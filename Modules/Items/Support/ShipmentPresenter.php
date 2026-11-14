<?php

namespace Modules\Items\Support;

use Modules\Items\Entities\Order;
use Modules\Items\Entities\Shipment;
use Modules\Items\Entities\ShipmentEvent;
use Modules\Items\Enums\ShipmentStatus;
use Modules\Items\Services\Couriers\CourierRegistry;
use Modules\Items\Services\ShipmentService;

/**
 * Shipments as the admin pages show them: explicit fields, so a model never leaks a column.
 */
final class ShipmentPresenter
{
    public function __construct(private readonly CourierRegistry $couriers) {}

    /**
     * The order page's shipment card: the active shipment (with its history and where it may go
     * next), whether a new one can be made, and the couriers to choose from.
     *
     * @return array{current: ?array<string, mixed>, can_create: bool, couriers: list<array{value: string, label: string}>}
     */
    public function card(?Shipment $shipment, Order $order): array
    {
        return [
            'current' => $shipment ? [
                'id' => $shipment->id,
                'courier' => $this->couriers->name($shipment->courier),
                'tracking_number' => $shipment->tracking_number,
                'status' => self::status($shipment->status),
                'cost' => $shipment->cost,
                'notes' => $shipment->notes,
                'shipped_at' => $shipment->shipped_at?->format('Y-m-d H:i'),
                'delivered_at' => $shipment->delivered_at?->format('Y-m-d H:i'),
                'created_at' => $shipment->created_at?->format('Y-m-d H:i'),
                'created_by' => $shipment->createdBy?->name,
                'events' => $shipment->events->map(fn (ShipmentEvent $event) => [
                    'id' => $event->id,
                    'status' => $event->status->getLabel(),
                    'tone' => $event->status->tone(),
                    'note' => $event->note,
                    'at' => $event->at?->format('Y-m-d H:i'),
                    'by' => $event->createdBy?->name,
                ])->values()->all(),
                'transitions' => array_map(fn (ShipmentStatus $status) => ['value' => $status->value, 'label' => $status->getLabel()], $shipment->status->next()),
            ] : null,
            'can_create' => $shipment === null && ShipmentService::shippable($order),
            'couriers' => $this->couriers->options(),
        ];
    }

    /**
     * A row of the shipments table.
     *
     * @return array<string, mixed>
     */
    public function row(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'order_number' => $shipment->order?->order_number,
            'order_deleted' => $shipment->order?->trashed() ?? true,
            'customer' => $shipment->order?->customer_name ?: $shipment->order?->user?->name,
            'courier' => $this->couriers->name($shipment->courier),
            'tracking_number' => $shipment->tracking_number,
            'status' => $shipment->status->value,
            'status_label' => $shipment->status->getLabel(),
            'status_tone' => $shipment->status->tone(),
            'cost' => $shipment->cost,
            'shipped_at' => $shipment->shipped_at?->format('Y-m-d H:i'),
            'delivered_at' => $shipment->delivered_at?->format('Y-m-d H:i'),
            'created_at' => $shipment->created_at?->format('Y-m-d H:i'),
            'created_by' => $shipment->createdBy?->name,
        ];
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private static function status(ShipmentStatus $status): array
    {
        return ['value' => $status->value, 'label' => $status->getLabel(), 'tone' => $status->tone()];
    }
}
