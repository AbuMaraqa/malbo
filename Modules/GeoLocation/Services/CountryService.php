<?php

namespace Modules\GeoLocation\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Core\Support\Translations;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Exceptions\LocationInUseException;
use Modules\GeoLocation\Repositories\Contracts\CountryRepositoryInterface;

class CountryService
{
    private const TRANSLATED = ['name'];

    public function __construct(
        private readonly CountryRepositoryInterface $countries,
        private readonly BranchRepositoryInterface $branches,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $locale): Country
    {
        return DB::transaction(function () use ($data, $locale): Country {
            /** @var Country $country */
            $country = $this->countries->query()->newModelInstance();

            return $this->save($country, $data, $locale);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Country $country, array $data, string $locale): Country
    {
        return DB::transaction(fn (): Country => $this->save($country, $data, $locale));
    }

    public function delete(Country $country): void
    {
        DB::transaction(fn () => $this->remove($country));
    }

    /**
     * Deletes all of them or, when one is in use, none.
     *
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->countries->query()->whereKey($ids)->get()->each(
            fn (Country $country) => $this->remove($country)
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Country $country, array $data, string $locale): Country
    {
        $country->fill(Arr::except($data, self::TRANSLATED));
        Translations::fill($country, Translations::fromForm($data, $locale, self::TRANSLATED));
        $country->save();

        return $country;
    }

    private function remove(Country $country): void
    {
        if ($country->governorates()->exists() || $this->branches->existsInCountry($country->id)) {
            throw new LocationInUseException(__('Cannot delete :name: it has governorates or branches', ['name' => $country->name]));
        }

        $this->countries->delete($country);
    }
}
