<?php

namespace Modules\GeoLocation\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\GeoLocation\Entities\Governorate;
use Modules\GeoLocation\Exceptions\LocationInUseException;
use Modules\GeoLocation\Repositories\Contracts\GovernorateRepositoryInterface;

class GovernorateService
{
    private const TRANSLATED = ['name'];

    public function __construct(private readonly GovernorateRepositoryInterface $governorates) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $locale): Governorate
    {
        return DB::transaction(function () use ($data, $locale): Governorate {
            /** @var Governorate $governorate */
            $governorate = $this->governorates->query()->newModelInstance();

            return $this->save($governorate, $data, $locale);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Governorate $governorate, array $data, string $locale): Governorate
    {
        return DB::transaction(fn (): Governorate => $this->save($governorate, $data, $locale));
    }

    public function delete(Governorate $governorate): void
    {
        DB::transaction(fn () => $this->remove($governorate));
    }

    /**
     * Deletes all of them or, when one is in use, none.
     *
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->governorates->query()->whereKey($ids)->get()->each(
            fn (Governorate $governorate) => $this->remove($governorate)
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Governorate $governorate, array $data, string $locale): Governorate
    {
        $governorate->fill(Arr::except($data, self::TRANSLATED));
        Translations::fill($governorate, Translations::fromForm($data, $locale, self::TRANSLATED));
        $governorate->save();

        return $governorate;
    }

    private function remove(Governorate $governorate): void
    {
        if ($governorate->cities()->exists()) {
            throw new LocationInUseException(__('Cannot delete :name: it has cities', ['name' => $governorate->name]));
        }

        $this->governorates->delete($governorate);
    }
}
