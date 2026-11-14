<?php

namespace Modules\Core\Enums;


enum UserRole: string
{
    case SUPER_ADMIN = 'Super Admin';
    case USER = 'User';
    case CUSTOMER = 'Customer';
    // A wholesale buyer (the Wholesale module): like a customer, never staff.
    case MERCHANT = 'Merchant';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => __('Super Admin'),
            self::USER => __('User'),
            self::CUSTOMER => __('Customer'),
            self::MERCHANT => __('Merchant'),
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
            self::USER, self::CUSTOMER, self::MERCHANT => 'heroicon-o-user',
        };
    }

    /**
     * The roles of buyers: a user with only these is not staff and stays out of the admin.
     *
     * @return list<string>
     */
    public static function nonStaff(): array
    {
        return [self::CUSTOMER->value, self::MERCHANT->value];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }
}
