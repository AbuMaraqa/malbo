<?php

namespace Modules\Wholesale\Enums;

/**
 * How a merchant paid (a payment entry).
 */
enum LedgerPaymentMethod: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CHEQUE = 'cheque';

    public function getLabel(): string
    {
        return match ($this) {
            self::CASH => __('Cash'),
            self::BANK_TRANSFER => __('Bank transfer'),
            self::CHEQUE => __('Cheque'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $method) => ['value' => $method->value, 'label' => $method->getLabel()], self::cases());
    }
}
