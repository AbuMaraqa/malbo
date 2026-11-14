<?php

namespace Modules\Items\Notifications;

use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Entities\Order;
use Throwable;

/**
 * Tells the staff who see orders (permission `Order View List`, through a role or directly) that a
 * buyer placed an order on the storefront. Sent after the commit, once the response is out. Never fatal: the order is placed
 * whether or not the message can go.
 */
class NewOrderAlert
{
    public static function send(Order $order): void
    {
        $message = __('Order #:number from :name, :total.', [
            'number' => $order->order_number,
            'name' => $order->customer_name ?: $order->contact_phone,
            'total' => number_format((float) $order->total, 2).' ₪',
        ]);

        try {
            $recipients = User::permission('Order View List')->get();
            $url = route('orders.edit', ['locale' => app()->getLocale(), 'order' => $order->id]);
        } catch (Throwable $e) {
            Log::warning('New order alert not sent: '.$e->getMessage(), ['order' => $order->id]);

            return;
        }

        foreach ($recipients as $user) {
            try {
                $user->notify(new GeneralNotification(title: __('New order'), message: $message, url: $url, icon: 'bag', color: 'text-success'));
            } catch (Throwable $e) {
                Log::warning('New order alert not sent: '.$e->getMessage(), ['order' => $order->id, 'user' => $user->id]);
            }
        }
    }
}
