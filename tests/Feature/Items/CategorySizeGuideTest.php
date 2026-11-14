<?php

namespace Tests\Feature\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Support\RichText;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Product;
use Modules\Store\Services\StorefrontCatalogCache;
use Tests\DatabaseTestCase;

/**
 * A category's size guide: rich text, translated, edited in the category form.
 */
class CategorySizeGuideTest extends DatabaseTestCase
{
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->staff = User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->assignRole('Super Admin');
    }

    public function test_the_size_guide_is_saved_sanitized_and_shown_on_edit(): void
    {
        $html = '<table><tr><td>M</td><td onclick="x()">98</td></tr></table><script>alert(1)</script>';

        $this->actingAs($this->staff)->post($this->siteUrl('/ar/admin/categories'), [
            'name' => 'قمصان',
            'slug' => 'shirts-'.Str::lower(Str::random(5)),
            'status' => true,
            'size_guide' => $html,
            'image' => UploadedFile::fake()->image('c.jpg'),
        ])->assertSessionHasNoErrors();

        $category = Category::query()->latest('id')->firstOrFail();
        $saved = $category->translate('ar')->size_guide;
        $this->assertSame(RichText::make($html)->toHtml(), $saved);
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('onclick', $saved);

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/categories/'.$category->id.'/edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('category.size_guide', $saved));

        $this->actingAs($this->staff)->put($this->siteUrl('/en/admin/categories/'.$category->id), [
            'name' => 'Shirts',
            'slug' => $category->slug,
            'status' => true,
            'size_guide' => '<p>Measure your chest.</p>',
        ])->assertSessionHasNoErrors();

        $category = Category::query()->findOrFail($category->id);
        $this->assertSame($saved, $category->translate('ar')->size_guide, 'the other locale is left alone');
        $this->assertSame('<p>Measure your chest.</p>', $category->translate('en')->size_guide);
    }

    public function test_a_tiptap_table_and_image_reach_the_product_page(): void
    {
        // What Tiptap's table and image extensions produce.
        $html = '<p>Measure flat.</p><table style="min-width: 50px;"><colgroup><col style="min-width: 25px;"><col style="min-width: 25px;"></colgroup>'
            .'<tbody><tr><th colspan="1" rowspan="1"><p>Size</p></th><th colspan="1" rowspan="1"><p>Chest</p></th></tr>'
            .'<tr><td colspan="1" rowspan="1"><p>M</p></td><td colspan="2" rowspan="1"><p>98</p></td></tr></tbody></table>'
            .'<img src="https://cdn.example.com/guide.png" alt="How to measure">';

        $this->actingAs($this->staff)->post($this->siteUrl('/ar/admin/categories'), [
            'name' => 'قمصان',
            'slug' => 'guided-'.Str::lower(Str::random(5)),
            'status' => true,
            'size_guide' => $html,
            'image' => UploadedFile::fake()->image('c.jpg'),
        ])->assertSessionHasNoErrors();

        $product = Product::factory()->create(['slug' => 'guided-shirt', 'manage_stock' => 0]);
        $product->categories()->attach(Category::query()->latest('id')->value('id'));
        app(StorefrontCatalogCache::class)->forget();

        $this->get($this->siteUrl('/ar/product/guided-shirt'))->assertOk()->assertInertia(function (Assert $page): void {
            $guide = $page->toArray()['props']['product']['size_guide']['html'];

            foreach (['<table', '<colgroup>', '<col', '<tbody>', '<tr>', '<th', '<td', 'colspan="2"', '98', '<img src="https://cdn.example.com/guide.png"', 'alt="How to measure"'] as $part) {
                $this->assertStringContainsString($part, $guide);
            }
        });
    }
}
