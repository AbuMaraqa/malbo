<?php

namespace Modules\GeoLocation\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\AdminFilter;
use Modules\GeoLocation\Support\GeoFilters;
use Spatie\QueryBuilder\AllowedFilter;


class CountryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // --- الحقول التي أضفتها لإصلاح الكود ---
            'id'                => $this->id,
            'name'              => $this->name, // كان مفقوداً، وهو مطلوب للـ sort

            // --- الحقول التي كانت موجودة ---
            'code'              => $this->code,
            'phone_code'        => $this->phone_code,
            'currency_id'       => $this->currency_id,
            'currency'          => $this->whenLoaded('currency'),
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            GeoFilters::integer('id'),
            AdminFilter::translated('name'),
            AdminFilter::text('code'),
            AllowedFilter::exact('phone_code'),
            GeoFilters::integer('currency_id'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'code',
            'phone_code',
            'currency_id',
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            'id',
            GeoFilters::nameSort(),
            'created_at',
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            GeoFilters::includeWithTranslations('currency'),
        ];
    }
}
