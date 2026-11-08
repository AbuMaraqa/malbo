<?php

namespace Tests\Feature\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;
use Modules\Items\Repositories\Contracts\CategoryRepositoryInterface;
use Modules\Items\Services\CategoryService;
use Tests\TenantTestCase;

class CategoryServiceTest extends TenantTestCase
{
    private CategoryService $service;

    private CategoryRepositoryInterface $categories;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->service = app(CategoryService::class);
        $this->categories = app(CategoryRepositoryInterface::class);
        $this->user = User::forceCreate(['name' => 'Staff', 'email' => 'staff-'.Str::random(10).'@items.test', 'password' => 'password']);
    }

    public function test_create_with_image_and_two_locales(): void
    {
        $category = $this->service->create(
            ['slug' => 'c-'.Str::random(6), 'status' => CategoryStatus::ACTIVE, 'sort_order' => 3, 'parent_id' => null],
            ['ar' => ['name' => 'قسم', 'description' => 'وصف'], 'en' => ['name' => 'Category']],
            UploadedFile::fake()->image('category.jpg'),
            $this->user->id,
        );

        $fresh = $category->fresh();
        $this->assertSame('قسم', $fresh->translate('ar')->name);
        $this->assertSame('Category', $fresh->translate('en')->name);
        $this->assertSame($this->user->id, (int) $fresh->created_by);
        $this->assertSame(3, (int) $fresh->sort_order);
        $this->assertCount(1, $fresh->getMedia('category'));
    }

    public function test_update_keeps_created_by_and_the_other_locale(): void
    {
        $category = $this->service->create(['slug' => 'c-'.Str::random(6)], ['ar' => ['name' => 'قسم'], 'en' => ['name' => 'Category']], null, $this->user->id);
        $other = User::forceCreate(['name' => 'Other', 'email' => 'o-'.Str::random(10).'@items.test', 'password' => 'password']);

        $this->service->update($category, ['sort_order' => 7, 'created_by' => $other->id], ['ar' => ['name' => 'قسم معدل']]);

        $fresh = $category->fresh();
        $this->assertSame('قسم معدل', $fresh->translate('ar')->name);
        $this->assertSame('Category', $fresh->translate('en')->name);
        $this->assertSame($this->user->id, (int) $fresh->created_by);
        $this->assertSame(7, (int) $fresh->sort_order);
    }

    public function test_set_active_and_delete(): void
    {
        $category = Category::factory()->create();

        $this->service->setActive($category, false);
        $this->assertSame(CategoryStatus::INACTIVE, $category->fresh()->status);

        $this->service->delete($category);
        $this->assertNull(Category::find($category->id));
    }

    public function test_options_and_active_options(): void
    {
        $active = Category::factory()->create(['name' => 'نشط']);
        $inactive = Category::factory()->create(['name' => 'موقوف', 'status' => CategoryStatus::INACTIVE]);

        $this->assertSame('نشط', $this->categories->options()[$active->id]);
        $this->assertSame('موقوف', $this->categories->options()[$inactive->id]);
        $this->assertArrayHasKey($active->id, $this->categories->activeOptions());
        $this->assertArrayNotHasKey($inactive->id, $this->categories->activeOptions());
        $this->assertContains($active->id, $this->categories->activeIds());
        $this->assertNotContains($inactive->id, $this->categories->activeIds());
    }

    public function test_tree_and_find_by_slug(): void
    {
        $parent = Category::factory()->create(['sort_order' => 1]);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        Category::factory()->create(['status' => CategoryStatus::INACTIVE]);

        $tree = $this->categories->tree();
        $root = $tree->firstWhere('id', $parent->id);
        $this->assertNotNull($root);
        $this->assertTrue($root->children->contains('id', $child->id));
        $this->assertTrue($tree->every(fn (Category $c) => $c->status === CategoryStatus::ACTIVE));

        $this->assertTrue($this->categories->findBySlug($child->slug)->is($child));
        $this->assertNull($this->categories->findBySlug('no-such-slug'));
    }

    public function test_changed_since(): void
    {
        $old = Category::factory()->create();
        $old->forceFill(['updated_at' => '2020-01-01 00:00:00'])->saveQuietly();
        $new = Category::factory()->create();

        $ids = $this->categories->changedSince('2021-01-01 00:00:00')->pluck('id');

        $this->assertTrue($ids->contains($new->id));
        $this->assertFalse($ids->contains($old->id));
    }
}
