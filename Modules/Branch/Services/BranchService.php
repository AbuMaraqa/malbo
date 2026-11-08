<?php

namespace Modules\Branch\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Core\Support\Translations;

class BranchService
{
    private const TRANSLATED = ['name', 'description', 'address'];

    public function __construct(private readonly BranchRepositoryInterface $branches) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $locale, ?int $userId): Branch
    {
        return DB::transaction(function () use ($data, $locale, $userId): Branch {
            /** @var Branch $branch */
            $branch = $this->branches->query()->newModelInstance();
            $branch->created_by = $userId;

            return $this->save($branch, $data, $locale);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data, string $locale): Branch
    {
        return DB::transaction(fn (): Branch => $this->save($branch, $data, $locale));
    }

    public function delete(Branch $branch): void
    {
        $this->branches->delete($branch);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->branches->query()->whereKey($ids)->get()->each(
            fn (Branch $branch) => $this->branches->delete($branch)
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Branch $branch, array $data, string $locale): Branch
    {
        $branch->fill(Arr::except($data, [...self::TRANSLATED, 'working_hours']));
        Translations::fill($branch, Translations::fromForm($data, $locale, self::TRANSLATED));
        $branch->save();

        foreach ($data['working_hours'] ?? [] as $hour) {
            $isClosed = (bool) ($hour['is_closed'] ?? false);

            $branch->workingHours()->updateOrCreate(['day' => $hour['day']], [
                'is_closed' => $isClosed,
                'start_time' => $isClosed ? null : $hour['start_time'],
                'end_time' => $isClosed ? null : $hour['end_time'],
            ]);
        }

        return $branch;
    }
}
