<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Tag;
use Tests\TenantTestCase;

class TagPagesTest extends TenantTestCase
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
        $tag = Tag::factory()->create(['name' => 'جديد', 'slug' => 'new-arrival', 'text_color' => '#ffffff', 'background_color' => '#123456']);

        $this->page('/tags')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/Tag/Index')
                ->where('table.meta.total', 1)
                ->where('table.data.0.id', $tag->id)
                ->where('table.data.0.name', 'جديد')
                ->where('table.data.0.background_color', '#123456')
                ->where('table.data.0.form.name', 'جديد')
                ->missing('table.data.0.created_by'));
    }

    public function test_search_finds_a_slug_in_another_letter_case_and_a_name(): void
    {
        Tag::factory()->create(['name' => 'جديد', 'slug' => 'new-arrival']);
        Tag::factory()->create(['name' => 'قديم', 'slug' => 'old-stock']);

        $this->page('/tags?filter[search]=NEW-ARR')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'new-arrival'));

        $this->page('/tags?filter[search]=قدي')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'old-stock'));
    }

    public function test_each_filter_and_a_malformed_date(): void
    {
        Tag::factory()->create(['name' => 'جديد', 'slug' => 'new-arrival']);
        Tag::factory()->create(['name' => 'قديم', 'slug' => 'old-stock']);

        $this->page('/tags?filter[name]=جدي')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'new-arrival'));

        $this->page('/tags?filter[created_at_from]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
        $this->page('/tags?filter[created_at_to]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 2));
        $this->page('/tags?filter[created_at_from]=not-a-date')->assertOk();
    }

    public function test_sorting_by_the_translated_name(): void
    {
        Tag::factory()->create(['name' => 'بقالة', 'slug' => 'b']);
        Tag::factory()->create(['name' => 'أدوات', 'slug' => 'a']);

        $this->page('/tags?sort=name')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.slug', 'a'));
        $this->page('/tags?sort=-name')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.slug', 'b'));
    }

    public function test_adding_saves_the_translation_colors_and_creator(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/tags'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $tag = Tag::query()->where('slug', 'new-tag')->firstOrFail();

        $this->assertSame('علامة', $tag->translate('ar')->name);
        $this->assertSame('وصف', $tag->translate('ar')->description);
        $this->assertSame('#ffffff', $tag->text_color);
        $this->assertSame($this->staff->id, $tag->created_by);
    }

    public function test_an_empty_slug_is_made_from_the_name(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/tags'), $this->payload(['name' => 'Best Seller', 'slug' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Tag::query()->where('slug', 'best-seller')->first());
    }

    public function test_validation_errors(): void
    {
        Tag::factory()->create(['slug' => 'taken']);

        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/tags'), $this->payload(['name' => '', 'slug' => 'taken', 'text_color' => 'red;background:url(x)', 'background_color' => '']))
            ->assertSessionHasErrors(['name', 'slug', 'text_color', 'background_color']);
    }

    public function test_editing_updates_the_same_tag_in_the_current_locale_only(): void
    {
        $tag = Tag::factory()->create(['slug' => 'old-slug']);
        $tag->translateOrNew('en')->name = 'English';
        $tag->save();
        $count = Tag::query()->count();
        $creator = $tag->created_by;

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/tags/'.$tag->id), $this->payload(['slug' => 'old-slug', 'name' => 'معدل', 'background_color' => '#111111']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $tag->fresh();

        $this->assertSame($count, Tag::query()->count());
        $this->assertSame('معدل', $fresh->translate('ar')->name);
        $this->assertSame('English', $fresh->translate('en')->name);
        $this->assertSame('#111111', $fresh->background_color);
        $this->assertSame($creator, $fresh->created_by);
    }

    public function test_delete_and_bulk_delete(): void
    {
        $tags = Tag::factory()->count(3)->create();

        $this->actingAs($this->staff)->delete($this->tenantUrl('/ar/admin/tags/'.$tags[0]->id))->assertRedirect();
        $this->actingAs($this->staff)->delete($this->tenantUrl('/ar/admin/tags'), ['ids' => [$tags[1]->id, $tags[2]->id]])->assertRedirect();

        $this->assertSame(0, Tag::query()->count());
    }

    public function test_info_returns_explicit_fields(): void
    {
        $tag = Tag::factory()->create();

        $this->page('/tags/'.$tag->id.'/info')->assertOk()->assertJsonStructure(['created_by', 'created_at', 'updated_at', 'activities']);
    }

    public function test_info_shows_who_changed_what(): void
    {
        $tag = Tag::factory()->create(['background_color' => '#000000', 'created_by' => $this->staff->id]);

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/tags/'.$tag->id), $this->payload(['slug' => $tag->slug, 'background_color' => '#111111']))
            ->assertSessionHasNoErrors();

        $this->page('/tags/'.$tag->id.'/info')
            ->assertOk()
            ->assertJsonPath('created_by', 'Staff')
            ->assertJsonPath('activities.0.causer', 'Staff')
            ->assertJsonPath('activities.0.old.background_color', '#000000')
            ->assertJsonPath('activities.0.new.background_color', '#111111');
    }

    public function test_a_bad_id_is_a_not_found_not_a_server_error(): void
    {
        $this->page('/tags/abc/info')->assertNotFound();
        $this->actingAs($this->staff)->put($this->tenantUrl('/ar/admin/tags/999999'), $this->payload())->assertNotFound();
    }

    public function test_every_action_needs_its_permission(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->givePermissionTo('Tag View List');

        $tag = Tag::factory()->create();

        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/tags'))->assertOk();
        $this->actingAs($viewer)->post($this->tenantUrl('/ar/admin/tags'), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($this->tenantUrl('/ar/admin/tags/'.$tag->id.'/info'))->assertForbidden();
        $this->actingAs($viewer)->put($this->tenantUrl('/ar/admin/tags/'.$tag->id), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl('/ar/admin/tags/'.$tag->id))->assertForbidden();
        $this->actingAs($viewer)->delete($this->tenantUrl('/ar/admin/tags'), ['ids' => [$tag->id]])->assertForbidden();

        $this->assertNotNull($tag->fresh());
    }

    public function test_guests_go_to_the_login_page(): void
    {
        $this->get($this->tenantUrl('/ar/admin/tags'))->assertRedirect();
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
            'name' => 'علامة',
            'slug' => 'new-tag',
            'description' => 'وصف',
            'text_color' => '#ffffff',
            'background_color' => '#000000',
            ...$overrides,
        ];
    }
}
