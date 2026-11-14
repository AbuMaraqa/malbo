<?php

namespace Modules\Store\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\User;
use Modules\Core\Services\ImageService;
use Modules\Items\Entities\Product;
use Modules\Store\Entities\Review;
use Modules\Store\Enums\ReviewStatus;
use Modules\Store\Notifications\NewReviewAlert;
use Modules\Store\Repositories\Contracts\ReviewRepositoryInterface;
use Throwable;

/**
 * Writes reviews. A buyer may review a product once they have a delivered store order with it (or
 * one of its variants); posting again replaces their review and sends it back to staff (pending).
 * Photos are decoded and encoded again before anything is saved: what is not really an image is
 * refused, and no EXIF (camera, GPS) is kept. Staff approve, reject, reply and remove photos. Each
 * change refreshes the product's cached rating; staff hear of a new review after the response.
 */
class ReviewService
{
    private const FORMATS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly ReviewRepositoryInterface $reviews) {}

    /**
     * @param  array{rating: int|string, title?: ?string, body: string}  $data
     * @param  list<UploadedFile>  $photos  new photos replace the old ones; none keeps them
     *
     * @throws ValidationException on `review` (not a verified buyer) or `photos.N` (not an image)
     */
    public function submit(User $user, Product $product, array $data, array $photos = []): Review
    {
        $orderId = $this->reviews->eligibleOrderId((int) $user->id, (int) $product->id);

        if ($orderId === null) {
            throw ValidationException::withMessages(['review' => __('Only buyers who received this product can review it.')]);
        }

        $files = $this->reencode($photos);

        try {
            $write = fn () => DB::transaction(function () use ($user, $product, $data, $files, $orderId): Review {
                $attributes = [
                    'order_id' => $orderId,
                    'rating' => (int) $data['rating'],
                    'title' => self::plain($data['title'] ?? null),
                    'body' => (string) self::plain($data['body']),
                    'status' => ReviewStatus::PENDING,
                    'staff_reply' => null,
                    'replied_at' => null,
                ];

                $review = $this->reviews->lockForUser((int) $user->id, (int) $product->id);

                if ($review !== null) {
                    $review->update($attributes);
                } else {
                    /** @var Review $review */
                    $review = $this->reviews->create(['product_id' => $product->id, 'user_id' => $user->id, ...$attributes]);
                }

                if ($files !== []) {
                    $review->clearMediaCollection('photos');

                    foreach ($files as $file) {
                        $review->addMedia($file['path'])->usingFileName(Str::uuid().'.'.$file['format'])->toMediaCollection('photos');
                    }
                }

                return $review;
            });

            try {
                $review = $write();
            } catch (UniqueConstraintViolationException) {
                // Another request wrote this buyer's first review in the meantime: this one replaces it.
                $review = $write();
            }
        } finally {
            foreach ($files as $file) {
                if (is_file($file['path'])) {
                    @unlink($file['path']);
                }
            }
        }

        $this->forgetSummary((int) $product->id);
        // Once the response is out, never fatal (NewReviewAlert catches its own failures).
        defer(fn () => NewReviewAlert::send($review), always: true);

        return $review;
    }

    public function moderate(Review $review, ReviewStatus $status): void
    {
        $review->update(['status' => $status]);
        $this->forgetSummary((int) $review->product_id);
    }

    public function reply(Review $review, ?string $reply): void
    {
        $reply = self::plain($reply);

        $review->update(['staff_reply' => $reply, 'replied_at' => $reply === null ? null : Carbon::now()]);
    }

    /**
     * @throws ModelNotFoundException when the photo is not this review's
     */
    public function deletePhoto(Review $review, int $mediaId): void
    {
        $media = $review->getMedia('photos')->firstWhere('id', $mediaId);

        if ($media === null) {
            throw (new ModelNotFoundException)->setModel(Review::class, [$mediaId]);
        }

        $media->delete();
    }

    public static function summaryKey(int $productId): string
    {
        return 'store.reviews.summary.'.$productId;
    }

    private function forgetSummary(int $productId): void
    {
        Cache::forget(self::summaryKey($productId));

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => Cache::forget(self::summaryKey($productId)));
        }
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @return list<array{path: string, format: string}>
     *
     * @throws ValidationException on `photos.N`
     */
    private function reencode(array $photos): array
    {
        $files = [];

        foreach (array_values($photos) as $index => $photo) {
            try {
                $format = self::FORMATS[$photo->getMimeType()] ?? throw new \RuntimeException('not an image');
                $files[] = ['path' => ImageService::reencode((string) $photo->getRealPath(), $format), 'format' => $format];
            } catch (Throwable) {
                foreach ($files as $file) {
                    @unlink($file['path']);
                }

                throw ValidationException::withMessages(["photos.{$index}" => __('This photo could not be read. Use a JPG, PNG or WebP image.')]);
            }
        }

        return $files;
    }

    /**
     * Plain text as typed: line breaks kept, other control characters dropped, trimmed; null when empty.
     */
    private static function plain(?string $text): ?string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $text = trim((string) preg_replace('/[^\P{C}\n]/u', '', $text));

        return $text === '' ? null : $text;
    }
}
