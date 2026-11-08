<?php

namespace Modules\Items\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class AddonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name, // من ملف Addon.php
            'description' => $this->description, // من ملف Addon.php
            'type' => $this->type->getLabel(),
            'image' => $this->image,
            $this->mergeWhen(isset($this->pivot), function () {
                // هذه البيانات من جدول addon_product
                return [
                    'is_required' => (bool) $this->pivot->is_required,
                    'min_selection' => (int) $this->pivot->min_selection,
                    'max_selection' => $this->pivot->max_selection ? (int) $this->pivot->max_selection : null,
                    'max_quantity_per_option' => (int) $this->pivot->max_quantity_per_option,
                ];
            }),

            // ---== بداية الكود المُصحح لـ options ==---
            'options' => $this->when(
            // الحالة الأولى: هذا الريسورس معروض ضمن منتج (Pivot موجود)
                isset($this->pivot) && $this->pivot->id,

                // ماذا نفعل إذا كان ضمن منتج:
                function () {
                    $customRows = collect($this->resource->getAttribute('custom_option_rows'));

                    if ($customRows->isEmpty()) {
                        $customRows = DB::table(config('items.table_prefix') . 'addon_product_option')
                            ->select('addon_option_id', 'price', 'is_default')
                            ->where('addon_product_id', $this->pivot->id)
                            ->get();
                    }

                    $customRows = $customRows->keyBy('addon_option_id');

                    $options = $this->resource->relationLoaded('addonOptions')
                        ? $this->resource->addonOptions
                        : $this->addonOptions()->with(['media', 'translations'])->get();

                    if ($customRows->isNotEmpty()) {
                        $options = $options
                            ->whereIn('id', $customRows->keys()->all())
                            ->values();
                    }

                    return $options->map(function ($option) use ($customRows) {
                        $customRow = $customRows->get($option->id);

                        if ($customRow && $customRow->price !== null) {
                            $option->price = $customRow->price;
                        }

                        if ($customRow && $customRow->is_default !== null) {
                            $option->is_default = (bool) $customRow->is_default;
                        }

                        return new AddonOptionResource($option);
                    });
                },

                // الحالة الثانية: هذا الريسورس معروض بشكل مستقل
                function () {
                    return AddonOptionResource::collection(
                        $this->resource->relationLoaded('addonOptions')
                            ? $this->resource->addonOptions
                            : collect()
                    );
                }
            )
            // ---== نهاية الكود المُصحح لـ options ==---
        ];
    }

    public static function allowedIncludes(): array
    {
        return [];
    }
}
