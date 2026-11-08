<?php

namespace Modules\Store\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Branch\Entities\Branch;
use Modules\Store\Services\StorefrontContact;

/**
 * An open branch as buyers see it on the contact page: where it is, how to call it, its hours.
 *
 * @mixin Branch
 */
class StoreBranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'hours' => StorefrontContact::hours($this->opening_time, $this->closing_time),
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
        ];
    }
}
