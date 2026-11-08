<?php

namespace Modules\Store\Transformers\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Store\Entities\Page;

/**
 * A published store page in the current locale, as listed in the app's help menu.
 * group: "policies" for shipping, returns, privacy and terms; "about" for the rest.
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'slug' => $this->slug,
            'title' => $this->title,
            'meta_description' => $this->meta_description,
            'group' => in_array($this->key, Page::POLICIES, true) ? 'policies' : 'about',
            'show_in_footer' => $this->show_in_footer,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
