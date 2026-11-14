<?php

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\User;
use Modules\Core\Notifications\GeneralNotification;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\OrderChannel;
use Modules\Items\Enums\OrderStatus;
use Modules\Store\Entities\Review;
use Modules\Store\Enums\ReviewStatus;
use Modules\Store\Services\ReviewService;
use Tests\Feature\Store\Concerns\MakesMarketing;
use Tests\Feature\Store\Concerns\MakesReviews;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Reviews from verified buyers: only an account whose store order with the product was delivered,
 * one per product, shown once staff approve it, with photos checked by their content.
 */
class ReviewTest extends StorefrontTestCase
{
    use MakesMarketing;
    use MakesReviews;
    use MakesShippingZones;

    private const PAGE = 'Store/Storefront/ProductPage';

    private Product $shirt;

    private Product $medium;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Notification::fake();
        $this->shirt = Product::factory()->create(['name' => 'قميص', 'slug' => 'shirt', 'base_price' => 100]);
        $this->medium = $this->variantOf($this->shirt, [$this->value($this->sizeAttribute(), 'M')]);
    }

    public function test_only_a_buyer_with_a_delivered_order_can_review(): void
    {
        $this->post($this->siteUrl('/ar/product/shirt/reviews'), $this->payload())->assertRedirect(route('store.account.login'));

        $buyer = $this->buyer();
        $this->actingAs($buyer)->from($this->siteUrl('/ar/product/shirt'))->post($this->siteUrl('/ar/product/shirt/reviews'), $this->payload())->assertSessionHasErrors('review');

        $this->orderFor($buyer, $this->medium, OrderStatus::OUT_FOR_DELIVERY);
        $this->submit($buyer)->assertSessionHasErrors('review');

        // Another buyer's delivered order does not count for this one.
        $this->orderFor($this->buyer('Other'), $this->medium);
        $this->submit($buyer)->assertSessionHasErrors('review');
        $this->assertFalse($this->props('/ar/product/shirt')['reviewForm']['eligible']);

        $order = $this->orderFor($buyer, $this->medium);
        $this->assertTrue($this->props('/ar/product/shirt')['reviewForm']['eligible']);
        $this->submit($buyer)->assertSessionHasNoErrors();

        $review = Review::query()->sole();
        $this->assertSame([$this->shirt->id, $buyer->id, $order->id, 4, ReviewStatus::PENDING], [$review->product_id, $review->user_id, $review->order_id, $review->rating, $review->status]);
    }

    public function test_wholesale_orders_do_not_count(): void
    {
        $merchant = $this->buyer();
        $this->orderFor($merchant, $this->medium, OrderStatus::DELIVERED, OrderChannel::B2B);

        $this->submit($merchant)->assertSessionHasErrors('review');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_one_review_per_product_and_user(): void
    {
        $buyer = $this->buyer();
        $this->orderFor($buyer, $this->medium);

        $this->submit($buyer, ['rating' => 2, 'body' => 'أول رأي'])->assertSessionHasNoErrors();
        Review::query()->update(['status' => ReviewStatus::APPROVED, 'staff_reply' => 'شكرًا']);

        $this->submit($buyer, ['rating' => 5, 'body' => 'غيّرت رأيي'])->assertSessionHasNoErrors();

        $review = Review::query()->sole();
        $this->assertSame([5, 'غيّرت رأيي', ReviewStatus::PENDING, null], [$review->rating, $review->body, $review->status, $review->staff_reply]);
    }

    public function test_only_approved_reviews_show(): void
    {
        $this->review($this->shirt, $this->buyer('Approved Buyer'), ['rating' => 4, 'body' => 'يظهر']);
        $this->review($this->shirt, null, ['status' => ReviewStatus::PENDING, 'body' => 'ينتظر']);
        $this->review($this->shirt, null, ['status' => ReviewStatus::REJECTED, 'body' => 'مرفوض']);

        $props = $this->props('/ar/product/shirt');

        $this->assertSame(['يظهر'], array_column($props['reviews']['data'], 'body'));
        $this->assertSame('Approved B.', $props['reviews']['data'][0]['author']);
        $this->assertTrue($props['reviews']['data'][0]['verified']);
        $this->assertEquals(['average' => 4.0, 'count' => 1], $props['reviewSummary']);
    }

    public function test_the_summary_is_cached_and_refreshed_on_moderation(): void
    {
        $this->review($this->shirt, null, ['rating' => 5]);
        $pending = $this->review($this->shirt, null, ['rating' => 1, 'status' => ReviewStatus::PENDING]);
        $this->assertEquals(['average' => 5.0, 'count' => 1], $this->props('/ar/product/shirt')['reviewSummary']);

        // A write that skips the service: the cached summary stays.
        DB::table('store_reviews')->where('id', $pending->id)->update(['status' => 'approved']);
        $this->assertEquals(['average' => 5.0, 'count' => 1], $this->props('/ar/product/shirt')['reviewSummary']);

        app(ReviewService::class)->moderate($pending->fresh(), ReviewStatus::APPROVED);
        $this->assertEquals(['average' => 3.0, 'count' => 2], $this->props('/ar/product/shirt')['reviewSummary']);
    }

    public function test_json_ld_has_the_aggregate_rating(): void
    {
        $this->assertArrayNotHasKey('aggregateRating', $this->jsonLd());

        $this->review($this->shirt, null, ['rating' => 5]);
        $this->review($this->shirt, null, ['rating' => 4]);
        $this->review($this->shirt, null, ['rating' => 1, 'status' => ReviewStatus::PENDING]);
        Cache::forget(ReviewService::summaryKey($this->shirt->id)); // written past the service

        $this->assertSame(['@type' => 'AggregateRating', 'ratingValue' => '4.5', 'reviewCount' => 2, 'bestRating' => 5, 'worstRating' => 1], $this->jsonLd()['aggregateRating']);
    }

    public function test_text_is_plain_and_escaped(): void
    {
        $buyer = $this->buyer();
        $this->orderFor($buyer, $this->medium);
        $evil = '<script>alert("x")</script><img src=x onerror=alert(1)>';

        $this->submit($buyer, ['title' => $evil, 'body' => $evil."\nسطر ثان"])->assertSessionHasNoErrors();
        Review::query()->update(['status' => ReviewStatus::APPROVED]);

        $this->assertSame($evil, Review::query()->sole()->title);
        $html = $this->get($this->siteUrl('/ar/product/shirt'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert("x")', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertSame($evil."\nسطر ثان", $this->props('/ar/product/shirt')['reviews']['data'][0]['body']);

        $this->submit($buyer, ['body' => str_repeat('ا', 2001)])->assertSessionHasErrors('body');
        $this->submit($buyer, ['rating' => 6])->assertSessionHasErrors('rating');
    }

    public function test_photos_are_checked_by_content(): void
    {
        $buyer = $this->buyer();
        $this->orderFor($buyer, $this->medium);

        $this->submit($buyer, ['photos' => [UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pwned";')]])->assertSessionHasErrors('photos.0');
        $this->submit($buyer, ['photos' => [UploadedFile::fake()->createWithContent('photo.png', 'GIF89a not really')]])->assertSessionHasErrors('photos.0');
        $this->submit($buyer, ['photos' => [UploadedFile::fake()->image('big.jpg')->size(6000)]])->assertSessionHasErrors('photos.0');
        $this->submit($buyer, ['photos' => array_map(fn (int $i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 5))])->assertSessionHasErrors('photos');
        $this->assertSame(0, Review::query()->count());

        $this->submit($buyer, ['photos' => [UploadedFile::fake()->image('a.jpg', 400, 300), UploadedFile::fake()->image('b.png', 300, 300), UploadedFile::fake()->image('c.webp', 200, 200)]])
            ->assertSessionHasNoErrors();
        $this->assertCount(3, Review::query()->sole()->getMedia('photos'));
    }

    public function test_photos_are_reencoded_without_exif(): void
    {
        $buyer = $this->buyer();
        $this->orderFor($buyer, $this->medium);

        $this->submit($buyer, ['photos' => [UploadedFile::fake()->createWithContent('camera.jpg', $this->jpegWithExif())]])->assertSessionHasNoErrors();

        $media = Review::query()->sole()->getFirstMedia('photos');
        $stored = (string) file_get_contents($media->getPath());
        $this->assertStringStartsWith("\xFF\xD8", $stored);
        $this->assertStringNotContainsString('Exif', $stored);
        $this->assertStringNotContainsString('GPS-SECRET', $stored);
        $this->assertNotSame('camera.jpg', $media->file_name);
    }

    public function test_staff_are_told_after_the_response(): void
    {
        $moderator = User::forceCreate(['name' => 'Mod', 'email' => 'mod-'.Str::random(6).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Review Update');
        $viewer = User::forceCreate(['name' => 'View', 'email' => 'view-'.Str::random(6).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Review View List');
        $buyer = $this->buyer();
        $this->orderFor($buyer, $this->medium);

        $this->withoutDeferredInvoke();
        $this->submit($buyer)->assertSessionHasNoErrors();
        Notification::assertNotSentTo($moderator, GeneralNotification::class);

        app(DeferredCallbackCollection::class)->invoke();
        Notification::assertSentTo($moderator, GeneralNotification::class);
        Notification::assertNotSentTo($viewer, GeneralNotification::class);
    }

    public function test_my_reviews(): void
    {
        $buyer = $this->buyer();
        $this->review($this->shirt, $buyer, ['status' => ReviewStatus::PENDING, 'body' => 'رأيي']);
        $this->review(Product::factory()->create(), null, ['body' => 'رأي غيري']);

        $this->actingAs($buyer)->get($this->siteUrl('/ar/account/reviews'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Store/Storefront/Account/Reviews')
            ->has('reviews', 1)
            ->where('reviews.0.body', 'رأيي')
            ->where('reviews.0.status', 'pending')
            ->where('reviews.0.product.name', 'قميص')
            ->etc());

        $this->get($this->siteUrl('/ar/account/reviews'))->assertOk();
        auth()->logout();
        $this->get($this->siteUrl('/ar/account/reviews'))->assertRedirect(route('store.account.login'));
    }

    public function test_the_sign_in_link_comes_back_to_the_reviews(): void
    {
        $buyer = $this->buyer();
        $login = $this->props('/ar/product/shirt')['reviewForm']['login_url'];

        $this->assertSame(route('store.account.login', ['back' => '/ar/product/shirt#reviews']), $login);
        $this->get($login)->assertOk();
        $this->post($this->siteUrl('/ar/account/login'), ['email' => $buyer->email, 'password' => 'secret-password'])
            ->assertRedirect($this->siteUrl('/ar/product/shirt#reviews'));
    }

    public function test_photos_claiming_huge_dimensions_are_refused(): void
    {
        $buyer = $this->buyer();
        $this->orderFor($buyer, $this->medium);

        $bomb = UploadedFile::fake()->createWithContent('bomb.png', \Tests\Unit\Core\ImageServiceTest::pngHeader(7000, 7000));

        $this->submit($buyer, ['photos' => [$bomb]])->assertSessionHasErrors('photos.0');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_first_review_racing_another_becomes_an_update(): void
    {
        $buyer = $this->buyer();
        $this->orderFor($buyer, $this->medium);
        $this->review($this->shirt, $buyer, ['rating' => 2, 'body' => 'الطلب الآخر وصل أولًا']);

        // The other request's row is not seen by the first look (it was not committed yet).
        $this->app->instance(\Modules\Store\Repositories\Contracts\ReviewRepositoryInterface::class, new class(new Review) extends \Modules\Store\Repositories\ReviewRepository
        {
            private int $looks = 0;

            public function lockForUser(int $userId, int $productId): ?Review
            {
                return $this->looks++ === 0 ? null : parent::lockForUser($userId, $productId);
            }
        });

        $this->submit($buyer, ['rating' => 5, 'body' => 'هذا ما بقي'])->assertSessionHasNoErrors();

        $review = Review::query()->sole();
        $this->assertSame([5, 'هذا ما بقي', ReviewStatus::PENDING], [$review->rating, $review->body, $review->status]);
    }

    public function test_deleting_a_review_or_its_author_refreshes_the_summary(): void
    {
        $first = $this->buyer();
        $second = $this->buyer();
        $this->review($this->shirt, $first, ['rating' => 5]);
        $removed = $this->review($this->shirt, $second, ['rating' => 1]);
        $this->review($this->shirt, null, ['rating' => 3]);
        $this->assertEquals(['average' => 3.0, 'count' => 3], $this->props('/ar/product/shirt')['reviewSummary']);

        $removed->delete();
        $this->assertEquals(['average' => 4.0, 'count' => 2], $this->props('/ar/product/shirt')['reviewSummary']);

        // The account goes: its reviews go with it (a database cascade, no model event).
        $first->delete();
        $this->assertEquals(['average' => 3.0, 'count' => 1], $this->props('/ar/product/shirt')['reviewSummary']);
    }

    private function submit(User $buyer, array $data = []): TestResponse
    {
        return $this->actingAs($buyer)->from($this->siteUrl('/ar/product/shirt'))->post($this->siteUrl('/ar/product/shirt/reviews'), $this->payload($data));
    }

    private function payload(array $data = []): array
    {
        return ['rating' => 4, 'title' => 'جيد', 'body' => 'قماش ممتاز والمقاس مضبوط.', ...$data];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLd(): array
    {
        return json_decode($this->props('/ar/product/shirt')['seo']['json_ld'][0], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function props(string $path, string $component = self::PAGE): array
    {
        return parent::props($path, $component);
    }

    /**
     * Deferred callbacks run when the test's response ends: this test wants to run them itself.
     */
    private function withoutDeferredInvoke(): void
    {
        $this->app->instance(DeferredCallbackCollection::class, new class extends DeferredCallbackCollection
        {
            public function invokeWhen(?\Closure $when = null): void
            {
                // held until the test calls invoke()
            }

            public function invoke(): void
            {
                parent::invokeWhen();
            }
        });
    }

    /**
     * A real JPEG with an EXIF block (and a GPS-like secret in it) right after its start marker.
     */
    private function jpegWithExif(): string
    {
        $image = imagecreatetruecolor(120, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $payload = "Exif\x00\x00MM\x00\x2A\x00\x00\x00\x08\x00\x00GPS-SECRET 31.9 35.2";
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
