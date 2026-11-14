<?php

namespace Modules\Store\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\Core\Settings\GeneralSettings;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductActive;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Repositories\Contracts\BundleRepositoryInterface;
use Modules\Items\Repositories\Contracts\ProductRepositoryInterface;
use Modules\Store\Contracts\SmsGateway;
use Modules\Store\Entities\MarketingOptOut;
use Modules\Store\Entities\StockAlert;
use Modules\Store\Mail\BackInStockMail;
use Modules\Store\Repositories\Contracts\StockAlertRepositoryInterface;
use Modules\Store\Support\InLocale;
use Modules\Store\Support\Phone;
use Throwable;

/**
 * "Notify me when available". A buyer leaves an email or a phone for an item (a variant, or a
 * product without variants) that is on the storefront but cannot be ordered now; one waiting alert
 * per item and contact.
 *
 * When InventoryService says items can be ordered again (ProductsBecameOrderable, after the commit
 * and after the response), their waiting alerts are claimed one by one (`notified_at`, so two runs
 * never send the same one) and sent in the subscriber's language: email, else SMS. Not while the
 * product is off the storefront, and never to a contact that opted out.
 */
class StockAlertService
{
    /** Waiting alerts one email or phone may hold at once. */
    public const MAX_WAITING = 10;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly StockAlertRepositoryInterface $alerts,
        private readonly OptOutService $optOuts,
        private readonly SmsGateway $sms,
        private readonly BundleRepositoryInterface $bundles,
    ) {}

    /**
     * @throws ValidationException on `product_id` (not on the storefront, or orderable now)
     */
    public function subscribe(int $productId, ?string $email, ?string $phone, string $locale): void
    {
        $product = $this->products->forCart([$productId])->get($productId);

        if (! $this->listed($product)) {
            throw ValidationException::withMessages(['product_id' => __('This product is no longer available.')]);
        }

        if (ProductConfigurator::available($product)) {
            throw ValidationException::withMessages(['product_id' => __('This item can be ordered now.')]);
        }

        $email = filled($email) ? mb_strtolower(trim((string) $email)) : null;
        $phone = filled($phone) ? Phone::canonical((string) $phone) : null;

        // Contacts are not confirmed: one cannot be signed up for the whole catalog.
        if ($this->alerts->waitingCount($email, $phone) >= self::MAX_WAITING) {
            throw ValidationException::withMessages(['product_id' => __('You are already waiting for :count items. We will write as they come back.', ['count' => self::MAX_WAITING])]);
        }

        // A second request for the same item and contact meets the partial unique index: nothing new.
        $this->alerts->query()->insertOrIgnore([
            'product_id' => $product->id,
            'email' => $email,
            'phone' => $phone,
            'locale' => InLocale::valid($locale),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Tells the waiting subscribers of these items that can be ordered on the storefront now.
     *
     * @param  list<int>  $productIds
     * @return int alerts sent
     */
    public function notifyAvailable(array $productIds): int
    {
        // Sets and bundles holding these items may be orderable again too (BundleStock decides).
        $productIds = array_values(array_unique([...array_map('intval', $productIds), ...$this->bundles->containing(array_map('intval', $productIds))]));
        $products = $this->products->forCart($productIds)
            ->filter(fn (Product $product) => $this->listed($product) && ProductConfigurator::available($product));

        if ($products->isEmpty()) {
            return 0;
        }

        $sent = 0;

        foreach ($this->alerts->waitingFor(array_map('intval', $products->keys()->all())) as $alert) {
            if ($this->optOuts->optedOut($alert->email, $alert->phone)) {
                continue;
            }

            // Phone only, and no SMS provider yet: it waits (not claimed) until one is set up.
            if (blank($alert->email) && ! $this->sms->enabled()) {
                continue;
            }

            $claimed = $this->alerts->query()->whereKey($alert->id)->whereNull('notified_at')->update(['notified_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

            if ($claimed === 0) {
                continue; // another run has it
            }

            try {
                $this->send($alert, $products->get($alert->product_id));
                $sent++;
            } catch (Throwable $e) {
                // Waiting again: the next time the item comes back, it is tried again.
                $this->alerts->query()->whereKey($alert->id)->update(['notified_at' => null]);
                Log::warning('Back-in-stock alert not sent: '.$e->getMessage(), ['alert' => $alert->id]);
            }
        }

        return $sent;
    }

    private function send(StockAlert $alert, Product $product): void
    {
        InLocale::run($alert->locale, function () use ($alert, $product): void {
            $parent = $product->parent ?? $product;
            $store = (string) app(GeneralSettings::class)->site_name;
            $values = $product->parent_id ? $product->attributeValues->sortBy('attribute_id') : collect();
            $url = route('store.product', ['slug' => $parent->slug]);

            if ($values->isNotEmpty()) {
                $url .= '?'.http_build_query(['selected' => $values->mapWithKeys(fn (AttributeValue $value) => [(int) $value->attribute_id => (int) $value->id])->sortKeys()->all()]);
            }

            $options = $values->map(fn (AttributeValue $value) => (string) $value->name)->implode(' / ');
            $name = (string) $parent->name;

            if (filled($alert->email)) {
                Mail::to($alert->email)->locale(app()->getLocale())->send(new BackInStockMail(
                    name: $name,
                    options: $options,
                    url: $url,
                    unsubscribeUrl: $this->optOuts->url(MarketingOptOut::EMAIL, (string) $alert->email, app()->getLocale()),
                    store: $store,
                ));

                return;
            }

            $this->sms->send((string) $alert->phone, __(':product is back at :store: :url', [
                'product' => trim($name.($options !== '' ? ' ('.$options.')' : '')),
                'store' => $store,
                'url' => $url,
            ])."
".__('Stop these messages: :url', ['url' => $this->optOuts->url(MarketingOptOut::PHONE, (string) $alert->phone, app()->getLocale())]));
        });
    }

    /**
     * On the storefront and choosable, whatever its stock: a published, active product without
     * active variants, or an active variant (no hidden value) of one.
     */
    private function listed(?Product $product): bool
    {
        if ($product === null || $product->trashed()) {
            return false;
        }

        $parent = $product->parent;
        $sellable = $parent ?? $product;
        $active = fn (Product $item) => (int) $item->is_active === ProductActive::IS_ACTIVE->value;

        if ($product->parent_id && ($parent === null || $parent->trashed())) {
            return false;
        }

        if ($sellable->status !== ProductStatus::PUBLISHED || ! $active($sellable) || ! $active($product)) {
            return false;
        }

        if ($product->parent_id) {
            return ProductConfigurator::visible($product);
        }

        return $product->variations->isEmpty();
    }
}
