<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Offer;
use Modules\Items\Repositories\Contracts\OfferRepositoryInterface;

class OfferRepository extends BaseEloquentRepository implements OfferRepositoryInterface
{
    public function __construct(Offer $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations', 'media', 'branch.translations', 'offerable.translations']);
    }

    public function findForForm(int $id): Offer
    {
        /** @var Offer */
        return $this->query()->with(['translations', 'media'])->findOrFail($id);
    }

    public function findCoupon(string $code, bool $lock = false): ?Offer
    {
        if (trim($code) === '') {
            return null;
        }

        /** @var Offer|null */
        return $this->query()
            ->whereNotNull('code')
            // No wildcard: an exact match that ignores letter case.
            ->whereLike('code', addcslashes(trim($code), '%_\\'), caseSensitive: false)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->orderBy('id')
            ->first();
    }
}
