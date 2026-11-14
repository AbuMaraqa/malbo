<?php

namespace Modules\Wholesale\Services;

use Illuminate\Validation\ValidationException;
use Modules\Store\Support\Price;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Repositories\Contracts\LedgerEntryRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\MerchantRepositoryInterface;
use Modules\Wholesale\Repositories\Contracts\WholesaleOrderRepositoryInterface;

/**
 * A merchant's credit: its limit, what it owes (the ledger's balance), what its open B2B orders
 * have promised (placed, not delivered, not cancelled, rejected or returned), and the room left.
 * An order on credit must fit in that room; checkout checks it on the locked merchant row.
 */
class MerchantCredit
{
    public function __construct(
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly WholesaleOrderRepositoryInterface $orders,
        private readonly MerchantRepositoryInterface $merchants,
    ) {}

    /**
     * @return array{enabled: bool, limit: float, balance: float, open: float, room: float}
     */
    public function status(Merchant $merchant): array
    {
        $limit = round((float) $merchant->credit_limit, 2);
        $balance = $this->ledger->balance((int) $merchant->id);
        $open = $this->orders->openTotal((int) $merchant->user_id);

        return [
            'enabled' => (bool) $merchant->credit_enabled && $limit > 0,
            'limit' => $limit,
            'balance' => $balance,
            'open' => $open,
            'room' => round(max(0.0, $limit - $balance - $open), 2),
        ];
    }

    /**
     * The figures as the portal shows them, or null when the merchant has no credit.
     *
     * @return array<string, string>|null
     */
    public function summary(Merchant $merchant): ?array
    {
        $status = $this->status($merchant);

        if (! $status['enabled']) {
            return null;
        }

        return [
            'limit_label' => (new Price($status['limit']))->format(),
            'balance_label' => (new Price($status['balance']))->format(),
            'open_label' => (new Price($status['open']))->format(),
            'room_label' => (new Price($status['room']))->format(),
        ];
    }

    /**
     * Inside the checkout's transaction: the merchant row is locked, then read again, so two orders
     * on credit at once are checked one after the other.
     *
     * @throws ValidationException on `payment_terms`
     */
    public function assertRoom(Merchant $merchant, float $amount): void
    {
        $locked = $this->merchants->lock((int) $merchant->id);
        $status = $this->status($locked);

        if (! $status['enabled']) {
            throw ValidationException::withMessages(['payment_terms' => __('Credit is not available on your account. Choose another way to pay.')]);
        }

        if (round($amount, 2) > $status['room']) {
            throw ValidationException::withMessages(['payment_terms' => __('Your credit has :room left and this order is :total. Choose another way to pay, or order less.', [
                'room' => (new Price($status['room']))->format(),
                'total' => (new Price(round($amount, 2)))->format(),
            ])]);
        }
    }
}
