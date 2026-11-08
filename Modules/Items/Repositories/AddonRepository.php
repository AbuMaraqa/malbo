<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Addon;
use Modules\Items\Repositories\Contracts\AddonRepositoryInterface;

class AddonRepository extends BaseEloquentRepository implements AddonRepositoryInterface
{
    public function __construct(Addon $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations', 'media']);
    }

    public function withOptions(): Collection
    {
        return $this->query()
            ->with(['translations', 'addonOptions' => fn ($options) => $options->orderBy('id'), 'addonOptions.translations'])
            ->orderBy('id')
            ->get();
    }

    public function findForForm(int $id): Addon
    {
        /** @var Addon */
        return $this->query()
            ->with(['translations', 'addonOptions' => fn ($query) => $query->orderBy('id'), 'addonOptions.translations', 'addonOptions.media'])
            ->findOrFail($id);
    }
}
