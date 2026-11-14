<?php

namespace Modules\Items\Enums;

/**
 * Why a product's stock changed.
 */
enum StockMovementType: string
{
    case SALE = 'sale';
    case CANCEL = 'cancel';
    case RETURN = 'return';
    case ADJUST = 'adjust';
    case INCOMING = 'incoming';

    public function getLabel(): string
    {
        return match ($this) {
            self::SALE => __('Sale'),
            self::CANCEL => __('Cancellation'),
            self::RETURN => __('Return'),
            self::ADJUST => __('Adjustment'),
            self::INCOMING => __('Incoming goods'),
        };
    }

    /**
     * The admin Badge's tone.
     */
    public function tone(): string
    {
        return match ($this) {
            self::SALE => 'neutral',
            self::CANCEL, self::RETURN => 'info',
            self::ADJUST => 'warning',
            self::INCOMING => 'success',
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
