<?php

namespace Modules\Wholesale\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Enums\PaymentStatus;
use Modules\Wholesale\Entities\LedgerEntry;
use Modules\Wholesale\Entities\Merchant;
use Modules\Wholesale\Enums\LedgerEntryType;
use Modules\Wholesale\Repositories\Contracts\LedgerEntryRepositoryInterface;

class LedgerEntryRepository extends BaseEloquentRepository implements LedgerEntryRepositoryInterface
{
    public function __construct(LedgerEntry $model)
    {
        parent::__construct($model);
    }

    public function balance(int $merchantId, ?string $before = null): float
    {
        return round((float) $this->query()
            ->where('merchant_id', $merchantId)
            ->when($before !== null, fn (Builder $query) => $query->whereDate('entry_date', '<', $before))
            ->sum('amount'), 2);
    }

    public function tableQuery(int $merchantId): Builder
    {
        $table = $this->model->getTable();

        // The running balance is worked out over the whole account first, so a filter on the
        // outer query hides rows without changing the balance shown on the others.
        $running = $this->query()
            ->select($table.'.*')
            ->selectRaw('sum(amount) over (order by entry_date, id rows between unbounded preceding and current row) as running_balance')
            ->where('merchant_id', $merchantId);

        return $this->query()
            ->fromSub($running, $table)
            ->with(['order:id,order_number,payment_status', 'createdBy:id,name']);
    }

    public function between(int $merchantId, ?string $from, ?string $to): Collection
    {
        return $this->query()
            ->where('merchant_id', $merchantId)
            ->when($from !== null, fn (Builder $query) => $query->whereDate('entry_date', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->whereDate('entry_date', '<=', $to))
            ->with('order:id,order_number')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }

    public function totalsByType(int $merchantId): array
    {
        return $this->query()
            ->where('merchant_id', $merchantId)
            ->groupBy('type')
            ->selectRaw('type, sum(amount) as total')
            ->pluck('total', 'type')
            ->map(fn ($total) => round((float) $total, 2))
            ->all();
    }

    public function invoicedAmount(int $orderId): ?float
    {
        $amount = $this->query()->where('order_id', $orderId)->where('type', LedgerEntryType::INVOICE->value)->value('amount');

        return $amount === null ? null : (float) $amount;
    }

    public function creditedAmount(int $orderId): float
    {
        return round(-(float) $this->query()->where('order_id', $orderId)->where('type', LedgerEntryType::CREDIT_NOTE->value)->sum('amount'), 2);
    }

    public function findForMerchant(int $merchantId, int $id): ?LedgerEntry
    {
        /** @var LedgerEntry|null */
        return $this->query()->where('merchant_id', $merchantId)->whereKey($id)->first();
    }

    public function sumBySource(int $orderId, array $sources): float
    {
        return round((float) $this->query()->where('order_id', $orderId)->whereIn('source', $sources)->sum('amount'), 2);
    }

    public function hasSource(int $orderId, string $source): bool
    {
        return $this->query()->where('order_id', $orderId)->where('source', $source)->exists();
    }

    public function unpaidInvoiceCount(int $merchantId): int
    {
        return $this->query()
            ->where('merchant_id', $merchantId)
            ->where('type', LedgerEntryType::INVOICE->value)
            ->whereHas('order', fn (Builder $order) => $order->where(fn (Builder $status) => $status->whereNull('payment_status')->orWhere('payment_status', '!=', PaymentStatus::PAID->value)))
            ->count();
    }

    public function hasCreditForReturn(int $returnRequestId): bool
    {
        return $this->query()->where('return_request_id', $returnRequestId)->exists();
    }

    public function lockMerchant(int $merchantId): void
    {
        Merchant::query()->whereKey($merchantId)->lockForUpdate()->first();
    }
}
