<?php

namespace Modules\GeoLocation\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\AdminFilter;
use Modules\GeoLocation\Support\GeoFilters;


class GovernorateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'code'       => $this->code,
            'country_id' => $this->country_id,
            'country'    => $this->whenLoaded('country'),
            'cities'     => $this->whenLoaded('cities'),
        ];
    }

    public static function allowedFilters(): array
    {
        return [
            GeoFilters::integer('id'),
            GeoFilters::integer('country_id'),
            AdminFilter::text('code'),
            AdminFilter::translated('name'),
        ];
    }

    public static function allowedFields(): array
    {
        return [
            'id',
            'code',
            'country_id',
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            'id',
            'code',
            'created_at',
            GeoFilters::nameSort(),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            GeoFilters::includeWithTranslations('country'),
            GeoFilters::includeWithTranslations('cities'),
        ];
    }
}
