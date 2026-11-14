<?php

namespace Modules\Items\Support;

use App\Support\Locales;
use Closure;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Settings\GeneralSettings;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\OrderItem;
use Modules\Items\Enums\DeliveryType;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\PaymentMethod;
use Modules\Items\Enums\PaymentStatus;
use Modules\Items\Services\Couriers\CourierRegistry;
use Modules\Items\Support\OrderBundles;
use Modules\Store\Settings\StoreAppearanceSettings;
use Modules\Store\Settings\StoreContactSettings;
use Modules\Store\Support\Price;
use Throwable;

/**
 * An order as its invoice and waybill print it, in one language: the names the lines were sold
 * under (their snapshot), the store, the buyer and where the parcel goes, the money and, once
 * there is a shipment, its courier. Plain strings only: the Blade views compute nothing.
 *
 * Texts are translated in $locale; the request's own locale is restored afterwards.
 */
final class OrderDocument
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Order $order, string $locale): array
    {
        return self::inLocale($locale, fn () => self::build($order, $locale));
    }

    /**
     * Why the order gets no waybill, or null when it does: it is not delivered (picked up or sold
     * in a shop), or it is closed without reaching the buyer's door again (cancelled, rejected,
     * returned, exchanged).
     */
    public static function waybillBlocker(Order $order): ?string
    {
        if ($order->delivery_type !== null && $order->delivery_type !== DeliveryType::DELIVERY) {
            return __('it is not a delivery order');
        }

        if (in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REJECTED, OrderStatus::RETURNED, OrderStatus::EXCHANGED], true)) {
            return $order->status->getLabel();
        }

        return null;
    }

    /**
     * Runs $callback with $locale as the application's locale, then puts the previous one back.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function inLocale(string $locale, Closure $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(Order $order, string $locale): array
    {
        $order->loadMissing(['items' => fn ($items) => $items->orderBy('id'), 'city.translations', 'district.translations', 'user', 'shipment']);

        $text = fn (?array $values) => (string) ($values[$locale] ?? collect($values ?? [])->first(fn ($value) => filled($value)) ?? '');
        $money = fn (float|string|null $amount) => (new Price((float) $amount))->format();
        $collect = $order->payment_method === PaymentMethod::CASH && $order->payment_status !== PaymentStatus::PAID && (float) $order->total > 0;
        $shipment = $order->shipment;

        return [
            'locale' => $locale,
            'dir' => Locales::isRtl($locale) ? 'rtl' : 'ltr',
            'store' => self::store(),
            'number' => (string) $order->order_number,
            'date' => $order->created_at?->format('Y-m-d H:i'),
            'customer' => [
                'name' => $order->customer_name ?: $order->user?->name,
                'phone' => $order->contact_phone,
                'email' => $order->customer_email,
                'address' => $order->delivery_address,
                'city' => $order->city?->translate($locale, true)?->name ?? $order->city?->name,
                'district' => $order->district?->translate($locale, true)?->name ?? $order->district?->name,
            ],
            'notes' => $order->notes,
            'lines' => $order->items->map(fn (OrderItem $item, int $index) => [
                // A set's pieces follow its heading row (`first`): the set's price is on it.
                'bundle' => $item->bundle_group === null ? null : [
                    'key' => $item->bundle_group,
                    'name' => OrderBundles::name($item, $locale),
                    'sku' => $item->bundle['sku'] ?? null,
                    'quantity' => OrderBundles::quantity($item),
                    'unit_price' => $money(OrderBundles::unitPrice($item)),
                    'total' => $money($order->items->where('bundle_group', $item->bundle_group)->sum(fn (OrderItem $piece) => (float) $piece->total_price)),
                    'first' => $order->items->values()->search(fn (OrderItem $piece) => $piece->bundle_group === $item->bundle_group) === $index,
                ],
                'name' => $text($item->product_name) ?: __('Deleted product'),
                'options' => collect($item->options ?? [])
                    ->map(fn (array $option) => trim($text($option['name'] ?? null).': '.$text($option['value'] ?? null), ': '))
                    ->filter()
                    ->implode(' · '),
                'sku' => $item->sku,
                'quantity' => (int) $item->quantity,
                'unit_price' => $money($item->price),
                'total' => $money($item->total_price),
            ])->values()->all(),
            'item_count' => (int) $order->items->sum('quantity'),
            'sub_total' => $money($order->sub_total),
            'coupon_code' => $order->coupon_code,
            'discount' => (float) $order->discount > 0 ? $money($order->discount) : null,
            'delivery_fee' => $money($order->delivery_fee),
            'total' => $money($order->total),
            'payment_method' => self::paymentMethod($order->payment_method),
            'payment_status' => self::paymentStatus($order->payment_status),
            'status' => $order->status?->getLabel(),
            // Cash on delivery not paid yet: what the courier collects at the door.
            'collect' => $collect ? $money($order->total) : null,
            'courier' => $shipment ? app(CourierRegistry::class)->name($shipment->courier) : null,
            'tracking_number' => $shipment?->tracking_number,
        ];
    }

    /**
     * The store's name and contact, and its logo as a file on this server (mPDF reads it from the
     * disk; nothing is fetched over the network).
     *
     * @return array{name: string, logo: ?string, phone: ?string, email: ?string}
     */
    private static function store(): array
    {
        $general = app(GeneralSettings::class);
        $contact = rescue(fn () => app(StoreContactSettings::class), null, false);
        $logoPath = rescue(fn () => app(StoreAppearanceSettings::class)->logo_path, null, false);

        return [
            'name' => $general->site_name,
            'logo' => self::localFile($logoPath),
            'phone' => $contact?->phone,
            'email' => $contact?->email ?: $general->email_address_for_contact,
        ];
    }

    private static function localFile(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            return $disk->exists($path) ? $disk->path($path) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The enum's own labels are Arabic texts; the documents speak every language.
     */
    private static function paymentMethod(?PaymentMethod $method): ?string
    {
        return match ($method) {
            PaymentMethod::CASH => __('Cash on delivery'),
            PaymentMethod::CREDIT_CARD => __('Credit card'),
            PaymentMethod::BANK_TRANSFER => __('Bank transfer'),
            PaymentMethod::ON_ACCOUNT => __('On credit'),
            null => null,
        };
    }

    private static function paymentStatus(?PaymentStatus $status): ?string
    {
        return match ($status) {
            PaymentStatus::PAID => __('Paid'),
            PaymentStatus::UNPAID => __('Unpaid'),
            PaymentStatus::REFUNDED => __('Refunded'),
            PaymentStatus::FAILED => __('Payment failed'),
            null => null,
        };
    }
}
