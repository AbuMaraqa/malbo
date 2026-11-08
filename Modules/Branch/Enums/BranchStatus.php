<?php

namespace Modules\Branch\Enums;

enum BranchStatus: int
{
    case OPEN = 1;
    case CLOSED = 2;


    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => __('Open'),
            self::CLOSED => __('Closed'),
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::OPEN => 'success',
            self::CLOSED => 'danger',
        };
    }
}
