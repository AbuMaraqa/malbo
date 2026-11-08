<?php

namespace Modules\GeoLocation\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Core\Support\Translations;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Exceptions\LocationInUseException;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;

class CityService
{
    private const TRANSLATED = ['name'];

    public function __construct(
        private readonly CityRepositoryInterface $cities,
        private readonly BranchRepositoryInterface $branches,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $locale): City
    {
        return DB::transaction(function () use ($data, $locale): City {
            /** @var City $city */
            $city = $this->cities->query()->newModelInstance();

            return $this->save($city, $data, $locale);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(City $city, array $data, string $locale): City
    {
        return DB::transaction(fn (): City => $this->save($city, $data, $locale));
    }

    public function delete(City $city): void
    {
        DB::transaction(fn () => $this->remove($city));
    }

    /**
     * Deletes all of them or, when one is in use, none.
     *
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->cities->query()->whereKey($ids)->get()->each(
            fn (City $city) => $this->remove($city)
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(City $city, array $data, string $locale): City
    {
        $city->fill(Arr::except($data, self::TRANSLATED));

        // A capital type only means something on a capital.
        if (! $city->is_capital) {
            $city->capital_type = null;
        }

        Translations::fill($city, Translations::fromForm($data, $locale, self::TRANSLATED));
        $city->save();

        return $city;
    }

    private function remove(City $city): void
    {
        if ($city->districts()->exists() || $this->branches->existsInCity($city->id)) {
            throw new LocationInUseException(__('Cannot delete :name: it has districts or branches', ['name' => $city->name]));
        }

        $this->cities->delete($city);
    }
}
