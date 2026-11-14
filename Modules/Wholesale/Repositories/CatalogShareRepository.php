<?php

namespace Modules\Wholesale\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Wholesale\Entities\CatalogShare;
use Modules\Wholesale\Repositories\Contracts\CatalogShareRepositoryInterface;

class CatalogShareRepository extends BaseEloquentRepository implements CatalogShareRepositoryInterface
{
    public function __construct(CatalogShare $model)
    {
        parent::__construct($model);
    }

    public function activeFor(int $merchantId): Collection
    {
        return $this->query()
            ->where('merchant_id', $merchantId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now())
            ->with('category.translations')
            ->latest('id')
            ->get();
    }

    public function findFor(int $merchantId, int $id): ?CatalogShare
    {
        /** @var CatalogShare|null */
        return $this->query()->where('merchant_id', $merchantId)->whereKey($id)->first();
    }

    public function findByToken(string $token): ?CatalogShare
    {
        /** @var CatalogShare|null */
        return $this->query()->where('token', $token)->with(['merchant', 'category'])->first();
    }
}
