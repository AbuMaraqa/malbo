<?php

namespace Modules\Items\Enums;

/**
 * MALBO's order flow: new, awaiting confirmation (a cash-on-delivery order MALBO confirms first),
 * confirmed, preparing, with the courier, delivered, then exchanged or returned. The integer values
 * are stored on orders: never renumber a case.
 */
enum OrderStatus: int
{
    case PENDING = 1;                // جديد
    case CONFIRMED = 2;              // مؤكد
    case PREPARING = 3;              // قيد التجهيز
    case READY_FOR_PICKUP = 4;       // جاهز للاستلام
    case OUT_FOR_DELIVERY = 5;       // مع شركة التوصيل
    case DELIVERED = 6;              // تم التسليم
    case CANCELLED = 7;              // ملغي
    case REJECTED = 8;               // مرفوض
    case RETURNED = 9;               // مرتجع
    case AWAITING_CONFIRMATION = 10; // بانتظار التأكيد
    case EXCHANGED = 11;             // استبدال

    /**
     * Where each status may go next (staying put is always allowed). Final statuses go nowhere.
     */
    private const TRANSITIONS = [
        self::PENDING->value => [self::AWAITING_CONFIRMATION, self::CONFIRMED, self::REJECTED, self::CANCELLED],
        self::AWAITING_CONFIRMATION->value => [self::CONFIRMED, self::REJECTED, self::CANCELLED],
        self::CONFIRMED->value => [self::PREPARING, self::CANCELLED],
        self::PREPARING->value => [self::OUT_FOR_DELIVERY, self::READY_FOR_PICKUP, self::CANCELLED],
        self::READY_FOR_PICKUP->value => [self::OUT_FOR_DELIVERY, self::DELIVERED, self::CANCELLED],
        self::OUT_FOR_DELIVERY->value => [self::DELIVERED, self::RETURNED],
        self::DELIVERED->value => [self::RETURNED, self::EXCHANGED],
    ];

    /**
     * Provides a translatable label for each status.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => __('New'),
            self::AWAITING_CONFIRMATION => __('Awaiting confirmation'),
            self::CONFIRMED => __('Confirmed'),
            self::PREPARING => __('Preparing'),
            self::READY_FOR_PICKUP => __('Ready for Pickup'),
            self::OUT_FOR_DELIVERY => __('With courier'),
            self::DELIVERED => __('Delivered'),
            self::CANCELLED => __('Cancelled'),
            self::REJECTED => __('Rejected'),
            self::RETURNED => __('Returned'),
            self::EXCHANGED => __('Exchanged'),
        };
    }

    /**
     * Provides a color for each status.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::PENDING, self::AWAITING_CONFIRMATION => 'warning',
            self::CONFIRMED, self::EXCHANGED => 'info',
            self::PREPARING => 'primary',
            self::OUT_FOR_DELIVERY => 'secondary',
            self::DELIVERED, self::READY_FOR_PICKUP => 'success',
            self::CANCELLED, self::REJECTED, self::RETURNED => 'danger',
        };
    }

    /**
     * Value => label, for select fields.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->toArray();
    }

    /**
     * The order is closed: its status can no longer change.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::CANCELLED, self::REJECTED, self::RETURNED, self::EXCHANGED], true);
    }

    /**
     * Reaching this status gives the order's stock back.
     */
    public function releasesStock(): bool
    {
        return in_array($this, [self::CANCELLED, self::REJECTED, self::RETURNED], true);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return $this === $newStatus || in_array($newStatus, self::TRANSITIONS[$this->value] ?? [], true);
    }
}
