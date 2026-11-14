<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\MarketingOptOut;
use Modules\Store\Repositories\Contracts\MarketingOptOutRepositoryInterface;

class MarketingOptOutRepository extends BaseEloquentRepository implements MarketingOptOutRepositoryInterface
{
    public function __construct(MarketingOptOut $model)
    {
        parent::__construct($model);
    }

    public function optedOut(?string $email, ?string $phoneDigits): bool
    {
        if (blank($email) && blank($phoneDigits)) {
            return false;
        }

        return $this->query()->where(function (Builder $query) use ($email, $phoneDigits): void {
            if (filled($email)) {
                $query->orWhere(fn (Builder $query) => $query->where('channel', MarketingOptOut::EMAIL)->where('value', $email));
            }

            if (filled($phoneDigits)) {
                $query->orWhere(fn (Builder $query) => $query->where('channel', MarketingOptOut::PHONE)->where('value', $phoneDigits));
            }
        })->exists();
    }
}
