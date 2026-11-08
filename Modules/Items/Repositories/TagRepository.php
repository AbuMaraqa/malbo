<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Tag;
use Modules\Items\Repositories\Contracts\TagRepositoryInterface;

class TagRepository extends BaseEloquentRepository implements TagRepositoryInterface
{
    public function __construct(Tag $model)
    {
        parent::__construct($model);
    }

    public function names(): array
    {
        return $this->query()->with('translations')->orderBy('id')->get()
            ->map(fn ($model) => (string) $model->name)
            ->filter()
            ->values()
            ->all();
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations']);
    }
}
