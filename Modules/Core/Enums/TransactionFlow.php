<?php

namespace Modules\Core\Enums;


enum TransactionFlow: string
{
    case INCOME = 'INCOME';     // تدفق إلى الداخل (دخل)
    case EXPENSE = 'EXPENSE';   // تدفق إلى الخارج (مصروف/إرجاع)

    public function getLabel(): ?string
    {
        return match ($this) {
            self::INCOME => __('Income'),             // <== تم التصحيح: يجب أن تكون 'دخل'
            self::EXPENSE => __('EXPENSE'),  // <== تم التصحيح: يجب أن تكون 'مصروف / إرجاع'
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::INCOME => 'success',
            self::EXPENSE => 'danger',
        };
    }

    public function isIncome(): bool
    {
        return $this === self::INCOME;
    }

    public function isExpense(): bool
    {
        return $this === self::EXPENSE;
    }
}
