<?php

namespace Modules\Store\Enums;

/**
 * A review waits for staff (pending), then shows on the product page (approved) or not (rejected).
 * A buyer who edits their review sends it back to pending.
 */
enum ReviewStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Rejected'),
        };
    }
}
