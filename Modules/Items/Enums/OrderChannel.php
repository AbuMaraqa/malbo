<?php

namespace Modules\Items\Enums;

/**
 * Where an order comes from: the store (buyers) or the wholesale portal (approved merchants).
 */
enum OrderChannel: string
{
    case B2C = 'b2c';
    case B2B = 'b2b';

    public function getLabel(): string
    {
        return match ($this) {
            self::B2C => __('Store'),
            self::B2B => __('Wholesale'),
        };
    }
}
