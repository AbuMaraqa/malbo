<?php

namespace Modules\Store\Console;

use Illuminate\Console\Command;
use Modules\Store\Services\CartReminderSender;

/**
 * Sends the abandoned-cart reminders that are due. Scheduled every 10 minutes (StoreServiceProvider).
 */
class SendCartReminders extends Command
{
    protected $signature = 'store:send-cart-reminders';

    protected $description = 'Send the abandoned-cart reminders that are due';

    public function handle(CartReminderSender $sender): int
    {
        $sent = $sender->sendDue();

        $this->info("Cart reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
