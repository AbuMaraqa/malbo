<?php

namespace Modules\Items\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Items\Entities\Attribute;
use Modules\Items\Enums\AttributeType;
use Modules\Items\Repositories\Contracts\AttributeRepositoryInterface;

class AttributeRepository extends BaseEloquentRepository implements AttributeRepositoryInterface
{
    public function __construct(Attribute $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with(['translations']);
    }

    public function withValues(): Collection
    {
        return $this->query()
            ->with(['translations', 'attributeValues' => fn ($values) => $values->orderBy('sort_order')->orderBy('id'), 'attributeValues.translations'])
            ->orderBy('id')
            ->get();
    }

    public function filterable(): Collection
    {
        return $this->query()
            ->with([
                'translations',
                'attributeValues' => fn ($values) => $values->where('is_active', true),
                'attributeValues.translations',
            ])
            ->where('is_filterable', true)
            ->orderBy('id')
            ->get();
    }

    public function sizeAttributeId(): ?int
    {
        $id = $this->query()->where('is_size', true)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function findForForm(int $id): Attribute
    {
        /** @var Attribute */
        return $this->query()->with(['translations', 'attributeValues.translations'])->findOrFail($id);
    }
}
