<?php

namespace Modules\Items\Notifications;

use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Entities\Product;
use Throwable;

/**
 * Tells the staff who manage products (permission `Product Update`, through a role or directly)
 * that a product's stock fell to its low-stock threshold. Sent by InventoryService after the commit,
 * once the response is out. Never fatal: the stock has changed whether or not the message can go.
 */
class LowStockAlert
{
    public static function send(Product $product): void
    {
        $message = __(':name has :count left.', [
            'name' => $product->name ?? $product->sku ?? '#'.$product->id,
            'count' => (int) $product->stock_quantity,
        ]);

        try {
            $recipients = User::permission('Product Update')->get();
        } catch (Throwable $e) {
            // E.g. the permission was removed. The sale already happened: never fail it now.
            Log::warning('Low stock alert not sent: '.$e->getMessage(), ['product' => $product->id]);

            return;
        }

        foreach ($recipients as $user) {
            try {
                // One recipient failing (e.g. no push credentials) never stops the others.
                $user->notify(new GeneralNotification(title: __('Low stock'), message: $message, icon: 'box', color: 'text-warning'));
            } catch (Throwable $e) {
                Log::warning('Low stock alert not sent: '.$e->getMessage(), ['product' => $product->id, 'user' => $user->id]);
            }
        }
    }
}
