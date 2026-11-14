<?php

namespace Modules\Wholesale\Enums;

use Modules\Items\Enums\PaymentMethod;
use Modules\Wholesale\Entities\Merchant;

/**
 * How a merchant pays a wholesale order. Credit (pay later, on the merchant's account) is offered
 * only to a merchant with credit enabled and a limit (MerchantCredit checks the room at checkout).
 */
enum PaymentTerms: string
{
    case CASH_ON_DELIVERY = 'cash_on_delivery';
    case BANK_TRANSFER = 'bank_transfer';
    case CREDIT = 'credit';

    public function getLabel(): string
    {
        return match ($this) {
            self::CASH_ON_DELIVERY => __('Cash on delivery'),
            self::BANK_TRANSFER => __('Bank transfer'),
            self::CREDIT => __('On credit'),
        };
    }

    /**
     * The order's payment method.
     */
    public function paymentMethod(): PaymentMethod
    {
        return match ($this) {
            self::CASH_ON_DELIVERY => PaymentMethod::CASH,
            self::BANK_TRANSFER => PaymentMethod::BANK_TRANSFER,
            self::CREDIT => PaymentMethod::ON_ACCOUNT,
        };
    }

    /**
     * What checkout offers every merchant.
     *
     * @return list<self>
     */
    public static function offered(): array
    {
        return [self::CASH_ON_DELIVERY, self::BANK_TRANSFER];
    }

    /**
     * What checkout offers this merchant: credit too when its credit is enabled with a limit.
     *
     * @return list<self>
     */
    public static function offeredTo(Merchant $merchant): array
    {
        return $merchant->credit_enabled && (float) $merchant->credit_limit > 0 ? [...self::offered(), self::CREDIT] : self::offered();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(?Merchant $merchant = null): array
    {
        return array_map(fn (self $terms) => ['value' => $terms->value, 'label' => $terms->getLabel()], $merchant ? self::offeredTo($merchant) : self::offered());
    }
}
