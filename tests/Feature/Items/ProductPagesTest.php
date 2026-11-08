<?php

namespace Tests\Feature\Items;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\BranchPrice;
use Modules\Items\Entities\Brand;
use Modules\Items\Entities\Category;
use Modules\Items\Entities\Label;
use Modules\Items\Entities\Product;
use Modules\Items\Entities\Tag;
use Modules\Items\Enums\ProductStatus;
use Modules\Items\Events\ProductUpdated;
use Tests\TenantTestCase;

/**
 * Ported from core's ProductAddPageTest, ProductDataFixesTest, ProductPagesCharacterizationTest and
 * ProductStatusTest: Livewire assertions became HTTP + assertInertia.
 */
class ProductPagesTest extends TenantTestCase
{
    private User $staff;

    private Category $c1;

    private Category $c2;

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

        [$this->c1, $this->c2] = Category::factory()->count(2)->create()->all();
    }

    // ---------------------------------------------------------------- the list

    public function test_the_list_is_shaped_for_the_table_and_hides_variations(): void
    {
        $product = $this->product(['name' => 'قميص', 'sale_price' => 80]);
        $product->categories()->sync([$this->c1->id]);
        $this->variationOf($product);

        $this->page('/products')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Product/Index')
            ->where('table.meta.total', 1)
            ->where('table.data.0.id', $product->id)
            ->where('table.data.0.name', 'قميص')
            ->where('table.data.0.categories', (string) $this->c1->name)
            ->where('table.data.0.status', ProductStatus::PUBLISHED->value)
            ->where('table.data.0.is_active', true)
            ->has('statuses', 2)
            ->missing('table.data.0.created_by'));
    }

    public function test_search_finds_a_name_and_a_sku_in_another_letter_case(): void
    {
        $this->product(['name' => 'Cotton Shirt', 'sku' => 'SKU-AAA111']);
        $this->product(['name' => 'Linen Pants', 'sku' => 'SKU-BBB222']);

        $this->page('/products?filter[search]=cOTTON')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.name', 'Cotton Shirt'));

        $this->page('/products?filter[search]=sku-bbb')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.name', 'Linen Pants'));

        $this->page('/products?filter[name]=LINEN')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.name', 'Linen Pants'));
    }

    public function test_each_filter_and_malformed_values(): void
    {
        $brand = Brand::factory()->create();
        $draft = $this->product(['name' => 'مسودة', 'status' => ProductStatus::DRAFT, 'is_active' => 0, 'base_price' => 10, 'stock_status' => 'out_of_stock']);
        $published = $this->product(['name' => 'منشور', 'is_featured' => true, 'base_price' => 90, 'brand_id' => $brand->id]);
        $published->categories()->sync([$this->c1->id]);

        $only = fn (string $query, Product $product) => $this->page('/products?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $product->id));

        $only('filter[status]='.ProductStatus::DRAFT->value, $draft);
        $only('filter[is_active]=0', $draft);
        $only('filter[is_active]=1', $published);
        $only('filter[category_id]='.$this->c1->id, $published);
        $only('filter[brand_id]='.$brand->id, $published);
        $only('filter[is_featured]=1', $published);
        $only('filter[stock_status]=out_of_stock', $draft);
        $only('filter[base_price_from]=50', $published);
        $only('filter[base_price_to]=50', $draft);

        $this->page('/products?filter[created_at_from]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
        $this->page('/products?filter[status]=abc')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 2));
    }

    public function test_sorting_by_the_translated_name(): void
    {
        $this->product(['name' => 'بقالة']);
        $this->product(['name' => 'أدوات']);

        $this->page('/products?sort=name')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.name', 'أدوات'));
        $this->page('/products?sort=-base_price')->assertOk();
    }

    public function test_a_bad_id_is_a_404_and_permissions_are_required(): void
    {
        $this->page('/products/abc/edit')->assertNotFound();
        $this->page('/products/999999/edit')->assertNotFound();

        $viewer = User::forceCreate(['name' => 'Viewer', 'email' => 'viewer-'.Str::random(8).'@items.test', 'password' => 'secret-password']);
        $role = \Spatie\Permission\Models\Role::findOrCreate('Product Viewer '.Str::random(5), 'web');
        $viewer->assignRole($role);
        $product = $this->product();

        $this->actingAs($viewer)->get($this->url('/products'))->assertForbidden();
        $this->actingAs($viewer)->delete($this->url('/products/'.$product->id))->assertForbidden();
        $this->actingAs($viewer)->get($this->url('/products/'.$product->id.'/barcodes'))->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/products/'.$product->id.'/status'), ['status' => 1])->assertForbidden();
        $this->assertNotNull($product->fresh());
    }

    // ---------------------------------------------------------------- add (ProductAddPageTest, ProductStatusTest)

    public function test_add_saves_a_minimal_product_published_with_its_image(): void
    {
        $slug = 'minimal-'.Str::lower(Str::random(6));

        $this->store(['name' => 'منتج بسيط', 'slug' => $slug])->assertRedirect($this->url('/products'))->assertSessionHasNoErrors();

        $product = Product::query()->where('slug', $slug)->firstOrFail();
        $this->assertSame('منتج بسيط', $product->translate('ar')->name);
        $this->assertSame(ProductStatus::PUBLISHED, $product->status);
        $this->assertSame([$this->c1->id], $product->categories()->pluck(config('items.table_prefix').'categories.id')->all());
        $this->assertTrue($product->hasMedia('product'), 'addImage swallows errors, so the media itself is checked');
    }

    public function test_add_requires_the_form_fields(): void
    {
        $this->from($this->url('/products/add'))->actingAs($this->staff)
            ->post($this->url('/products'), ['name' => ''])
            ->assertSessionHasErrors(['name', 'image', 'base_price', 'category_id', 'status']);
    }

    public function test_slug_comes_from_the_name_and_transliterates_arabic(): void
    {
        $this->store(['name' => 'قميص أزرق', 'slug' => ''])->assertSessionHasNoErrors();

        $product = $this->latestParent();
        $this->assertNotSame('', $product->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $product->slug);
    }

    public function test_double_submit_does_not_create_a_duplicate(): void
    {
        $slug = 'double-'.Str::lower(Str::random(6));

        $this->store(['slug' => $slug])->assertSessionHasNoErrors();
        $this->store(['slug' => $slug])->assertSessionHasErrors('slug');

        $this->assertSame(1, Product::query()->where('slug', $slug)->count());
    }

    public function test_a_slug_held_by_a_deleted_product_is_a_validation_error_not_a_crash(): void
    {
        $deleted = $this->product();
        $deleted->delete();

        $this->store(['slug' => $deleted->slug])->assertSessionHasErrors('slug');
    }

    public function test_status_is_draft_or_published_only(): void
    {
        $this->store(['status' => ProductStatus::ARCHIVED->value])->assertSessionHasErrors('status');
        $this->store(['status' => 'x'])->assertSessionHasErrors('status');
    }

    public function test_the_form_offers_draft_and_published_only(): void
    {
        $this->page('/products/add')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Product/Add')
            ->where('statuses.0.value', ProductStatus::DRAFT->value)
            ->where('statuses.1.value', ProductStatus::PUBLISHED->value)
            ->has('statuses', 2));
    }

    public function test_the_payload_field_carries_the_form_and_empty_lists(): void
    {
        $slug = 'payload-'.Str::lower(Str::random(6));

        $this->actingAs($this->staff)->post($this->url('/products'), [
            'payload' => json_encode([
                ...$this->fields(['slug' => $slug, 'cost_price' => '']),
                'tags' => [],
                'variations' => [],
            ]),
            'image' => UploadedFile::fake()->image('p.jpg'),
        ])->assertSessionHasNoErrors();

        $product = Product::query()->where('slug', $slug)->firstOrFail();
        $this->assertNull($product->cost_price);
        $this->assertTrue($product->hasMedia('product'));
    }

    public function test_a_file_in_a_row_joins_the_row_from_the_payload(): void
    {
        $slug = 'row-file-'.Str::lower(Str::random(6));

        $this->actingAs($this->staff)->post($this->url('/products'), [
            'payload' => json_encode($this->fields(['slug' => $slug, 'variations' => [$this->row(['name' => 'أحمر'])]])),
            'image' => UploadedFile::fake()->image('p.jpg'),
            'variations' => [0 => ['image' => UploadedFile::fake()->image('v.jpg')]],
        ])->assertSessionHasNoErrors();

        $variation = Product::query()->where('slug', $slug)->firstOrFail()->variations()->firstOrFail();
        $this->assertSame('أحمر', $variation->translate('ar')->name);
        $this->assertTrue($variation->hasMedia('product'));
    }

    // ---------------------------------------------------------------- ProductDataFixesTest

    public function test_add_saves_discount_and_limits(): void
    {
        $this->store([
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'discount_start_date' => '2026-11-01',
            'discount_end_date' => '2026-11-30',
            'cost_price' => 60,
            'wholesale_price' => 80,
            'low_stock_threshold' => 3,
            'stock_status' => 'in_stock',
            'allow_backorder' => true,
            'barcode' => '123',
            'max_quantity_per_order' => 5,
        ])->assertSessionHasNoErrors();

        $product = $this->latestParent();
        $this->assertSame('percentage', $product->discount_type);
        $this->assertEquals(10, $product->discount_value);
        $this->assertStringStartsWith('2026-11-01', (string) $product->discount_start_date);
        $this->assertStringStartsWith('2026-11-30', (string) $product->discount_end_date);
        $this->assertEquals(60, $product->cost_price);
        $this->assertEquals(80, $product->wholesale_price);
        $this->assertEquals(3, $product->low_stock_threshold);
        $this->assertSame('in_stock', $product->stock_status);
        $this->assertTrue((bool) $product->allow_backorder);
        $this->assertSame('123', $product->barcode);
        $this->assertEquals(5, $product->max_quantity_per_order);
    }

    public function test_untouched_and_cleared_fields_get_the_column_defaults(): void
    {
        $this->store([
            'discount_type' => '',
            'discount_value' => '',
            'discount_start_date' => '',
            'discount_end_date' => '',
            'cost_price' => '',
            'wholesale_price' => '',
            'low_stock_threshold' => '',
            'stock_status' => '',
            'max_quantity_per_order' => '',
            'barcode' => '',
            'allow_backorder' => '',
        ])->assertSessionHasNoErrors();

        $product = $this->latestParent()->fresh();
        $this->assertSame('none', $product->discount_type);
        $this->assertSame('in_stock', $product->stock_status);
        $this->assertEquals(10, $product->low_stock_threshold);
        $this->assertFalse((bool) $product->allow_backorder);
        $this->assertNull($product->discount_value);
        $this->assertNull($product->discount_start_date);
        $this->assertNull($product->cost_price);
        $this->assertNull($product->max_quantity_per_order);
    }

    public function test_the_discount_window_and_percentage_are_validated(): void
    {
        $this->store(['discount_start_date' => '2026-11-30', 'discount_end_date' => '2026-11-01'])->assertSessionHasErrors('discount_end_date');
        $this->store(['discount_type' => 'percentage', 'discount_value' => 150])->assertSessionHasErrors('discount_value');
        $this->store(['discount_type' => 'bogus'])->assertSessionHasErrors('discount_type');
    }

    public function test_description_is_made_safe_on_the_product_and_its_variations(): void
    {
        $this->store([
            'description' => '<p onclick="x()">وصف</p><script>alert(1)</script>',
            'variations' => [$this->row(['name' => 'أحمر', 'description' => '<p>متغير</p><script>alert(2)</script>'])],
        ])->assertSessionHasNoErrors();

        $product = $this->latestParent();
        $this->assertStringContainsString('وصف', (string) $product->description);
        $this->assertStringNotContainsString('script', (string) $product->description);
        $this->assertStringNotContainsString('onclick', (string) $product->description);
        $this->assertStringNotContainsString('script', (string) $product->variations()->firstOrFail()->description);
    }

    public function test_add_saves_seo_text(): void
    {
        $this->store(['meta_title' => 'عنوان', 'meta_description' => 'وصف', 'meta_keywords' => 'a,b'])->assertSessionHasNoErrors();

        $this->assertEquals(
            ['seo' => ['ar' => ['title' => 'عنوان', 'description' => 'وصف', 'keywords' => 'a,b']]],
            $this->latestParent()->meta_data,
        );
    }

    public function test_edit_shows_and_saves_seo_text(): void
    {
        $product = $this->product(['meta_data' => ['seo' => ['ar' => ['title' => 'عنوان', 'description' => null, 'keywords' => null]]]]);
        $product->categories()->sync([$this->c1->id]);

        $this->page('/products/'.$product->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Product/Edit')
            ->where('product.meta_title', 'عنوان'));

        $this->update($product, ['meta_title' => 'جديد'])->assertSessionHasNoErrors();

        $this->assertSame('جديد', $product->fresh()->meta_data['seo']['ar']['title']);
    }

    public function test_edit_keeps_values_of_a_product_without_meta(): void
    {
        $product = $this->product(['meta_data' => null, 'discount_value' => null]);

        $this->update($product)->assertSessionHasNoErrors();

        $fresh = $product->fresh();
        $this->assertEquals(100, $fresh->base_price);
        $this->assertNull($fresh->meta_data);
        $this->assertNull($fresh->discount_value);
    }

    public function test_edit_does_not_require_an_image_and_keeps_the_saved_sku_when_left_empty(): void
    {
        $product = $this->product(['sku' => 'SKU-KEEP']);

        $this->update($product, ['sku' => ''])->assertSessionHasNoErrors();

        $this->assertSame('SKU-KEEP', $product->fresh()->sku);
    }

    public function test_a_taken_sku_is_a_validation_error(): void
    {
        $this->product(['sku' => 'SKU-TAKEN']);

        $this->store(['sku' => 'SKU-TAKEN'])->assertSessionHasErrors('sku');
        $this->store(['sku' => 'SKU-SAME', 'variations' => [$this->row(['sku' => 'SKU-SAME'])]])->assertSessionHasErrors('variations.0.sku');
    }

    // ---------------------------------------------------------------- ProductPagesCharacterizationTest

    public function test_add_full_payload(): void
    {
        [$branch, $label, $addon, $o1, $o2, $red, $blue] = $this->catalog();
        Tag::factory()->create(['name' => 'قديم']);
        $slug = 'full-'.Str::lower(Str::random(6));

        $this->store([
            'name' => 'منتج كامل',
            'slug' => $slug,
            'base_price' => 120,
            'category_id' => [$this->c1->id, $this->c2->id],
            'branches' => [$branch->id],
            'meta_title' => 'عنوان',
            'tags' => ['جديد', 'قديم'],
            'labels' => [(string) $label->name],
            'addons' => [[
                'addon_id' => $addon->id, 'is_required' => true, 'min_selection' => 1, 'max_selection' => 2, 'max_quantity_per_option' => 3,
                'allowed_options' => [
                    ['addon_option_id' => $o1->id, 'price' => 7, 'is_default' => true],
                    ['addon_option_id' => $o2->id, 'price' => '', 'is_default' => false],
                ],
            ]],
            'variations' => [
                $this->row(['name' => 'أحمر', 'base_price' => 120, 'attribute_values' => [$red->id], 'category_id' => [$this->c2->id]]),
                $this->row(['name' => 'أزرق', 'base_price' => 130, 'attribute_values' => [$blue->id], 'category_id' => [$this->c2->id]]),
            ],
        ])->assertSessionHasNoErrors();

        $product = Product::query()->where('slug', $slug)->firstOrFail();
        $this->assertSame('منتج كامل', $product->translate('ar')->name);
        $this->assertSame(ProductStatus::PUBLISHED, $product->status);
        $this->assertSame('عنوان', $product->meta_data['seo']['ar']['title']);

        $tagNames = $product->tags()->with('translations')->get()->map(fn (Tag $t) => $t->translate('ar')->name)->sort()->values()->all();
        $this->assertSame(['جديد', 'قديم'], $tagNames);
        $this->assertSame(1, Tag::whereTranslation('name', 'قديم')->count());
        $this->assertSame([$label->id], $product->labels->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$this->c1->id, $this->c2->id], $product->categories->pluck('id')->all());
        $this->assertSame([$branch->id], $product->branches->pluck('id')->all());

        $pivot = DB::table(config('items.table_prefix').'addon_product')->where('product_id', $product->id)->first();
        $this->assertSame($addon->id, (int) $pivot->addon_id);
        $this->assertTrue((bool) $pivot->is_required);
        $this->assertSame(2, (int) $pivot->max_selection);
        $this->assertSame(3, (int) $pivot->max_quantity_per_option);
        $overrides = DB::table(config('items.table_prefix').'addon_product_option')->where('addon_product_id', $pivot->id)->orderBy('id')->get();
        $this->assertEquals(7, $overrides[0]->price);
        $this->assertTrue((bool) $overrides[0]->is_default);
        $this->assertNull($overrides[1]->price);

        $variations = $product->variations()->orderBy('id')->get();
        $this->assertCount(2, $variations);
        foreach ($variations as $i => $variation) {
            $this->assertSame(ProductStatus::PUBLISHED, $variation->status);
            $this->assertSame([[$red->id], [$blue->id]][$i], $variation->attributeValues->pluck('id')->all());
            $this->assertSame([$this->c2->id], $variation->categories->pluck('id')->all());
        }
        $this->assertNotSame($variations[0]->slug, $variations[1]->slug);
        $this->assertEquals(130, $variations[1]->base_price);
    }

    public function test_the_edit_page_sends_the_saved_form_with_the_selected_attribute_values(): void
    {
        [$branch, , $addon, $o1, , $red, $blue] = $this->catalog();
        $product = $this->product();
        $product->branches()->sync([$branch->id]);
        $product->categories()->sync([$this->c1->id]);
        $variation = $this->variationOf($product);
        $variation->attributeValues()->sync([$red->id]);

        $this->update($product, [
            'branches' => [$branch->id],
            'variations' => [$this->row(['id' => $variation->id, 'name' => 'أحمر', 'attribute_values' => [$red->id]])],
            'addons' => [['addon_id' => $addon->id, 'allowed_options' => [['addon_option_id' => $o1->id, 'price' => 3]]]],
        ])->assertSessionHasNoErrors();

        $this->page('/products/'.$product->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.branches.0', $branch->id)
            ->where('product.category_id.0', $this->c1->id)
            ->where('product.variations.0.id', $variation->id)
            ->where('product.variations.0.attribute_values.0', $red->id)
            ->where('product.attributes.'.$red->attribute_id.'.0', $red->id)
            ->where('product.addons.0.addon_id', $addon->id)
            ->where('product.addons.0.allowed_options.0.addon_option_id', $o1->id)
            ->has('attributes', fn (Assert $list) => $list->etc())
            ->missing('product.created_by'));
        $this->assertNotNull($blue);
    }

    public function test_edit_full_payload(): void
    {
        $product = $this->product(['name' => 'قديم']);
        $product->categories()->sync([$this->c1->id]);
        $keep = $this->variationOf($product);
        $remove = $this->variationOf($product);

        $this->update($product, [
            'name' => 'اسم جديد',
            'tags' => ['قديم'],
            'variations' => [
                $this->row(['id' => $keep->id, 'name' => 'يبقى', 'base_price' => 111]),
                $this->row(['name' => 'أخضر', 'slug' => 'green-'.Str::lower(Str::random(4)), 'base_price' => 140]),
            ],
        ])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('اسم جديد', $product->translate('ar')->name);
        $this->assertSame(['قديم'], $product->tags()->with('translations')->get()->map(fn (Tag $t) => $t->translate('ar')->name)->all());
        $this->assertEquals(111, $keep->fresh()->base_price);
        $this->assertSoftDeleted($remove);
        $new = $product->variations()->whereNotIn('id', [$keep->id])->firstOrFail();
        $this->assertSame(ProductStatus::PUBLISHED, $new->status);
        $this->assertEquals(140, $new->base_price);
    }

    public function test_an_empty_list_clears_tags_branches_addons_and_variations(): void
    {
        [$branch] = $this->catalog();
        $product = $this->product();
        $product->categories()->sync([$this->c1->id]);
        $product->branches()->sync([$branch->id]);
        $product->tags()->sync([Tag::factory()->create()->id]);
        $variation = $this->variationOf($product);

        $this->update($product, ['tags' => [], 'branches' => [], 'variations' => []])->assertSessionHasNoErrors();

        $this->assertSame(0, $product->tags()->count());
        $this->assertSame(0, $product->branches()->count());
        $this->assertSoftDeleted($variation);
    }

    public function test_edit_restores_a_trashed_variation_with_the_same_slug(): void
    {
        $product = $this->product();
        $removed = $this->variationOf($product);
        $removed->delete();

        $this->update($product, ['variations' => [$this->row(['name' => 'أزرق', 'slug' => $removed->slug, 'base_price' => 150])]])
            ->assertSessionHasNoErrors();

        $restored = $removed->fresh();
        $this->assertFalse($restored->trashed());
        $this->assertEquals(150, $restored->base_price);
    }

    public function test_a_row_cannot_take_over_another_products_variation(): void
    {
        $product = $this->product();
        $other = $this->variationOf($this->product());

        $this->update($product, ['variations' => [$this->row(['id' => $other->id])]])->assertSessionHasErrors('variations.0.id');
    }

    public function test_rows_keep_only_their_own_fields(): void
    {
        $product = $this->product();
        $parentOfOther = $this->product();

        $this->update($product, ['variations' => [$this->row(['name' => 'أحمر', 'parent_id' => $parentOfOther->id, 'created_by' => 99999])]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $parentOfOther->variations()->count());
        $this->assertSame(1, $product->variations()->count());
    }

    public function test_edit_saves_branches(): void
    {
        [$branch] = $this->catalog();
        $other = Branch::factory()->create();
        $product = $this->product();
        $product->branches()->sync([$branch->id]);

        $this->page('/products/'.$product->id.'/edit')->assertInertia(fn (Assert $page) => $page->where('product.branches', [$branch->id]));

        $this->update($product, ['branches' => [$other->id]])->assertSessionHasNoErrors();

        $this->assertSame([$other->id], $product->branches()->pluck('branch_id')->all());
    }

    public function test_add_fires_product_updated_once_and_edit_does_not(): void
    {
        Event::fake([ProductUpdated::class]);

        $this->store()->assertSessionHasNoErrors();
        Event::assertDispatchedTimes(ProductUpdated::class, 1);

        $this->update($this->latestParent(), ['name' => 'تعديل'])->assertSessionHasNoErrors();
        Event::assertDispatchedTimes(ProductUpdated::class, 1);
    }

    // ---------------------------------------------------------------- nested validation

    public function test_a_nested_error_comes_back_on_its_row_and_field(): void
    {
        [, , $addon, $o1] = $this->catalog();

        $this->store([
            'variations' => [
                $this->row(['name' => 'أحمر']),
                $this->row(['name' => 'أزرق', 'base_price' => '', 'category_id' => []]),
            ],
            'addons' => [['addon_id' => $addon->id, 'allowed_options' => [['addon_option_id' => $o1->id, 'price' => 'abc']]]],
        ])->assertSessionHasErrors([
            'variations.1.base_price',
            'variations.1.category_id',
            'addons.0.allowed_options.0.price',
        ])->assertSessionDoesntHaveErrors(['variations.0.base_price']);

        $this->assertSame(0, Product::query()->count());
    }

    public function test_an_option_must_belong_to_its_addon_and_appear_once(): void
    {
        [, , $addon, $o1] = $this->catalog();
        $foreign = AddonOption::factory()->for(Addon::factory())->create();

        $this->store(['addons' => [['addon_id' => $addon->id, 'allowed_options' => [['addon_option_id' => $foreign->id]]]]])
            ->assertSessionHasErrors('addons.0.allowed_options.0.addon_option_id');

        $this->store(['addons' => [['addon_id' => $addon->id, 'allowed_options' => [['addon_option_id' => $o1->id], ['addon_option_id' => $o1->id]]]]])
            ->assertSessionHasErrors('addons.0.allowed_options.1.addon_option_id');

        $this->store(['addons' => [['addon_id' => $addon->id], ['addon_id' => $addon->id]]])
            ->assertSessionHasErrors('addons.1.addon_id');
    }

    // ---------------------------------------------------------------- media

    public function test_images_and_gallery_of_the_product_and_a_variation(): void
    {
        $this->store([
            'gallery' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'variations' => [$this->row(['image' => UploadedFile::fake()->image('v.jpg'), 'gallery' => [UploadedFile::fake()->image('g.jpg')]])],
        ])->assertSessionHasNoErrors();

        $product = $this->latestParent();
        $this->assertCount(2, $product->getMedia('product_gallery'));

        $variation = $product->variations()->firstOrFail();
        $this->assertTrue($variation->hasMedia('product'));
        $this->assertCount(1, $variation->getMedia('product_gallery'));

        $this->page('/products/'.$product->id.'/edit')->assertInertia(fn (Assert $page) => $page
            ->has('product.images', 1)
            ->has('product.gallery_images', 2)
            ->has('product.variations.0.images', 1));
    }

    public function test_removing_and_reordering_saved_images(): void
    {
        $this->store(['gallery' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')]])
            ->assertSessionHasNoErrors();

        $product = $this->latestParent();
        [$a, $b, $c] = $product->getMedia('product_gallery')->modelKeys();
        $image = $product->getFirstMedia('product')->id;

        $this->update($product, ['removed_image' => [$image], 'removed_gallery' => [$a], 'gallery_order' => [$c, $b]])->assertSessionHasNoErrors();

        $product = Product::query()->findOrFail($product->id);
        $this->assertFalse($product->hasMedia('product'));
        $this->assertSame([$c, $b], $product->getMedia('product_gallery')->modelKeys());
    }

    public function test_removing_media_never_reaches_another_product(): void
    {
        $other = $this->product();
        $media = $other->addMedia(UploadedFile::fake()->image('o.jpg'))->toMediaCollection('product', 'items');

        $this->update($this->product(), ['removed_image' => [$media->id], 'removed_gallery' => [$media->id]])->assertSessionHasNoErrors();

        $this->assertTrue(Product::query()->findOrFail($other->id)->hasMedia('product'));
    }

    // ---------------------------------------------------------------- ProductStatusTest and the table's inline edits

    public function test_draft_chosen_on_add_applies_to_variations(): void
    {
        $this->store(['status' => ProductStatus::DRAFT->value, 'variations' => [$this->row(), $this->row()]])->assertSessionHasNoErrors();

        $parent = $this->latestParent();
        $this->assertSame(ProductStatus::DRAFT, $parent->status);
        $this->assertCount(2, $parent->variations);
        $parent->variations->each(fn (Product $v) => $this->assertSame(ProductStatus::DRAFT, $v->status));
    }

    public function test_status_change_on_edit_cascades(): void
    {
        $parent = $this->product();
        $variation = $this->variationOf($parent);

        $this->update($parent, [
            'status' => ProductStatus::DRAFT->value,
            'variations' => [$this->row(['id' => $variation->id, 'name' => 'متغير'])],
        ])->assertSessionHasNoErrors();

        $this->assertSame(ProductStatus::DRAFT, $parent->fresh()->status);
        $this->assertSame(ProductStatus::DRAFT, $variation->fresh()->status);
    }

    public function test_variation_added_on_edit_gets_parent_status(): void
    {
        $parent = $this->product(['status' => ProductStatus::DRAFT]);

        $this->update($parent, ['status' => ProductStatus::DRAFT->value, 'variations' => [$this->row()]])->assertSessionHasNoErrors();

        $this->assertSame(ProductStatus::DRAFT, $parent->variations()->firstOrFail()->status);
    }

    public function test_status_change_from_the_table_cascades(): void
    {
        $parent = $this->product();
        $variation = $this->variationOf($parent);

        $this->actingAs($this->staff)->from($this->url('/products'))
            ->put($this->url('/products/'.$parent->id.'/status'), ['status' => ProductStatus::DRAFT->value])
            ->assertRedirect($this->url('/products'));

        $this->assertSame(ProductStatus::DRAFT, $parent->fresh()->status);
        $this->assertSame(ProductStatus::DRAFT, $variation->fresh()->status);

        $this->actingAs($this->staff)->put($this->url('/products/'.$parent->id.'/status'), ['status' => ProductStatus::ARCHIVED->value])->assertSessionHasErrors('status');
    }

    public function test_the_active_toggle_from_the_table(): void
    {
        $product = $this->product();

        $this->actingAs($this->staff)->put($this->url('/products/'.$product->id.'/active'), ['active' => false])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $product->fresh()->is_active);

        $this->actingAs($this->staff)->put($this->url('/products/'.$product->id.'/active'), ['active' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, (int) $product->fresh()->is_active);
    }

    // ---------------------------------------------------------------- delete

    public function test_delete_is_permanent_and_takes_the_variations_with_it(): void
    {
        $product = $this->product();
        $variation = $this->variationOf($product);

        $this->actingAs($this->staff)->delete($this->url('/products/'.$product->id))->assertSessionHasNoErrors();

        $this->assertNull(Product::withTrashed()->find($product->id));
        $this->assertNull(Product::withTrashed()->find($variation->id));
    }

    public function test_bulk_delete(): void
    {
        $one = $this->product();
        $two = $this->product();
        $kept = $this->product();
        $variation = $this->variationOf($one);

        $this->actingAs($this->staff)->delete($this->url('/products'), ['ids' => [$one->id, $two->id]])->assertSessionHasNoErrors();

        $this->assertNull(Product::withTrashed()->find($one->id));
        $this->assertNull(Product::withTrashed()->find($two->id));
        $this->assertNull(Product::withTrashed()->find($variation->id));
        $this->assertNotNull($kept->fresh());

        $this->actingAs($this->staff)->delete($this->url('/products'), ['ids' => ['abc']])->assertSessionHasErrors('ids.0');
    }

    public function test_info(): void
    {
        $this->actingAs($this->staff)->getJson($this->url('/products/'.$this->product()->id.'/info'))->assertOk()->assertJsonStructure(['activities']);
    }

    // ---------------------------------------------------------------- barcode labels

    public function test_the_barcode_dialog_lists_the_product_and_its_variations(): void
    {
        $product = $this->product(['name' => 'قميص', 'sku' => 'SKU-ONE', 'barcode' => null]);
        $variation = $this->variationOf($product, ['barcode' => '999-ABC']);

        $this->actingAs($this->staff)->getJson($this->url('/products/'.$product->id.'/barcodes'))
            ->assertOk()
            ->assertJsonPath('rows.0.id', $product->id)
            ->assertJsonPath('rows.0.code', 'SKU-ONE')
            ->assertJsonPath('rows.1.id', $variation->id)
            ->assertJsonPath('rows.1.code', '999-ABC');
    }

    public function test_barcode_pdf_downloads_with_arabic_names_and_the_label_size(): void
    {
        $product = $this->product(['name' => 'قميص أزرق', 'sku' => 'SKU-ONE']);
        $this->variationOf($product, ['name' => 'أحمر']);

        $response = $this->actingAs($this->staff)
            ->get($this->url('/products/'.$product->id.'/barcodes/pdf?quantities['.$product->id.']=2'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $pdf = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('DejaVuSans', $pdf, 'the embedded font that can draw Arabic');
        // Two of the product and one of the variation, the default.
        $this->assertSame(3, preg_match_all('#/Type /Page\b(?!s)#', $pdf));
        $this->assertMatchesRegularExpression('#/MediaBox \[0 0 612\.\d+ 215\.\d+\]#', $pdf, '216 x 76 mm, wide');
    }

    public function test_barcode_pdf_refuses_no_labels_and_too_many(): void
    {
        $product = $this->product();

        $this->actingAs($this->staff)->get($this->url('/products/'.$product->id.'/barcodes/pdf?quantities['.$product->id.']=0'))->assertStatus(422);
        $this->actingAs($this->staff)->get($this->url('/products/'.$product->id.'/barcodes/pdf?quantities['.$product->id.']=1001'))->assertSessionHasErrors('quantities.'.$product->id);
    }

    // ---------------------------------------------------------------- branch pricing

    public function test_branch_pricing_page_and_save(): void
    {
        $branch = Branch::factory()->create();
        $product = $this->product();
        $variation = $this->variationOf($product);
        BranchPrice::query()->create(['product_id' => $product->id, 'branch_id' => $branch->id, 'price' => 55]);

        $this->page('/products/'.$product->id.'/branch-pricings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Product/BranchPricings')
            ->has('rows', 2)
            ->has('branches', 1)
            ->where('prices.'.$product->id.'.'.$branch->id, '55.00')
            ->where('prices.'.$variation->id.'.'.$branch->id, ''));

        $this->actingAs($this->staff)->put($this->url('/products/'.$product->id.'/branch-pricings'), [
            'prices' => [
                $product->id => [$branch->id => ''],
                $variation->id => [$branch->id => 70],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertNull(BranchPrice::query()->where('product_id', $product->id)->first());
        $this->assertEquals(70, BranchPrice::query()->where('product_id', $variation->id)->value('price'));
    }

    public function test_branch_pricing_validates_and_stays_on_this_product(): void
    {
        $branch = Branch::factory()->create();
        $product = $this->product();
        $other = $this->product();

        $this->actingAs($this->staff)->put($this->url('/products/'.$product->id.'/branch-pricings'), ['prices' => [$product->id => [$branch->id => -1]]])
            ->assertSessionHasErrors('prices.'.$product->id.'.'.$branch->id);

        $this->actingAs($this->staff)->put($this->url('/products/'.$product->id.'/branch-pricings'), ['prices' => [$other->id => [$branch->id => 5]]])
            ->assertSessionHasNoErrors();

        $this->assertNull(BranchPrice::query()->where('product_id', $other->id)->first());
    }

    // ---------------------------------------------------------------- lazy loading

    public function test_the_pages_and_a_save_load_no_relation_lazily(): void
    {
        [$branch, , $addon, $o1, , $red, $blue] = $this->catalog();
        $product = $this->product();
        $product->categories()->sync([$this->c1->id]);
        $this->update($product, [
            'branches' => [$branch->id],

            'addons' => [['addon_id' => $addon->id, 'allowed_options' => [['addon_option_id' => $o1->id]]]],
            'variations' => [$this->row(['name' => 'أحمر', 'attribute_values' => [$red->id]])],
        ])->assertSessionHasNoErrors();


        $lazy = [];
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) use (&$lazy): void {
            $lazy[] = $model::class.'::'.$relation;
        });

        $this->page('/products')->assertOk();
        $this->page('/products/add')->assertOk();
        $this->page('/products/'.$product->id.'/edit')->assertOk();
        $this->page('/products/'.$product->id.'/branch-pricings')->assertOk();
        $this->actingAs($this->staff)->getJson($this->url('/products/'.$product->id.'/barcodes'))->assertOk();
        $this->update($product, ['variations' => [$this->row(['name' => 'أزرق', 'attribute_values' => [$blue->id]])]])->assertSessionHasNoErrors();

        // A row with no name: the service builds it from the attribute values' names.
        app(\Modules\Items\Services\ProductVariations::class)->sync($product->fresh(), [
            ['slug' => 'no-name-'.Str::lower(Str::random(4)), 'base_price' => 5, 'attribute_values' => [$red->id, $blue->id]],
        ], 'ar', $this->staff->id);

        $this->assertSame([], array_values(array_unique($lazy)));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array{0: Branch, 1: Label, 2: Addon, 3: AddonOption, 4: AddonOption, 5: AttributeValue, 6: AttributeValue}
     */
    private function catalog(): array
    {
        $addon = Addon::factory()->create();
        [$o1, $o2] = AddonOption::factory()->count(2)->for($addon)->create()->all();
        [$red, $blue] = AttributeValue::factory()->count(2)->for(Attribute::factory()->state(['type' => 3]))->create()->all();

        return [Branch::factory()->create(), Label::factory()->create(['name' => 'عرض']), $addon, $o1, $o2, $red, $blue];
    }

    private function product(array $attributes = []): Product
    {
        return Product::query()->create([
            'name' => 'منتج',
            'slug' => 'product-'.Str::lower(Str::random(8)),
            'base_price' => 100,
            'status' => ProductStatus::PUBLISHED,
            ...$attributes,
        ]);
    }

    private function variationOf(Product $parent, array $attributes = []): Product
    {
        return $this->product(['name' => 'متغير', 'parent_id' => $parent->id, 'status' => $parent->status, ...$attributes]);
    }

    private function latestParent(): Product
    {
        return Product::query()->with('translations')->whereNull('parent_id')->latest('id')->firstOrFail();
    }

    private function fields(array $overrides = []): array
    {
        return [
            'name' => 'منتج',
            'slug' => 'product-'.Str::lower(Str::random(8)),
            'status' => ProductStatus::PUBLISHED->value,
            'base_price' => 100,
            'category_id' => [$this->c1->id],
            'description' => '<p>وصف</p>',
            'is_featured' => false,
            'is_digital' => false,
            ...$overrides,
        ];
    }

    private function row(array $overrides = []): array
    {
        return [
            'name' => 'متغير '.Str::random(4),
            'base_price' => 100,
            'category_id' => [$this->c1->id],
            'is_featured' => false,
            'is_digital' => false,
            ...$overrides,
        ];
    }

    private function store(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->post($this->url('/products'), [
            ...$this->fields($overrides),
            'image' => UploadedFile::fake()->image('product.jpg'),
        ]);
    }

    private function update(Product $product, array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->put($this->url('/products/'.$product->id), $this->fields([
            'slug' => $product->slug,
            'base_price' => $product->base_price,
            'status' => $product->status->value,
            ...$overrides,
        ]));
    }

    private function page(string $path): TestResponse
    {
        return $this->actingAs($this->staff)->get($this->url($path));
    }

    private function url(string $path): string
    {
        return $this->tenantUrl('/ar/admin'.$path);
    }
}
