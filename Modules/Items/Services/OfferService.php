<?php

namespace Modules\Items\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Translations;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Offer;
use Modules\Items\Entities\Product;
use Modules\Items\Repositories\Contracts\OfferRepositoryInterface;

/**
 * Writes offers. On add, one offer per chosen branch, sharing the uploaded image.
 */
class OfferService
{
    /** What the form's choice of target means. */
    public const TARGETS = [
        'items_category' => Category::class,
        'items_product' => Product::class,
    ];

    private const TRANSLATED = ['name', 'description'];

    private const COLUMNS = [
        'type', 'value', 'code', 'min_purchase_amount', 'usage_limit', 'usage_limit_per_user',
        'start_date', 'end_date', 'is_active', 'offerable_id',
    ];

    public function __construct(private readonly OfferRepositoryInterface $offers) {}

    /**
     * @param  array<string, mixed>  $data  with `branch_id` a list
     * @return list<Offer>
     */
    public function create(array $data, string $locale, ?int $userId): array
    {
        return DB::transaction(function () use ($data, $locale, $userId): array {
            $created = [];

            foreach (array_values($data['branch_id']) as $position => $branchId) {
                /** @var Offer $offer */
                $offer = $this->offers->query()->newModelInstance();
                $this->fill($offer, $data, $locale);
                $offer->fill(['branch_id' => $branchId, 'created_by' => $userId]);
                $offer->save();

                if ($position === 0) {
                    $offer->addImage($data['image'] ?? null);
                } elseif ($image = $created[0]->getFirstMedia('offer')) {
                    $offer->addMedia($image->getPath())->preservingOriginal()->toMediaCollection('offer', 'offers');
                }

                $created[] = $offer;
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $data  with `branch_id` one id
     */
    public function update(Offer $offer, array $data, string $locale): Offer
    {
        return DB::transaction(function () use ($offer, $data, $locale): Offer {
            $this->fill($offer, $data, $locale);
            $offer->fill(['branch_id' => $data['branch_id']]);
            $offer->save();

            if (! empty($data['removed_image'])) {
                $offer->getMedia('offer')->whereIn('id', array_map('intval', $data['removed_image']))->each->delete();
                $offer->unsetRelation('media');
            }

            if (! empty($data['image'])) {
                $offer->addImage($data['image']); // the collection holds one file: the old one goes
            }

            return $offer;
        });
    }

    public function delete(Offer $offer): void
    {
        $this->offers->delete($offer);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): void
    {
        DB::transaction(fn () => $this->offers->query()->whereKey($ids)->get()->each(
            fn (Offer $offer) => $this->offers->delete($offer)
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Offer $offer, array $data, string $locale): void
    {
        Translations::fill($offer, Translations::fromForm($data, $locale, self::TRANSLATED));

        $offer->fill(Arr::only($data, self::COLUMNS));
        $offer->offerable_type = self::TARGETS[$data['offerable_model']];
    }
}
