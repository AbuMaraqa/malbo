<?php

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Items\Entities\Product;
use Modules\Store\Entities\Review;
use Modules\Store\Enums\ReviewStatus;
use Tests\Feature\Store\Concerns\MakesReviews;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Review moderation: the list with its filters, approve, reject, reply, remove a photo.
 */
class ReviewPagesTest extends StorefrontTestCase
{
    use MakesReviews;
    use MakesShippingZones;

    private Product $shirt;

    private Product $belt;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->shirt = Product::factory()->create(['name' => 'قميص']);
        $this->belt = Product::factory()->create(['name' => 'حزام']);
    }

    public function test_the_moderation_list_and_filters(): void
    {
        $pending = $this->review($this->shirt, $this->buyer('Samir Haddad'), ['status' => ReviewStatus::PENDING, 'rating' => 2, 'body' => 'متوسط']);
        $approved = $this->review($this->shirt, null, ['rating' => 5]);
        $belt = $this->review($this->belt, null, ['status' => ReviewStatus::REJECTED, 'rating' => 1, 'body' => 'سيئ جدًا']);

        $rows = fn (string $query = '') => collect($this->actingAs($this->staff(['Review View List']))->get($this->siteUrl('/ar/admin/store/reviews'.$query))
            ->assertOk()->viewData('page')['props']['table']['data']);

        $all = $rows();
        $this->assertCount(3, $all);
        $row = $all->firstWhere('id', $pending->id);
        $this->assertSame(['قميص', 'Samir Haddad', 2, 'pending', 'متوسط'], [$row['product'], $row['author'], $row['rating'], $row['status'], $row['body']]);

        $this->assertSame([$pending->id], $rows('?filter[status]=pending')->pluck('id')->all());
        $this->assertSame([$approved->id], $rows('?filter[rating]=5')->pluck('id')->all());
        $this->assertSame([$belt->id], $rows('?filter[product]='.$this->belt->id)->pluck('id')->all());
        $this->assertSame([$belt->id], $rows('?filter[search]=جدًا')->pluck('id')->all());

        $this->actingAs($this->staff(['Review View List']))->get($this->siteUrl('/ar/admin/store/reviews'))
            ->assertInertia(fn (Assert $page) => $page->component('Store/Review/Index')->where('products', fn ($products) => collect($products)->pluck('value')->sort()->values()->all() === collect([$this->shirt->id, $this->belt->id])->sort()->values()->all())->etc());
    }

    public function test_approve_reject_reply_and_delete_a_photo(): void
    {
        $review = $this->review($this->shirt, null, ['status' => ReviewStatus::PENDING]);
        $photo = $review->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('photos');
        $other = $this->review($this->belt);
        $foreign = $other->addMedia(UploadedFile::fake()->image('b.jpg'))->toMediaCollection('photos');
        $staff = $this->staff(['Review View List', 'Review Update']);
        $base = $this->siteUrl('/ar/admin/store/reviews/'.$review->id);

        $this->actingAs($staff)->get($base.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Review/Edit')
            ->where('review.id', $review->id)
            ->where('review.photos.0.id', $photo->id)
            ->etc());

        $this->actingAs($staff)->from($base.'/edit')->put($base.'/status', ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::APPROVED, $review->fresh()->status);

        $this->actingAs($staff)->from($base.'/edit')->put($base.'/status', ['status' => 'rejected'])->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::REJECTED, $review->fresh()->status);
        $this->actingAs($staff)->from($base.'/edit')->put($base.'/status', ['status' => 'deleted'])->assertSessionHasErrors('status');

        $this->actingAs($staff)->from($base.'/edit')->put($base.'/reply', ['staff_reply' => '  شكرًا لك، يسعدنا ذلك.  '])->assertSessionHasNoErrors();
        $this->assertSame('شكرًا لك، يسعدنا ذلك.', $review->fresh()->staff_reply);
        $this->assertNotNull($review->fresh()->replied_at);
        $this->actingAs($staff)->from($base.'/edit')->put($base.'/reply', ['staff_reply' => ''])->assertSessionHasNoErrors();
        $this->assertNull($review->fresh()->staff_reply);

        // Another review's photo is never touched through this one.
        $this->actingAs($staff)->delete($base.'/photos/'.$foreign->id)->assertNotFound();
        $this->actingAs($staff)->from($base.'/edit')->delete($base.'/photos/'.$photo->id)->assertRedirect();
        $this->assertCount(0, $review->fresh()->getMedia('photos'));
        $this->assertCount(1, $other->fresh()->getMedia('photos'));
    }

    public function test_staff_need_the_permissions(): void
    {
        $review = $this->review($this->shirt, null, ['status' => ReviewStatus::PENDING]);
        $base = $this->siteUrl('/ar/admin/store/reviews');

        $this->actingAs($this->staff(['Page View List']))->get($base)->assertForbidden();

        $viewer = $this->staff(['Review View List']);
        $this->actingAs($viewer)->get($base)->assertOk();
        $this->actingAs($viewer)->get($base.'/'.$review->id.'/edit')->assertForbidden();
        $this->actingAs($viewer)->put($base.'/'.$review->id.'/status', ['status' => 'approved'])->assertForbidden();
        $this->assertSame(ReviewStatus::PENDING, $review->fresh()->status);
    }
}
