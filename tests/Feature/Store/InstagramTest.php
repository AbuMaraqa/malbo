<?php

namespace Tests\Feature\Store;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Product;
use Modules\Items\Enums\ProductStatus;
use Modules\Store\Contracts\InstagramFeedSource;
use Modules\Store\Entities\InstagramPost;
use Modules\Store\Services\InstagramService;
use Modules\Store\Settings\StoreAppearanceSettings;

/**
 * Instagram content: posts staff enter (picture, link, caption, the products it shows), in their
 * order, on the home page once the section is switched on. The storefront reads them through
 * InstagramFeedSource, so a Graph API sync can take the manual source's place.
 */
class InstagramTest extends StorefrontTestCase
{
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $this->staff = $this->staff(['Instagram Post View List', 'Instagram Post Create', 'Instagram Post Update', 'Instagram Post Delete']);
    }

    public function test_crud_filters_and_permissions(): void
    {
        $shirt = Product::factory()->create(['name' => 'قميص']);

        $this->actingAs($this->staff)->post($this->admin('/instagram'), [
            'permalink' => 'https://www.instagram.com/p/ABC123/',
            'caption' => 'إطلالة الصيف',
            'sort_order' => 2,
            'is_active' => true,
            'products' => [$shirt->id],
            'image' => UploadedFile::fake()->image('post.jpg', 800, 800),
        ])->assertSessionHasNoErrors()->assertRedirect($this->admin('/instagram'));

        $post = InstagramPost::query()->with('products')->sole();
        $this->assertSame(['https://www.instagram.com/p/ABC123/', 'إطلالة الصيف', 'manual'], [$post->permalink, $post->caption, $post->source]);
        $this->assertSame([$shirt->id], $post->products->modelKeys());
        $this->assertNotNull($post->getFirstMedia('image'));

        $hidden = InstagramPost::query()->create(['permalink' => 'https://www.instagram.com/p/OLD/', 'is_active' => false]);

        $this->actingAs($this->staff)->get($this->admin('/instagram'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/InstagramPost/Index')
            ->where('table.meta.total', 2)
            ->where('settings.enabled', false));
        $this->actingAs($this->staff)->get($this->admin('/instagram?filter[is_active]=0'))->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $hidden->id));

        $this->actingAs($this->staff)->get($this->admin('/instagram/'.$post->id.'/edit'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/InstagramPost/Edit')->where('post.caption', 'إطلالة الصيف')->where('post.products', [$shirt->id]));

        $this->actingAs($this->staff)->post($this->admin('/instagram/'.$post->id), ['_method' => 'put', 'permalink' => 'https://www.instagram.com/p/ABC123/', 'caption' => 'جديد', 'sort_order' => 1, 'is_active' => true, 'products' => []])
            ->assertSessionHasNoErrors();
        $this->assertSame(['جديد', []], [$post->fresh()->caption, $post->products()->get()->modelKeys()]);
        $this->assertNotNull($post->fresh()->getFirstMedia('image'), 'the picture stays when none is sent');

        $this->actingAs($this->staff)->post($this->admin('/instagram'), ['permalink' => 'javascript:alert(1)', 'image' => UploadedFile::fake()->image('x.jpg')])->assertSessionHasErrors('permalink');
        $this->actingAs($this->staff)->post($this->admin('/instagram'), ['permalink' => 'https://www.instagram.com/p/X/'])->assertSessionHasErrors('image');

        $this->actingAs($this->staff)->put($this->admin('/instagram/'.$hidden->id.'/status'), ['active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($hidden->fresh()->is_active);

        $this->actingAs($this->staff)->delete($this->admin('/instagram/'.$post->id))->assertSessionHasNoErrors();
        $this->assertNull($post->fresh());

        $reader = $this->staff(['Instagram Post View List']);
        $this->actingAs($reader)->delete($this->admin('/instagram/'.$hidden->id))->assertForbidden();
        $this->actingAs($reader)->put($this->admin('/instagram/settings'), ['enabled' => true])->assertForbidden();
    }

    public function test_the_home_shows_active_posts_only_when_enabled(): void
    {
        $first = InstagramPost::query()->create(['permalink' => 'https://www.instagram.com/p/ONE/', 'caption' => 'one', 'sort_order' => 2]);
        $second = InstagramPost::query()->create(['permalink' => 'https://www.instagram.com/p/TWO/', 'caption' => 'two', 'sort_order' => 1]);
        InstagramPost::query()->create(['permalink' => 'https://www.instagram.com/p/OFF/', 'caption' => 'off', 'is_active' => false]);

        $this->assertNull($this->props('/ar', 'Store/Storefront/Home')['instagram']);

        $this->actingAs($this->staff)->put($this->admin('/instagram/settings'), ['enabled' => true, 'title' => 'تابعونا'])->assertSessionHasNoErrors();
        $this->assertTrue(app(StoreAppearanceSettings::class)->instagram_enabled);

        $instagram = $this->props('/ar', 'Store/Storefront/Home')['instagram'];
        $this->assertSame('تابعونا', $instagram['title']);
        $this->assertSame([$second->id, $first->id], array_column($instagram['posts'], 'id'));
        $this->assertSame('https://www.instagram.com/p/TWO/', $instagram['posts'][0]['url']);
    }

    public function test_tagged_products_are_shop_links(): void
    {
        $shirt = Product::factory()->create(['name' => 'قميص', 'slug' => 'shirt']);
        $draft = Product::factory()->create(['name' => 'مسودة', 'status' => ProductStatus::DRAFT]);
        $post = InstagramPost::query()->create(['permalink' => 'https://www.instagram.com/p/ONE/']);
        $post->products()->attach([$shirt->id => ['sort_order' => 0], $draft->id => ['sort_order' => 1]]);

        $posts = app(InstagramFeedSource::class)->posts(8);

        $this->assertSame([['name' => 'قميص', 'url' => route('store.product', ['slug' => 'shirt'])]], array_map(fn (array $product) => array_intersect_key($product, ['name' => 1, 'url' => 1]), $posts[0]['products']));
    }

    public function test_the_source_is_a_contract(): void
    {
        $this->app->instance(InstagramFeedSource::class, new class implements InstagramFeedSource
        {
            public function posts(int $limit): array
            {
                return [['id' => 9, 'image' => 'https://cdn.example/9.jpg', 'url' => 'https://www.instagram.com/p/NINE/', 'caption' => 'synced', 'products' => []]];
            }
        });
        $settings = app(StoreAppearanceSettings::class);
        $settings->instagram_enabled = true;
        $settings->save();

        $this->assertSame('synced', $this->props('/ar', 'Store/Storefront/Home')['instagram']['posts'][0]['caption']);

        // A sync writes through the service, one row per post of the source (by its id there).
        $service = app(InstagramService::class);
        $service->import('graph', '178', ['permalink' => 'https://www.instagram.com/p/A/', 'caption' => 'first', 'posted_at' => '2026-11-01 10:00:00']);
        $service->import('graph', '178', ['permalink' => 'https://www.instagram.com/p/A/', 'caption' => 'edited']);

        $this->assertSame(['edited', 'graph'], [InstagramPost::query()->sole()->caption, InstagramPost::query()->sole()->source]);
    }

    public function test_an_import_keeps_only_https_links(): void
    {
        $service = app(InstagramService::class);

        foreach (['javascript:alert(1)', 'http://www.instagram.com/p/A/', 'not a link', '//www.instagram.com/p/A/'] as $permalink) {
            $this->assertNull($service->import('graph', 'x'.md5($permalink), ['permalink' => $permalink]));
        }

        $this->assertSame(0, InstagramPost::query()->count());
        $this->assertNotNull($service->import('graph', '1', ['permalink' => 'https://www.instagram.com/p/A/']));
    }

    private function admin(string $path): string
    {
        return $this->siteUrl('/ar/admin/store'.$path);
    }
}
