<?php

namespace Modules\Items\Enums;


enum DeliveryType: int
{
    case DELIVERY = 1;
    case PICKUP = 2;

    case IN_STORE = 3;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DELIVERY => __('Delivery'),
            self::PICKUP => __('Pickup'),
            self::IN_STORE  => __('In Store')
        };
    }
}
