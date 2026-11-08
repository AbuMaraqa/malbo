<?php

namespace Modules\GeoLocation\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\GeoLocation\Entities\Governorate;
use Modules\GeoLocation\Repositories\Contracts\GovernorateRepositoryInterface;

class GovernorateRepository extends BaseEloquentRepository implements GovernorateRepositoryInterface
{
    public function __construct(Governorate $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations', 'country.translations']);
    }

    public function options(): array
    {
        return $this->query()->with('translations')->get()
            ->map(fn (Governorate $governorate) => [
                'value' => $governorate->id,
                'label' => (string) $governorate->name,
                'country_id' => $governorate->country_id,
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    public function codes(): array
    {
        return $this->query()->whereNotNull('code')->distinct()->orderBy('code')->pluck('code')->all();
    }
}
