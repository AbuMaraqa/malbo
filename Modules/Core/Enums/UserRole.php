<?php

namespace Modules\Core\Enums;


enum UserRole: string
{
    case SUPER_ADMIN = 'Super Admin';
    case USER = 'User';
    case CUSTOMER = 'Customer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => __('Super Admin'),
            self::USER => __('User'),
            self::CUSTOMER => __('Customer'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            default => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'heroicon-o-shield-check',
            self::USER, self::CUSTOMER => 'heroicon-o-user',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
