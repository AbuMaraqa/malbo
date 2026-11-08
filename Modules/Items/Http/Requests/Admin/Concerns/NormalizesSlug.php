<?php

namespace Modules\Items\Http\Requests\Admin\Concerns;

use Illuminate\Support\Str;

/**
 * The slug the server keeps: the typed one in URL form, or one made from the name when it is left
 * empty. Str::slug also transliterates an Arabic name, which the browser cannot do.
 */
trait NormalizesSlug
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
        ]);
    }
}
