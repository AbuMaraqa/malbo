<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\Currency;
use Modules\Core\Repositories\Contracts\CurrencyRepositoryInterface;
use Modules\Core\Support\Translations;

class CurrencyService
{
    private const TRANSLATED = ['name', 'plural_name'];

    public function __construct(private readonly CurrencyRepositoryInterface $currencies) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, string $locale, ?int $userId): Currency
    {
        return DB::transaction(function () use ($data, $locale, $userId): Currency {
            /** @var Currency $currency */
            $currency = $this->currencies->query()->newModelInstance();
            $currency->fill(['created_by' => $userId, ...$data]);
            Translations::fill($currency, Translations::fromForm($data, $locale, self::TRANSLATED));
            $currency->save();

            return $currency;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Currency $currency, array $data, string $locale): Currency
    {
        return DB::transaction(function () use ($currency, $data, $locale): Currency {
            $currency->fill($data);
            Translations::fill($currency, Translations::fromForm($data, $locale, self::TRANSLATED));
            $currency->save();

            return $currency;
        });
    }

    public function delete(Currency $currency): void
    {
        $this->currencies->delete($currency);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->currencies->query()->whereKey($ids)->get()->each(
            fn (Currency $currency) => $this->currencies->delete($currency)
        ));
    }
}
