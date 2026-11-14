<?php

namespace Modules\Reports\Services;

use Modules\Branch\Entities\Branch;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Reports\Support\ReportFilters;

/**
 * What the report filter bar offers, and the link to a report's CSV with the same filters.
 */
class ReportOptions
{
    public function __construct(
        private readonly BranchRepositoryInterface $branches,
        private readonly CategoryRepositoryInterface $categories,
    ) {}

    /**
     * @return list<array{value: int, label: string}>
     */
    public function branches(): array
    {
        return $this->branches->query()->with('translations')->orderBy('id')->get()
            ->map(fn (Branch $branch) => ['value' => $branch->id, 'label' => (string) ($branch->name ?? '#'.$branch->id)])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function categories(): array
    {
        return collect($this->categories->options())
            ->map(fn ($name, $id) => ['value' => (int) $id, 'label' => (string) ($name ?? '#'.$id)])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function exportUrl(string $report, ReportFilters $filters): string
    {
        return route("reports.{$report}.export", ['filter' => $filters->query()]);
    }
}
