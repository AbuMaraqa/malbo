<?php

namespace Modules\Items\Enums;

/**
 * Why the buyer sends items back. The values are stored.
 */
enum ReturnReason: string
{
    case WRONG_SIZE = 'wrong_size';
    case DEFECTIVE = 'defective';
    case NOT_AS_DESCRIBED = 'not_as_described';
    case CHANGED_MIND = 'changed_mind';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WRONG_SIZE => __('Wrong size'),
            self::DEFECTIVE => __('Defective'),
            self::NOT_AS_DESCRIBED => __('Not as described'),
            self::CHANGED_MIND => __('Changed my mind'),
            self::OTHER => __('Other'),
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
