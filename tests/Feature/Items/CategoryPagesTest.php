<?php

namespace Tests\Feature\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;
use Tests\TenantTestCase;

/**
 * The category pages, including what core's CategoryStatusTest checks: status and sort order are
 * editable, a new category is active, the table sorts by sort order then id, the status toggle saves.
 */
class CategoryPagesTest extends TenantTestCase
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
        $category = $this->category(sortOrder: 3, name: 'أجهزة');
        $child = $this->category(sortOrder: 1, parent: $category);

        $this->page('/categories')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/Category/Index')
                ->where('table.meta.total', 2)
                ->where('table.query.sort', 'sort_order')
                ->where('table.data.0.id', $child->id)
                ->where('table.data.0.parent', 'أجهزة')
                ->where('table.data.1.parent', null)
                ->where('table.data.1.active', true)
                ->has('parents', 2));
    }

    public function test_index_sorts_by_sort_order_then_id(): void
    {
        $a = $this->category(sortOrder: 2);
        $first = $this->category(sortOrder: 1);
        $b = $this->category(sortOrder: 2);

        $this->page('/categories')->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.id', $first->id)
            ->where('table.data.1.id', $a->id)
            ->where('table.data.2.id', $b->id));
    }

    public function test_a_column_the_user_sorts_by_wins(): void
    {
        $small = $this->category(sortOrder: 1);
        $big = $this->category(sortOrder: 9);

        $this->page('/categories?sort=-sort_order')->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.id', $big->id)
            ->where('table.data.1.id', $small->id));
    }

    public function test_sorting_by_the_translated_name(): void
    {
        $b = $this->category(sortOrder: 1, name: 'بقالة');
        $a = $this->category(sortOrder: 2, name: 'أدوات');

        $this->page('/categories?sort=name')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.id', $a->id)
            ->where('table.data.1.id', $b->id));

        $this->page('/categories?sort=-name')->assertInertia(fn (Assert $page) => $page->where('table.data.0.id', $b->id));
    }

    public function test_search_finds_a_slug_in_another_letter_case_and_a_name(): void
    {
        $category = $this->category(sortOrder: 1, name: 'أجهزة', slug: 'home-tools');
        $this->category(sortOrder: 2);

        $this->page('/categories?filter[search]=HOME-TO')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)
            ->where('table.data.0.id', $category->id));

        $this->page('/categories?filter[search]=أجهز')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1));
    }

    public function test_each_filter(): void
    {
        $parent = $this->category(sortOrder: 5, name: 'أجهزة');
        $child = $this->category(sortOrder: 7, parent: $parent, status: CategoryStatus::INACTIVE);

        $this->page('/categories?filter[status]=0')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $child->id));

        $this->page('/categories?filter[parent_id]='.$parent->id)->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $child->id));

        $this->page('/categories?filter[name]=أجهز')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $parent->id));

        $this->page('/categories?filter[sort_order_from]=6')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $child->id));

        $this->page('/categories?filter[created_at_to]=2000-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
    }

    public function test_a_malformed_filter_is_ignored_not_a_server_error(): void
    {
        $this->category(sortOrder: 1);

        foreach (['status=abc', 'parent_id=abc', 'status=99999999999999', 'created_at_from=nope', 'sort_order_from=x'] as $filter) {
            $this->page('/categories?filter['.str_replace('=', ']=', $filter))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1));
        }
    }

    public function test_add_saves_status_sort_order_translation_and_image(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/categories'), $this->payload(['status' => false, 'sort_order' => 4]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $category = Category::latest('id')->firstOrFail();

        $this->assertSame(CategoryStatus::INACTIVE, $category->status);
        $this->assertSame(4, (int) $category->sort_order);
        $this->assertSame('قسم', $category->translate('ar')->name);
        $this->assertSame($this->staff->id, $category->created_by);
        $this->assertTrue($category->hasMedia('category'));
    }

    public function test_new_category_is_active_by_default(): void
    {
        $payload = $this->payload();
        unset($payload['status'], $payload['sort_order']);

        $this->actingAs($this->staff)->post($this->tenantUrl('/ar/admin/categories'), $payload)->assertRedirect();

        $this->assertSame(CategoryStatus::ACTIVE, Category::latest('id')->firstOrFail()->status);
    }

    public function test_an_empty_slug_is_made_from_the_name(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/categories'), $this->payload(['name' => 'Home Tools', 'slug' => '']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('home-tools', Category::latest('id')->firstOrFail()->slug);
    }

    public function test_validation_errors(): void
    {
        $this->category(sortOrder: 1, slug: 'taken');

        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/categories'), $this->payload(['name' => '', 'slug' => 'taken', 'image' => null, 'parent_id' => 999999]))
            ->assertSessionHasErrors(['name', 'slug', 'image', 'parent_id']);

        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/categories'), $this->payload(['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors('image');
    }

    public function test_the_edit_page_shows_the_current_locale_and_leaves_itself_out_of_the_parent_tree(): void
    {
        $root = $this->category(sortOrder: 1, name: 'جذر');
        $category = $this->category(sortOrder: 2, name: 'قسم', parent: $root);
        $child = $this->category(sortOrder: 3, parent: $category);

        $this->page('/categories/'.$category->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/Category/Edit')
                ->where('category.name', 'قسم')
                ->where('category.parent_id', $root->id)
                ->where('category.status', true)
                ->has('images', 1)
                ->has('parents', 1)
                ->where('parents.0.id', $root->id)
                ->has('parents.0.children', 0)
                ->missing('category.created_by'));

        $this->assertNotNull($child->fresh());
    }

    public function test_the_parent_tree_is_nested_at_any_depth(): void
    {
        $root = $this->category(sortOrder: 1);
        $middle = $this->category(sortOrder: 1, parent: $root);
        $leaf = $this->category(sortOrder: 1, parent: $middle);
        $this->category(sortOrder: 1, status: CategoryStatus::INACTIVE);

        $this->page('/categories/add')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/Category/Add')
                ->has('parents', 1)
                ->where('parents.0.children.0.id', $middle->id)
                ->where('parents.0.children.0.children.0.id', $leaf->id));
    }

    public function test_editing_updates_the_same_category_and_keeps_its_image_when_none_is_sent(): void
    {
        $category = $this->category(sortOrder: 1);
        $count = Category::query()->count();
        $media = $category->getFirstMedia('category');

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/categories/'.$category->id), $this->payload([
                'slug' => $category->slug,
                'sort_order' => 9,
                'status' => false,
                'image' => null,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $category->fresh();

        $this->assertSame($count, Category::query()->count());
        $this->assertSame(9, (int) $fresh->sort_order);
        $this->assertSame(CategoryStatus::INACTIVE, $fresh->status);
        $this->assertSame($media->id, $fresh->getFirstMedia('category')->id);
    }

    public function test_a_new_image_replaces_the_old_one(): void
    {
        $category = $this->category(sortOrder: 1);
        $old = $category->getFirstMedia('category')->id;

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/categories/'.$category->id), $this->payload(['slug' => $category->slug, 'removed_image' => [$old]]))
            ->assertSessionHasNoErrors();

        $media = $category->fresh()->getMedia('category');

        $this->assertCount(1, $media);
        $this->assertNotSame($old, $media->first()->id);
    }

    public function test_removing_the_image_without_a_new_one_is_a_validation_error(): void
    {
        $category = $this->category(sortOrder: 1);

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/categories/'.$category->id), $this->payload([
                'slug' => $category->slug,
                'image' => null,
                'removed_image' => [$category->getFirstMedia('category')->id],
            ]))
            ->assertSessionHasErrors('image');
    }

    public function test_a_category_cannot_move_under_itself_or_its_child(): void
    {
        $category = $this->category(sortOrder: 1);
        $child = $this->category(sortOrder: 2, parent: $category);

        foreach ([$category->id, $child->id] as $parentId) {
            $this->actingAs($this->staff)
                ->put($this->tenantUrl('/ar/admin/categories/'.$category->id), $this->payload(['slug' => $category->slug, 'image' => null, 'parent_id' => $parentId]))
                ->assertSessionHasErrors('parent_id');
        }

        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_status_toggle_from_the_table(): void
    {
        $category = $this->category(sortOrder: 0);

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/categories/'.$category->id.'/status'), ['active' => false])
            ->assertRedirect();

        $this->assertSame(CategoryStatus::INACTIVE, $category->fresh()->status);

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/categories/'.$category->id.'/status'), ['active' => 'nonsense'])
            ->assertSessionHasErrors('active');
    }

    public function test_delete_and_bulk_delete_are_permanent_and_free_the_slug(): void
    {
        $one = $this->category(sortOrder: 1, slug: 'one');
        $two = $this->category(sortOrder: 2, slug: 'two');
        $three = $this->category(sortOrder: 3, slug: 'three');

        $this->actingAs($this->staff)->delete($this->tenantUrl('/ar/admin/categories/'.$one->id))->assertRedirect();
        $this->actingAs($this->staff)->delete($this->tenantUrl('/ar/admin/categories'), ['ids' => [$two->id, $three->id]])->assertRedirect();

        $this->assertSame(0, Category::withTrashed()->whereIn('slug', ['one', 'two', 'three'])->count());
    }

    public function test_deleting_a_parent_keeps_its_children_as_roots(): void
    {
        $parent = $this->category(sortOrder: 1);
        $child = $this->category(sortOrder: 2, parent: $parent);

        $this->actingAs($this->staff)->delete($this->tenantUrl('/ar/admin/categories/'.$parent->id))->assertRedirect();

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_info_returns_explicit_fields_and_the_activity_log(): void
    {
        $category = $this->category(sortOrder: 1);

        $this->page('/categories/'.$category->id.'/info')
            ->assertOk()
            ->assertJsonStructure(['created_by', 'created_at', 'updated_at', 'activities'])
            ->assertJsonPath('activities.0.old', null);
    }

    public function test_a_non_numeric_id_is_a_not_found_not_a_server_error(): void
    {
        $this->page('/categories/abc/edit')->assertNotFound();
        $this->page('/categories/999999/edit')->assertNotFound();
    }

    public function test_every_action_needs_its_permission(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->givePermissionTo('Category View List');

        $category = $this->category(sortOrder: 1);

        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/categories'))->assertOk();
        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/categories/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->tenantUrl('/ar/admin/categories'), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/categories/'.$category->id.'/edit'))->assertForbidden();
        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/categories/'.$category->id.'/info'))->assertForbidden();
        $this->actingAs($viewer)->put($this->tenantUrl('/ar/admin/categories/'.$category->id), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->put($this->tenantUrl('/ar/admin/categories/'.$category->id.'/status'), ['active' => false])->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl('/ar/admin/categories/'.$category->id))->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl('/ar/admin/categories'), ['ids' => [$category->id]])->assertForbidden();

        $this->assertNotNull($category->fresh());
    }

    public function test_guests_go_to_the_login_page(): void
    {
        $this->get($this->tenantUrl('/ar/admin/categories'))->assertRedirect();
    }

    private function page(string $path)
    {
        return $this->actingAs($this->staff)->get($this->tenantUrl('/ar/admin'.$path));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'قسم',
            'slug' => 'cat-'.Str::lower(Str::random(6)),
            'description' => 'وصف',
            'status' => true,
            'sort_order' => 0,
            'image' => UploadedFile::fake()->image('category.jpg'),
            ...$overrides,
        ];
    }

    private function category(int $sortOrder, ?string $name = null, ?string $slug = null, ?Category $parent = null, CategoryStatus $status = CategoryStatus::ACTIVE): Category
    {
        $category = Category::factory()->create([
            'name' => $name ?? 'قسم '.Str::random(4),
            'slug' => $slug ?? 'cat-'.Str::lower(Str::random(6)),
            'status' => $status,
            'sort_order' => $sortOrder,
            'parent_id' => $parent?->id,
        ]);

        $category->addMedia(UploadedFile::fake()->image('category.jpg'))->toMediaCollection('category', 'items');

        return $category;
    }
}
