<?php

namespace Modules\Wholesale\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Support\Translations;
use Modules\Wholesale\Entities\Tier;
use Modules\Wholesale\Repositories\Contracts\TierRepositoryInterface;
use Modules\Wholesale\Settings\WholesaleSettings;

/**
 * Writes price tiers (their numbers and their name in one locale) and the store-wide minimum order.
 * One tier at most is the default: making one the default takes it from the other, in the same
 * transaction. The default tier cannot be deleted; deleting another leaves its merchants on the default.
 */
class TierService
{
    private const TRANSLATED = ['name'];

    public function __construct(private readonly TierRepositoryInterface $tiers) {}

    /**
     * @param  array<string, mixed>  $data  name, discount_percent, sort_order, is_default
     */
    public function create(array $data, string $locale, int $userId): Tier
    {
        return self::oneDefault(fn (): Tier => DB::transaction(function () use ($data, $locale, $userId): Tier {
            /** @var Tier $tier */
            $tier = $this->tiers->query()->newModelInstance();

            return $this->save($tier, [...self::columns($data), 'created_by' => $userId], $data, $locale);
        }));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tier $tier, array $data, string $locale): Tier
    {
        return self::oneDefault(fn (): Tier => DB::transaction(fn (): Tier => $this->save($tier, self::columns($data), $data, $locale)));
    }

    /**
     * The partial unique index keeps one default. Should two saves still collide (the lock in
     * save() makes it unlikely), the second gets a form error, not a server error.
     *
     * @param  \Closure(): Tier  $write
     *
     * @throws ValidationException on `is_default`
     */
    private static function oneDefault(\Closure $write): Tier
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['is_default' => __('Another tier was just made the default. Reload the page and try again.')]);
        }
    }

    /**
     * @throws ValidationException on `tier` for the default tier
     */
    public function delete(Tier $tier): void
    {
        if ($tier->is_default) {
            throw ValidationException::withMessages(['tier' => __('The default tier cannot be deleted. Make another tier the default first.')]);
        }

        $this->tiers->delete($tier);
    }

    public function saveSettings(?float $value, ?int $pieces): void
    {
        $settings = app(WholesaleSettings::class);
        $settings->minimum_order_value = $value === null ? null : round($value, 2);
        $settings->minimum_order_pieces = $pieces;
        $settings->save();
    }

    /**
     * @param  array<string, mixed>  $columns
     * @param  array<string, mixed>  $data
     */
    private function save(Tier $tier, array $columns, array $data, string $locale): Tier
    {
        if ($columns['is_default']) {
            // Two saves choosing a default at once: the second waits here, then sees the first.
            DB::statement('LOCK TABLE '.$tier->getTable().' IN SHARE ROW EXCLUSIVE MODE');

            // The other default steps down first: the partial unique index allows one.
            $this->tiers->query()->where('is_default', true)->when($tier->exists, fn ($query) => $query->whereKeyNot($tier->id))->update(['is_default' => false]);
        }

        $tier->fill($columns);
        Translations::fill($tier, Translations::fromForm($data, $locale, self::TRANSLATED));
        $tier->save();

        return $tier;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function columns(array $data): array
    {
        return [
            'discount_percent' => round((float) ($data['discount_percent'] ?? 0), 2),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_default' => (bool) ($data['is_default'] ?? false),
        ];
    }
}
