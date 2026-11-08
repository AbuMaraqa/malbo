<?php

namespace Modules\Items\Enums;


enum AddonType: int
{
    case RADIO          = 1;
    case CHECKBOX         = 2;

    public static function array(): array
    {
        return [
            self::RADIO,
            self::CHECKBOX
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->label()])
            ->toArray();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::CHECKBOX         => __('Checkbox'),
            self::RADIO          => __('Radio'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CHECKBOX       => 'primary',
            self::RADIO          => 'primary',
        };
    }
}
