<?php

namespace Modules\Wholesale\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Wholesale\Entities\Tier;
use Modules\Wholesale\Repositories\Contracts\TierRepositoryInterface;

class TierRepository extends BaseEloquentRepository implements TierRepositoryInterface
{
    public function __construct(Tier $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with('translations')->withCount('merchants');
    }

    public function forEdit(int $id): Tier
    {
        /** @var Tier */
        return $this->query()->with('translations')->findOrFail($id);
    }

    public function default(): ?Tier
    {
        /** @var Tier|null */
        return $this->query()->with('translations')->where('is_default', true)->first();
    }

    public function ordered(): Collection
    {
        return $this->query()->with('translations')->orderBy('sort_order')->orderBy('id')->get();
    }

    public function options(): array
    {
        return $this->ordered()
            ->map(fn (Tier $tier) => ['value' => (int) $tier->id, 'label' => (string) $tier->name])
            ->values()
            ->all();
    }
}
