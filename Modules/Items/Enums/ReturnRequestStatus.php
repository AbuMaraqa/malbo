<?php

namespace Modules\Items\Enums;

/**
 * Where a return or exchange request stands: requested, then approved or rejected; an approved one
 * is received (the items are back at MALBO), then completed (stock and order updated). Staff may
 * still reject an approved or received request (with a note: why). The buyer may cancel a request
 * nobody has handled yet. Rejected, cancelled and completed are final. The values are stored:
 * never rename a case's value.
 */
enum ReturnRequestStatus: string
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RECEIVED = 'received';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    private const TRANSITIONS = [
        'requested' => [self::APPROVED, self::REJECTED, self::CANCELLED],
        'approved' => [self::RECEIVED, self::REJECTED],
        'received' => [self::COMPLETED, self::REJECTED],
    ];

    public function getLabel(): string
    {
        return match ($this) {
            self::REQUESTED => __('Requested'),
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Rejected'),
            self::RECEIVED => __('Items received'),
            self::COMPLETED => __('Completed'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    /**
     * The admin Badge's tone.
     */
    public function tone(): string
    {
        return match ($this) {
            self::REQUESTED => 'warning',
            self::APPROVED, self::RECEIVED => 'info',
            self::REJECTED => 'danger',
            self::COMPLETED => 'success',
            self::CANCELLED => 'neutral',
        };
    }

    /**
     * Statuses that take nothing from an order: its units may be asked for again.
     *
     * @return list<self>
     */
    public static function void(): array
    {
        return [self::REJECTED, self::CANCELLED];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->value] ?? [], true);
    }

    /**
     * Still being handled: an order has at most one open request.
     */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::REQUESTED, self::APPROVED, self::RECEIVED];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->getLabel()], self::cases());
    }
}
