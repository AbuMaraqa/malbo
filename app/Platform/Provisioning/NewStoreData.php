<?php

declare(strict_types=1);

namespace App\Platform\Provisioning;

use Illuminate\Http\UploadedFile;

/**
 * Everything needed to create a store. Filled by `stores:create` and by the public
 * sign-up. The color, font and logo are optional: the store defaults apply. `password` is plain text: it is hashed inside the store only.
 */
final readonly class NewStoreData
{
    public function __construct(
        public string $slug,
        public string $nameAr,
        public ?string $nameEn,
        public ?string $businessType,
        public string $ownerName,
        public string $ownerEmail,
        public ?string $ownerPhone,
        public string $password,
        public ?string $contactEmail,
        public ?string $primaryColor = null,
        public ?string $font = null,
        public ?UploadedFile $logo = null,
    ) {}
}
