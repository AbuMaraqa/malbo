<?php

namespace Modules\Store\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\Store\Entities\Review;
use Modules\Store\Enums\ReviewStatus;
use Modules\Store\Repositories\Contracts\ReviewRepositoryInterface;
use Modules\Store\Services\ReviewService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Review moderation: what buyers wrote, approve or reject it, reply in the store's name, remove a
 * photo. Only approved reviews show on the product page.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
        private readonly ReviewService $reviewService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Store/Review/Index', [
            'table' => AdminTable::for($this->reviews->tableQuery())
                ->filters(
                    AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                        $term = '%'.addcslashes(trim((string) $value), '%_\\').'%';

                        $query->where(fn (Builder $match) => $match
                            ->whereLike('title', $term, caseSensitive: false)
                            ->orWhereLike('body', $term, caseSensitive: false)
                            ->orWhereHas('product', fn (Builder $product) => $product->withTrashed()
                                ->whereHas('translations', fn (Builder $name) => $name->whereLike('name', $term, caseSensitive: false))));
                    }),
                    AdminFilter::exact('status'),
                    AdminFilter::exact('rating'),
                    AdminFilter::exact('product', 'product_id'),
                    AllowedFilter::callback('has_photos', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                        ? $query->whereHas('media')
                        : $query->whereDoesntHave('media')),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts('created_at', 'rating', 'id')
                ->defaultSort('-created_at')
                ->rows(fn (Review $review) => [
                    'id' => $review->id,
                    'product' => (string) $review->product?->name,
                    'author' => (string) $review->user?->name,
                    'rating' => (int) $review->rating,
                    'title' => $review->title,
                    'body' => Str::limit((string) $review->body, 200),
                    'status' => $review->status->value,
                    'status_label' => $review->status->label(),
                    'photos' => $review->getMedia('photos')->count(),
                    'replied' => $review->staff_reply !== null,
                    'created' => $review->created_at?->diffForHumans(),
                    'created_at' => $review->created_at?->translatedFormat('j F Y, H:i'),
                ]),
            'products' => $this->reviews->reviewedProducts(),
            'statuses' => array_map(fn (ReviewStatus $status) => ['value' => $status->value, 'label' => $status->label()], ReviewStatus::cases()),
        ]);
    }

    public function edit(int $review): Response
    {
        $model = $this->find($review);

        return Inertia::render('Store/Review/Edit', [
            'review' => [
                'id' => $model->id,
                'product' => [
                    'name' => (string) $model->product?->name,
                    'url' => $model->product && ! $model->product->trashed() ? route('store.product', ['slug' => $model->product->slug]) : null,
                ],
                'author' => (string) $model->user?->name,
                'email' => $model->user?->email,
                'rating' => (int) $model->rating,
                'title' => $model->title,
                'body' => $model->body,
                'status' => $model->status->value,
                'status_label' => $model->status->label(),
                'staff_reply' => $model->staff_reply,
                'replied_at' => $model->replied_at?->translatedFormat('j F Y, H:i'),
                'order' => $model->order ? ['number' => $model->order->order_number, 'url' => route('orders.edit', ['order' => $model->order->id])] : null,
                'photos' => $model->getMedia('photos')->map(fn (Media $media) => ['id' => $media->id, 'url' => $media->getUrl()])->values()->all(),
                'created_at' => $model->created_at?->translatedFormat('j F Y, H:i'),
            ],
        ]);
    }

    public function status(Request $request, int $review): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::enum(ReviewStatus::class)]])['status'];

        $this->reviewService->moderate($this->find($review), ReviewStatus::from($status));

        return back()->with('success', __('Saved Successfully'));
    }

    public function reply(Request $request, int $review): RedirectResponse
    {
        $reply = $request->validate(['staff_reply' => ['nullable', 'string', 'max:2000']])['staff_reply'] ?? null;

        $this->reviewService->reply($this->find($review), $reply);

        return back()->with('success', __('Saved Successfully'));
    }

    public function destroyPhoto(int $review, int $media): RedirectResponse
    {
        $this->reviewService->deletePhoto($this->find($review), $media);

        return back()->with('success', __('Photo removed.'));
    }

    private function find(int $id): Review
    {
        $review = $this->reviews->findForModeration($id);
        abort_if($review === null, 404);

        return $review;
    }
}
