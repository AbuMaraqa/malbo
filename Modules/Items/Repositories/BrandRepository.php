<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Brand;
use Modules\Items\Repositories\Contracts\BrandRepositoryInterface;

class BrandRepository extends BaseEloquentRepository implements BrandRepositoryInterface
{
    public function __construct(Brand $model)
    {
        parent::__construct($model);
    }

    public function options(): array
    {
        return $this->query()->with('translations')->orderBy('id')->get()
            ->map(fn ($model) => ['value' => $model->id, 'label' => (string) $model->name])
            ->all();
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations', 'media']);
    }
}
