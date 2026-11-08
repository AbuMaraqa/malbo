<?php

namespace Modules\Branch\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\AdminFilter;
use Modules\GeoLocation\Support\GeoFilters;
use Spatie\QueryBuilder\AllowedFilter;


class BranchResource extends JsonResource
{
    /**
     * تحديد الحقول التي يمكن للمستخدم طلبها عبر API.
     * الحقول المترجمة مثل 'name' ستكون متاحة مباشرة على الموديل بفضل astrotomic/translatable.
     */
    public static function allowedFields(): array
    {
        return [
            'id',
            'phone',
            'email',
            'city_id',
            'country_id',
            'latitude',
            'longitude',
            'opening_time',
            'closing_time',
            'status',
        ];
    }

    /**
     * تحديد الفلاتر المسموح بها.
     * نستخدم callback للحقول المترجمة لاستدعاء الـ scope المخصص في الموديل.
     */
    public static function allowedFilters(): array
    {
        return [
            GeoFilters::integer('id'),
            AdminFilter::translated('name'),
            AdminFilter::translated('address'),
            AllowedFilter::exact('phone'),
            AllowedFilter::exact('email'),
            GeoFilters::integer('city_id'),
            GeoFilters::integer('country_id'),
            GeoFilters::decimal('latitude'),
            GeoFilters::decimal('longitude'),
            GeoFilters::integer('status'),
        ];
    }

    public static function allowedSorts(): array
    {
        return [
            'id',
            'status',
            GeoFilters::nameSort(),
        ];
    }

    public static function allowedIncludes(): array
    {
        return [
            'workingHours',
            'translations',
        ];
    }

    /**
     * تحويل الـ resource إلى مصفوفة.
     * من الأفضل تحديد الحقول هنا بشكل صريح.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'description'   => $this->description,
            'address'       => $this->address,
            'phone'         => $this->phone,
            'email'         => $this->email,
            'city_id'       => $this->city_id,
            'country_id'    => $this->country_id,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
            'opening_time'  => $this->opening_time,
            'closing_time'  => $this->closing_time,
            'status'        => $this->status,
            'working_hours' => BranchWorkingHourResource::collection($this->whenLoaded('workingHours')),
            'translations'   => $this->whenLoaded('translations')
        ];
    }
}
