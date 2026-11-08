<?php

namespace Tests\Feature\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Product;
use Tests\TenantTestCase;

class BrandPagesTest extends TenantTestCase
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

    public function test_the_list_is_shaped_for_the_table(): void
    {
        $brand = $this->brand('سامسونج', 'samsung');

        $this->page('/brand')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/Brand/Index')
                ->where('table.meta.total', 1)
                ->where('table.data.0.id', $brand->id)
                ->where('table.data.0.name', 'سامسونج')
                ->where('table.data.0.image_id', $brand->getFirstMedia('brand')->id)
                ->where('table.data.0.form.name', 'سامسونج')
                ->missing('table.data.0.created_by'));
    }

    public function test_search_finds_a_slug_in_another_letter_case_and_a_name(): void
    {
        $this->brand('سامسونج', 'samsung');
        $this->brand('أبل', 'apple');

        $this->page('/brand?filter[search]=SAMS')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'samsung'));

        $this->page('/brand?filter[search]=أبل')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'apple'));
    }

    public function test_each_filter_and_a_malformed_date(): void
    {
        $this->brand('سامسونج', 'samsung');
        $this->brand('أبل', 'apple');

        $this->page('/brand?filter[name]=سامس')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'samsung'));

        $this->page('/brand?filter[created_at_from]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
        $this->page('/brand?filter[created_at_to]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 2));
        $this->page('/brand?filter[created_at_to]=nope')->assertOk();
    }

    public function test_sorting_by_the_translated_name(): void
    {
        $this->brand('بقالة', 'b');
        $this->brand('أدوات', 'a');

        $this->page('/brand?sort=name')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.slug', 'a'));
    }

    public function test_adding_saves_the_translation_image_and_creator(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/brand'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $brand = Brand::query()->where('slug', 'new-brand')->firstOrFail();

        $this->assertSame('علامة', $brand->translate('ar')->name);
        $this->assertSame('وصف', $brand->translate('ar')->description);
        $this->assertSame($this->staff->id, $brand->created_by);
        $this->assertTrue($brand->hasMedia('brand'));
    }

    public function test_the_model_does_not_read_translations_from_the_request(): void
    {
        $this->app['request']->merge(['name' => 'From the request', 'description' => 'x']);

        $brand = Brand::factory()->create(['name' => 'صريح']);

        $this->assertSame('صريح', $brand->fresh()->translate('ar')->name);
    }

    public function test_validation_errors(): void
    {
        $this->brand('سامسونج', 'taken');

        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/brand'), $this->payload(['name' => '', 'slug' => 'taken', 'image' => null, 'description' => str_repeat('x', 256)]))
            ->assertSessionHasErrors(['name', 'slug', 'image', 'description']);
    }

    public function test_editing_updates_the_same_brand_and_keeps_its_image_when_none_is_sent(): void
    {
        $brand = $this->brand('قديم', 'old-slug');
        $media = $brand->getFirstMedia('brand')->id;

        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/brand/'.$brand->id), [...$this->payload(['slug' => 'old-slug', 'name' => 'معدل', 'image' => null]), '_method' => 'put'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $brand->fresh();

        $this->assertSame(1, Brand::query()->count());
        $this->assertSame('معدل', $fresh->translate('ar')->name);
        $this->assertSame($media, $fresh->getFirstMedia('brand')->id);
    }

    public function test_a_new_image_replaces_the_old_one_and_removing_clears_it(): void
    {
        $brand = $this->brand('قديم', 'old-slug');
        $old = $brand->getFirstMedia('brand')->id;

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/brand/'.$brand->id), $this->payload(['slug' => 'old-slug']))
            ->assertSessionHasNoErrors();

        $media = $brand->fresh()->getMedia('brand');
        $this->assertCount(1, $media);
        $this->assertNotSame($old, $media->first()->id);

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/brand/'.$brand->id), $this->payload(['slug' => 'old-slug', 'image' => null, 'removed_image' => [$media->first()->id]]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($brand->fresh()->hasMedia('brand'));
    }

    public function test_deleting_a_brand_keeps_its_products(): void
    {
        $brand = $this->brand('سامسونج', 'samsung');
        $product = Product::factory()->create(['brand_id' => $brand->id]);

        $this->actingAs($this->staff)->delete($this->tenantUrl('/ar/admin/brand/'.$brand->id))->assertRedirect();

        $this->assertNull(Brand::query()->find($brand->id));
        $this->assertNull($product->fresh()->brand_id);
    }

    public function test_bulk_delete(): void
    {
        $one = $this->brand('واحد', 'one');
        $two = $this->brand('اثنان', 'two');

        $this->actingAs($this->staff)->delete($this->tenantUrl('/ar/admin/brand'), ['ids' => [$one->id, $two->id]])->assertRedirect();

        $this->assertSame(0, Brand::query()->count());
    }

    public function test_info_returns_explicit_fields(): void
    {
        $brand = $this->brand('سامسونج', 'samsung');

        $this->page('/brand/'.$brand->id.'/info')->assertOk()->assertJsonStructure(['created_by', 'created_at', 'updated_at', 'activities']);
    }

    public function test_a_bad_id_is_a_not_found_not_a_server_error(): void
    {
        $this->page('/brand/abc/info')->assertNotFound();
        $this->actingAs($this->staff)->put($this->tenantUrl('/ar/admin/brand/999999'), $this->payload())->assertNotFound();
    }

    public function test_every_action_needs_its_permission(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->givePermissionTo('Brand View List');

        $brand = $this->brand('سامسونج', 'samsung');

        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/brand'))->assertOk();
        $this->actingAs($viewer)->post($this->tenantUrl('/ar/admin/brand'), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/brand/'.$brand->id.'/info'))->assertForbidden();
        $this->actingAs($viewer)->put($this->tenantUrl('/ar/admin/brand/'.$brand->id), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl('/ar/admin/brand/'.$brand->id))->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl('/ar/admin/brand'), ['ids' => [$brand->id]])->assertForbidden();

        $this->assertNotNull($brand->fresh());
    }

    public function test_guests_go_to_the_login_page(): void
    {
        $this->get($this->tenantUrl('/ar/admin/brand'))->assertRedirect();
    }

    private function page(string $path)
    {
        return $this->actingAs($this->staff)->get($this->tenantUrl('/ar/admin'.$path));
    }

    private function brand(string $name, string $slug): Brand
    {
        $brand = Brand::factory()->create(['name' => $name, 'slug' => $slug]);
        $brand->addImage(UploadedFile::fake()->image('brand.jpg'));

        return $brand->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'علامة',
            'slug' => 'new-brand',
            'description' => 'وصف',
            'image' => UploadedFile::fake()->image('brand.jpg'),
            ...$overrides,
        ];
    }
}
