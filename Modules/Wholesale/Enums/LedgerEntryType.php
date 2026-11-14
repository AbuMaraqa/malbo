<?php

namespace Modules\Wholesale\Enums;

/**
 * What a merchant's ledger entry is. Invoices and credit notes are posted by the platform (a B2B
 * order delivered, a return); payments and adjustments are recorded by staff.
 */
enum LedgerEntryType: string
{
    case INVOICE = 'invoice';
    case PAYMENT = 'payment';
    case CREDIT_NOTE = 'credit_note';
    case ADJUSTMENT = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::INVOICE => __('Invoice'),
            self::PAYMENT => __('Payment received'),
            self::CREDIT_NOTE => __('Credit note'),
            self::ADJUSTMENT => __('Account adjustment'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::INVOICE => 'neutral',
            self::PAYMENT => 'success',
            self::CREDIT_NOTE => 'info',
            self::ADJUSTMENT => 'warning',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->getLabel()], self::cases());
    }
}
