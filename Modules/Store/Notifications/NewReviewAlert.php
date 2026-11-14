<?php

namespace Modules\Store\Notifications;

use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Store\Entities\Review;
use Throwable;

/**
 * Tells the staff who moderate reviews (permission `Review Update`) that one waits for them. Sent
 * once the response is out; never fatal.
 */
class NewReviewAlert
{
    public static function send(Review $review): void
    {
        try {
            $review->loadMissing('product.translations');
            $recipients = User::permission('Review Update')->get();
            $url = route('reviews.edit', ['locale' => app()->getLocale(), 'review' => $review->id]);
            $message = __('A :rating-star review of :product waits for you.', ['rating' => $review->rating, 'product' => (string) $review->product?->name]);
        } catch (Throwable $e) {
            Log::warning('New review alert not sent: '.$e->getMessage(), ['review' => $review->id]);

            return;
        }

        foreach ($recipients as $user) {
            try {
                $user->notify(new GeneralNotification(title: __('New review'), message: $message, url: $url, icon: 'star', color: 'text-warning'));
            } catch (Throwable $e) {
                Log::warning('New review alert not sent: '.$e->getMessage(), ['review' => $review->id, 'user' => $user->id]);
            }
        }
    }
}
