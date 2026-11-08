<?php

namespace Modules\GeoLocation\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\AdminFilter;
use Modules\GeoLocation\Support\GeoFilters;


class DistrictResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'city_id'         => $this->city_id,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'type'            => $this->type,
            'city'            => $this->whenLoaded('city'),
            'governorate'     => $this->whenLoaded('governorate'),
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            GeoFilters::integer('id'),
            GeoFilters::integer('city_id'),
            GeoFilters::decimal('latitude'),
            GeoFilters::decimal('longitude'),
            GeoFilters::integer('type'),
            AdminFilter::translated('name'),
            AdminFilter::relation('governorate_id', 'city', 'governorate_id'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'city_id',
            'latitude',
            'longitude',
            'type',
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            'id',
            'created_at',
            GeoFilters::nameSort(),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            GeoFilters::includeWithTranslations('governorate'),
            GeoFilters::includeWithTranslations('city'),
        ];
    }
}
