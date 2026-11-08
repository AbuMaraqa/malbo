<?php

namespace Modules\Store\Transformers\V1;

use Illuminate\Http\Request;
use Modules\Store\Support\PageOutline;
use Modules\Store\Support\PagePlaceholders;

/**
 * One store page with its body as HTML (the store's details filled in). The FAQ also comes
 * as questions and answers, for an accordion.
 */
class PageDetailResource extends PageResource
{
    public function toArray(Request $request): array
    {
        $body = PagePlaceholders::body($this->body);

        return [
            ...parent::toArray($request),
            'body' => $body === '' ? null : $body,
            'faq' => $this->key === 'faq' && $body !== '' ? PageOutline::questions($body) : null,
        ];
    }
}
