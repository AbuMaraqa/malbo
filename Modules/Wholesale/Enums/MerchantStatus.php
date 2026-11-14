<?php

namespace Modules\Wholesale\Enums;

/**
 * Where a merchant's application stands. Only an approved merchant sees wholesale prices and buys.
 * pending → approved | rejected; approved ↔ suspended. A rejected application stays rejected.
 */
enum MerchantStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => __('Pending review'),
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Rejected'),
            self::SUSPENDED => __('Suspended'),
        };
    }

    /** The admin badge's tone. */
    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::SUSPENDED => 'neutral',
        };
    }

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::PENDING => [self::APPROVED, self::REJECTED],
            self::APPROVED => [self::SUSPENDED],
            self::SUSPENDED => [self::APPROVED],
            self::REJECTED => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /**
     * Saying no to a merchant, or stopping one, needs a reason they can be told.
     */
    public function needsNote(): bool
    {
        return in_array($this, [self::REJECTED, self::SUSPENDED], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->getLabel()], self::cases());
    }
}
