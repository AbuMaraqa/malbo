<?php

namespace Modules\Core\Enums;


enum EmailStatus: int
{
    case PENDING    = 0;
    case SENT       = 1;
    case FAILED     = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            static::PENDING    => __('Pending'),
            static::SENT       => __('Sent'),
            static::FAILED     => __('Failed'),
        };
    }
}
