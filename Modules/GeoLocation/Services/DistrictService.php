<?php

namespace Modules\GeoLocation\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Repositories\Contracts\DistrictRepositoryInterface;

class DistrictService
{
    private const TRANSLATED = ['name'];

    public function __construct(private readonly DistrictRepositoryInterface $districts) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $locale): District
    {
        return DB::transaction(function () use ($data, $locale): District {
            /** @var District $district */
            $district = $this->districts->query()->newModelInstance();

            return $this->save($district, $data, $locale);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(District $district, array $data, string $locale): District
    {
        return DB::transaction(fn (): District => $this->save($district, $data, $locale));
    }

    public function delete(District $district): void
    {
        DB::transaction(fn () => $this->remove($district));
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->districts->query()->whereKey($ids)->get()->each(
            fn (District $district) => $this->remove($district)
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(District $district, array $data, string $locale): District
    {
        $district->fill(Arr::except($data, self::TRANSLATED));
        Translations::fill($district, Translations::fromForm($data, $locale, self::TRANSLATED));
        $district->save();

        return $district;
    }

    /**
     * Its translations first: unlike the other locations, their foreign key does not cascade.
     */
    private function remove(District $district): void
    {
        $district->translations()->delete();

        $this->districts->delete($district);
    }
}
