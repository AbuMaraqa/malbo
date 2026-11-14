<?php

namespace Modules\Wholesale\Services\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\UserRole;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderStatus;
use Modules\Items\Enums\ShipmentStatus;
use Modules\Store\Contracts\DemoPart;
use Modules\Store\Services\Demo\DemoStore;
use Modules\Store\Services\Demo\DemoStoreBuilder;
use Modules\Store\Support\Phone;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\MerchantStatus;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleProductRepositoryInterface;
use Modules\Wholesale\Services\MerchantLedger;
use Modules\Wholesale\Services\MerchantService;
use Modules\Wholesale\Services\TierService;
use Modules\Wholesale\Services\WholesaleCartService;
use Modules\Wholesale\Services\WholesaleCheckoutService;
use Modules\Wholesale\Services\WholesalePriceService;

/**
 * The demo store's wholesale side: two price tiers, an approved merchant (gold tier, credit) with a
 * delivered order, a payment and an order on account, a merchant still waiting for approval, a
 * quantity break, and incoming scarves merchants can reserve.
 */
final class WholesaleDemo implements DemoPart
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly MerchantRepositoryInterface $merchants,
        private readonly MerchantService $merchantService,
        private readonly TierService $tiers,
        private readonly WholesalePriceService $prices,
        private readonly WholesaleProductRepositoryInterface $products,
        private readonly WholesaleCartService $cart,
        private readonly WholesaleCheckoutService $checkout,
        private readonly MerchantLedger $ledger,
        private readonly DemoStoreBuilder $builder,
    ) {}

    public function build(DemoStore $demo): void
    {
        DB::transaction(function () use ($demo): void {
            $silver = $this->tier($demo, ['فضي', 'Silver', 'כסף'], 5, default: true);
            $gold = $this->tier($demo, ['ذهبي', 'Gold', 'זהב'], 10);
            $demo->extra['tiers'] = ['silver' => $silver, 'gold' => $gold];

            $approved = $this->merchant($demo, 'merchant', 'محلات الأناقة للألبسة', 'خالد منصور', '0599555111', 'CR-562190', 'south');
            $this->merchantService->changeStatus($approved, MerchantStatus::APPROVED, null, $demo->bot->id);
            $this->merchantService->update($approved->fresh(), [
                'shop_name' => $approved->shop_name, 'owner_name' => $approved->owner_name, 'city_id' => $approved->city_id,
                'phone' => $approved->phone, 'commercial_register' => $approved->commercial_register, 'shop_address' => $approved->shop_address,
                'tier_id' => $gold->id, 'sales_rep_id' => $demo->staffId,
                'credit_enabled' => true, 'credit_limit' => 8000,
            ]);
            $pending = $this->merchant($demo, 'pending-merchant', 'بوتيك النجمة', 'سعيد عيسى', '0569555222', 'CR-771034', 'north');

            $demo->extra['merchants'] = ['approved' => $approved->fresh(), 'pending' => $pending];
            $demo->logins[] = ['Merchant (approved, gold tier)', $approved->email, $demo->password];
            $demo->logins[] = ['Merchant (pending approval)', $pending->email, $demo->password];

            // Chinos by the piece, 5% off from 20 pieces; scarves arriving in three weeks.
            $this->prices->save($this->products->forPriceEdit($demo->products['chinos']->id), [
                'by_piece' => true, 'prices' => [], 'breaks' => [['min_quantity' => 20, 'discount_percent' => 5]],
            ]);
            $scarf = $this->products->forPriceEdit($demo->products['wool-scarf']->id);
            $this->prices->saveIncoming($scarf, [$scarf->id => [
                'quantity' => 40, 'expected_at' => Carbon::now()->addWeeks(3)->toDateString(),
                'loaded_quantity' => (int) $scarf->incoming_quantity, 'loaded_expected_at' => $scarf->incoming_expected_at?->toDateString(),
            ]], $demo->bot->id);
        });

        $merchant = $demo->extra['merchants']['approved'];

        // Delivered two weeks ago (invoiced), and paid by bank transfer since.
        $delivered = $this->order($demo, $merchant, 15, 'cash_on_delivery', [
            ['oxford-shirt', ['white.S' => 2, 'white.M' => 4, 'white.L' => 4, 'white.XL' => 2]],
            ['polo', ['navy.M' => 3, 'navy.L' => 3]],
        ]);
        $this->builder->advance($demo, $delivered, 15, [OrderStatus::CONFIRMED, OrderStatus::PREPARING], ShipmentStatus::DELIVERED);
        $demo->at(5, fn () => $this->ledger->recordPayment($merchant, [
            'amount' => round((float) $delivered->fresh()->total / 2, 2), 'method' => 'bank_transfer', 'reference' => 'TRX-20431',
            'note' => 'دفعة أولى', 'entry_date' => Carbon::now()->toDateString(),
        ], null, $demo->bot->id));

        // On account, waiting for the store to confirm it.
        $onAccount = $this->order($demo, $merchant, 1, 'credit', [
            ['chinos', ['beige.30' => 5, 'beige.32' => 8, 'beige.34' => 8, 'beige.36' => 4]],
        ]);

        $demo->extra['wholesale_orders'] = ['delivered' => $delivered->fresh(), 'on_account' => $onAccount];
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $names
     */
    private function tier(DemoStore $demo, array $names, float $discount, bool $default = false): \Modules\Wholesale\Entities\Tier
    {
        $tier = $this->tiers->create(['name' => $names[0], 'discount_percent' => $discount, 'sort_order' => 0, 'is_default' => $default && ! DB::table((new \Modules\Wholesale\Entities\Tier)->getTable())->where('is_default', true)->exists()], 'ar', $demo->bot->id);
        Translations::fill($tier, ['en' => ['name' => $names[1]], 'he' => ['name' => $names[2]]]);
        $tier->save();

        return $tier;
    }

    /**
     * A shop's join request as the storefront makes it (MerchantApplicationService, without signing
     * in from the console): a merchant account and a pending merchant.
     */
    private function merchant(DemoStore $demo, string $name, string $shop, string $owner, string $phone, string $register, string $zone): Merchant
    {
        $email = $demo->email($name);

        $user = $this->users->create(['name' => $owner, 'email' => $email, 'phone' => Phone::canonical($phone), 'password' => $demo->password]);
        $user->forceFill(['email_verified_at' => Carbon::now()])->save();
        $user->assignRole(UserRole::MERCHANT->value);

        /** @var Merchant $merchant */
        $merchant = $this->merchants->create([
            'user_id' => $user->id, 'shop_name' => $shop, 'owner_name' => $owner, 'city_id' => $demo->cities[$zone]->id ?? null,
            'phone' => Phone::canonical($phone), 'email' => $email, 'commercial_register' => $register,
            'shop_address' => 'شارع السوق التجاري، محل 7', 'status' => MerchantStatus::PENDING,
        ]);
        $merchant->statusChanges()->create(['from_status' => null, 'to_status' => MerchantStatus::PENDING, 'note' => null, 'created_by' => $user->id]);

        return $merchant;
    }

    /**
     * A wholesale order as the portal places it: the cart set product by product, then checkout.
     *
     * @param  list<array{0: string, 1: array<string, int>}>  $lines  [product key, ["colour.size" => pieces]]
     */
    private function order(DemoStore $demo, Merchant $merchant, float $daysAgo, string $terms, array $lines): Order
    {
        return $demo->at($daysAgo, function () use ($demo, $merchant, $terms, $lines): Order {
            foreach ($lines as [$key, $pieces]) {
                $quantities = [];

                foreach ($pieces as $item => $count) {
                    [$color, $size] = explode('.', $item);
                    $quantities[$demo->item($key, $color, $size)->id] = $count;
                }

                $this->cart->set($merchant, $demo->products[$key]->id, $quantities);
            }

            return $this->checkout->place($this->cart->current((int) $merchant->user_id), $merchant, [
                'address_source' => 'shop', 'address_id' => null, 'notes' => null, 'payment_terms' => $terms,
            ]);
        });
    }
}
