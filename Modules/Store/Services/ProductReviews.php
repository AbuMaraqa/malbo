<?php

namespace Modules\Store\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Product;
use Modules\Store\Entities\Review;
use Modules\Store\Repositories\Contracts\ReviewRepositoryInterface;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Reviews as the storefront shows them: a product's approved reviews (paginated), its rating
 * summary (cached until a review of it changes, ReviewService), the review form's state for the
 * visitor, and an account's own reviews. Texts are sent as they were typed: Vue escapes them.
 */
class ProductReviews
{
    public const PER_PAGE = 6;

    public const PAGE_NAME = 'reviews_page';

    private const SUMMARY_TTL = 86400;

    public function __construct(private readonly ReviewRepositoryInterface $reviews) {}

    /**
     * @return array{average: ?float, count: int}
     */
    public function summary(int $productId): array
    {
        return Cache::remember(ReviewService::summaryKey($productId), self::SUMMARY_TTL, fn () => $this->reviews->summary($productId));
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, total: int}}
     */
    public function page(int $productId): array
    {
        $page = $this->reviews->approvedForProduct($productId, self::PER_PAGE, self::PAGE_NAME);

        return [
            'data' => $page->getCollection()->map(fn (Review $review) => self::row($review))->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ];
    }

    /**
     * What the product page's review form shows this visitor.
     *
     * @return array{signed_in: bool, eligible: bool, login_url: string, submit_url: string, review: ?array<string, mixed>}
     */
    public function form(?User $user, Product $product): array
    {
        $own = $user ? $this->reviews->findForUser((int) $user->id, (int) $product->id) : null;

        return [
            'signed_in' => $user !== null,
            'eligible' => $user !== null && $this->reviews->eligibleOrderId((int) $user->id, (int) $product->id) !== null,
            // A path on this site: the sign-in page only keeps those, and brings the buyer back here.
            'login_url' => route('store.account.login', ['back' => route('store.product', ['slug' => $product->slug], false).'#reviews']),
            'submit_url' => route('store.reviews.store', ['slug' => $product->slug]),
            'review' => $own ? [...self::row($own), 'status' => $own->status->value, 'status_label' => $own->status->label()] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        return $this->reviews->forUser($userId)->map(fn (Review $review) => [
            ...self::row($review),
            'status' => $review->status->value,
            'status_label' => $review->status->label(),
            'product' => [
                'name' => (string) $review->product?->name,
                'image' => $review->product?->getFirstMediaUrl('product') ?: null,
                'url' => $review->product && ! $review->product->trashed() ? route('store.product', ['slug' => $review->product->slug]).'#reviews' : null,
            ],
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'author' => self::author((string) $review->user?->name),
            // Only an account with a delivered order of the product can write one (ReviewService).
            'verified' => true,
            'date' => $review->created_at?->translatedFormat('j F Y'),
            'photos' => $review->getMedia('photos')->map(fn (Media $media) => ['id' => $media->id, 'url' => $media->getUrl()])->values()->all(),
            'reply' => $review->staff_reply,
        ];
    }

    /**
     * "Samir Haddad" shows as "Samir H.": enough to be real, not enough to find.
     */
    private static function author(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return __('A buyer');
        }

        return count($parts) === 1 ? $parts[0] : $parts[0].' '.mb_substr(end($parts), 0, 1).'.';
    }
}
