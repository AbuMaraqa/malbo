<?php

namespace Tests\Unit\Items;

use Modules\Items\Enums\OrderStatus as S;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * MALBO's order flow: new, awaiting confirmation (cash on delivery), confirmed, preparing, with the
 * courier, delivered, then exchanged or returned. Cancelled, rejected, returned and exchanged are final.
 */
class OrderStatusTest extends TestCase
{
    private const ALLOWED = [
        'PENDING' => ['AWAITING_CONFIRMATION', 'CONFIRMED', 'REJECTED', 'CANCELLED'],
        'AWAITING_CONFIRMATION' => ['CONFIRMED', 'REJECTED', 'CANCELLED'],
        'CONFIRMED' => ['PREPARING', 'CANCELLED'],
        'PREPARING' => ['OUT_FOR_DELIVERY', 'READY_FOR_PICKUP', 'CANCELLED'],
        'READY_FOR_PICKUP' => ['OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED'],
        'OUT_FOR_DELIVERY' => ['DELIVERED', 'RETURNED'],
        'DELIVERED' => ['RETURNED', 'EXCHANGED'],
        'CANCELLED' => [],
        'REJECTED' => [],
        'RETURNED' => [],
        'EXCHANGED' => [],
    ];

    /**
     * @return iterable<string, array{S, S, bool}>
     */
    public static function transitions(): iterable
    {
        foreach (self::ALLOWED as $from => $targets) {
            foreach (S::cases() as $to) {
                $allowed = $to->name === $from || in_array($to->name, $targets, true);

                yield "{$from} -> {$to->name}" => [constant(S::class.'::'.$from), $to, $allowed];
            }
        }
    }

    #[DataProvider('transitions')]
    public function test_the_transition_table(S $from, S $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_every_status_is_in_the_table(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(self::ALLOWED), array_map(fn (S $s) => $s->name, S::cases()));
    }

    public function test_final_statuses_cannot_move(): void
    {
        $final = array_values(array_filter(S::cases(), fn (S $s) => $s->isFinal()));

        $this->assertEqualsCanonicalizing([S::CANCELLED, S::REJECTED, S::RETURNED, S::EXCHANGED], $final);
    }

    public function test_released_stock_statuses(): void
    {
        $released = array_values(array_filter(S::cases(), fn (S $s) => $s->releasesStock()));

        $this->assertEqualsCanonicalizing([S::CANCELLED, S::REJECTED, S::RETURNED], $released);
    }

    public function test_the_malbo_labels(): void
    {
        app()->setLocale('en');

        $this->assertSame('New', S::PENDING->getLabel());
        $this->assertSame('Awaiting confirmation', S::AWAITING_CONFIRMATION->getLabel());
        $this->assertSame('With courier', S::OUT_FOR_DELIVERY->getLabel());
        $this->assertSame('Exchanged', S::EXCHANGED->getLabel());
        $this->assertSame(10, S::AWAITING_CONFIRMATION->value);
        $this->assertSame(11, S::EXCHANGED->value);
    }
}
