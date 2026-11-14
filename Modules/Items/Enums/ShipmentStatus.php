<?php

namespace Modules\Items\Enums;

/**
 * Where a shipment stands: pending (made, waiting for the courier), picked up, in transit, then
 * delivered or returned to the sender; a shipment the courier never took may be cancelled.
 * Delivered, returned to sender and cancelled are final. The values are stored: never rename one.
 */
enum ShipmentStatus: string
{
    case PENDING = 'pending';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT = 'in_transit';
    case DELIVERED = 'delivered';
    case RETURNED_TO_SENDER = 'returned_to_sender';
    case CANCELLED = 'cancelled';

    private const TRANSITIONS = [
        'pending' => [self::PICKED_UP, self::IN_TRANSIT, self::DELIVERED, self::CANCELLED],
        'picked_up' => [self::IN_TRANSIT, self::DELIVERED, self::RETURNED_TO_SENDER],
        'in_transit' => [self::DELIVERED, self::RETURNED_TO_SENDER],
    ];

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::PICKED_UP => __('Picked up'),
            self::IN_TRANSIT => __('In transit'),
            self::DELIVERED => __('Delivered'),
            self::RETURNED_TO_SENDER => __('Returned to sender'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    /**
     * The admin Badge's tone.
     */
    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PICKED_UP, self::IN_TRANSIT => 'info',
            self::DELIVERED => 'success',
            self::RETURNED_TO_SENDER => 'danger',
            self::CANCELLED => 'neutral',
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->value] ?? [], true);
    }

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return self::TRANSITIONS[$this->value] ?? [];
    }

    /**
     * Where the order goes when the shipment reaches this status, or null when it stays.
     */
    public function orderStatus(): ?OrderStatus
    {
        return match ($this) {
            self::PICKED_UP, self::IN_TRANSIT => OrderStatus::OUT_FOR_DELIVERY,
            self::DELIVERED => OrderStatus::DELIVERED,
            self::RETURNED_TO_SENDER => OrderStatus::RETURNED,
            self::PENDING, self::CANCELLED => null,
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
