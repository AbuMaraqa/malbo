<?php

namespace Modules\Store\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Core\Repositories\BaseEloquentRepository;
use Modules\Store\Entities\CampaignPage;
use Modules\Store\Repositories\Contracts\CampaignPageRepositoryInterface;

class CampaignPageRepository extends BaseEloquentRepository implements CampaignPageRepositoryInterface
{
    public function __construct(CampaignPage $model)
    {
        parent::__construct($model);
    }

    public function tableQuery(): Builder
    {
        return $this->query()->with('translations')->withCount('blocks');
    }

    public function withBlocks(int $id): CampaignPage
    {
        /** @var CampaignPage */
        return $this->query()->with(['translations', 'blocks.media'])->findOrFail($id);
    }

    public function findBySlug(string $slug): ?CampaignPage
    {
        /** @var CampaignPage|null */
        return $this->query()->with(['translations', 'blocks.media'])->where('slug', $slug)->first();
    }

    public function liveSlugs(): Collection
    {
        $now = Carbon::now();

        return $this->query()->toBase()
            ->where('status', CampaignPage::PUBLISHED)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('id')
            ->get(['slug', 'updated_at']);
    }

    public function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'campaign';
        $candidate = $base;

        for ($counter = 1; $this->query()->where('slug', $candidate)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists(); $counter++) {
            $candidate = $base.'-'.$counter;
        }

        return $candidate;
    }
}
