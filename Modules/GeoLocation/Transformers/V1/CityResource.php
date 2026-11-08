<?php

namespace Modules\GeoLocation\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\AdminFilter;
use Modules\GeoLocation\Support\GeoFilters;


class CityResource extends JsonResource
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
            'governorate_id'  => $this->governorate_id,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'population'      => $this->population,
            'type'            => $this->type,
            'is_capital'      => (bool) $this->is_capital,
            'capital_type'    => $this->capital_type,
            'governorate'     => $this->whenLoaded('governorate'),
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            GeoFilters::integer('id'),
            AdminFilter::translated('name'),
            GeoFilters::integer('governorate_id'),
            GeoFilters::decimal('latitude'),
            GeoFilters::decimal('longitude'),
            GeoFilters::integer('population'),
            GeoFilters::integer('type'),
            AdminFilter::boolean('is_capital'),
            GeoFilters::integer('capital_type'),
            AdminFilter::relation('country_id', 'governorate', 'country_id'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'governorate_id',
            'latitude',
            'longitude',
            'population',
            'type',
            'is_capital',
            'capital_type',
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            'id',
            GeoFilters::nameSort(),
            'population',
            'created_at',
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            GeoFilters::includeWithTranslations('governorate'),
        ];
    }
}
