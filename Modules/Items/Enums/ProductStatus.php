<?php

namespace Modules\Items\Enums;

enum ProductStatus : int
{
    case DRAFT = 1;
    case PUBLISHED = 2;
    case ARCHIVED = 3;

    /**
     * What the product form and table offer: Archived stays in the enum but is not offered yet.
     *
     * @return array<int, string>
     */
    public static function formOptions(): array
    {
        return [
            self::DRAFT->value => __(self::DRAFT->label()),
            self::PUBLISHED->value => __(self::PUBLISHED->label()),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PUBLISHED => 'success',
            self::ARCHIVED => 'danger',
        };
    }
}
