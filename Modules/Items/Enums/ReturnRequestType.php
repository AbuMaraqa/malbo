<?php

namespace Modules\Items\Enums;

/**
 * A return gives the items back (a refund is recorded); an exchange gives other products for them.
 */
enum ReturnRequestType: string
{
    case RETURN = 'return';
    case EXCHANGE = 'exchange';

    public function getLabel(): string
    {
        return match ($this) {
            self::RETURN => __('Return'),
            self::EXCHANGE => __('Exchange'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->getLabel()], self::cases());
    }
}
