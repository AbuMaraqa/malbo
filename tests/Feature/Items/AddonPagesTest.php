<?php

namespace Tests\Feature\Items;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Addon;
use Modules\Items\Entities\AddonOption;
use Modules\Items\Enums\AddonType;
use Tests\TenantTestCase;

class AddonPagesTest extends TenantTestCase
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
        $addon = Addon::factory()->create(['name' => 'صوص', 'description' => 'وصف', 'type' => AddonType::CHECKBOX]);

        $this->page('/addons')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/Addon/Index')
                ->where('table.meta.total', 1)
                ->where('table.data.0.id', $addon->id)
                ->where('table.data.0.name', 'صوص')
                ->where('table.data.0.description', 'وصف')
                ->where('table.data.0.type', AddonType::CHECKBOX->getLabel())
                ->has('types', 2)
                ->missing('table.data.0.created_by'));
    }

    public function test_search_finds_a_name_in_another_letter_case(): void
    {
        Addon::factory()->create(['name' => 'Cheese']);
        Addon::factory()->create(['name' => 'Sauce']);

        $this->page('/addons?filter[search]=cheE')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.name', 'Cheese'));
    }

    public function test_each_filter_and_malformed_values(): void
    {
        Addon::factory()->create(['name' => 'صوص', 'type' => AddonType::CHECKBOX]);
        Addon::factory()->create(['name' => 'جبنة', 'type' => AddonType::RADIO]);

        $this->page('/addons?filter[type]='.AddonType::CHECKBOX->value)->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.name', 'صوص'));

        $this->page('/addons?filter[name]=جبن')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.name', 'جبنة'));

        $this->page('/addons?filter[created_at_from]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));

        $this->page('/addons?filter[type]=abc')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 2));
    }

    public function test_sorting_by_the_translated_name(): void
    {
        Addon::factory()->create(['name' => 'بقالة']);
        Addon::factory()->create(['name' => 'أدوات']);

        $this->page('/addons?sort=name')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.name', 'أدوات'));
    }

    public function test_adding_saves_the_addon_options_and_their_images(): void
    {
        $this->page('/addons/add')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Items/Addon/Add'));

        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/addons'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $addon = Addon::query()->latest('id')->firstOrFail();

        $this->assertSame('صوص', $addon->translate('ar')->name);
        $this->assertSame(AddonType::CHECKBOX, $addon->type);
        $this->assertSame($this->staff->id, $addon->created_by);

        $option = $addon->addonOptions()->firstOrFail();

        $this->assertSame('حار', $option->translate('ar')->name);
        $this->assertSame(2.5, (float) $option->price);
        $this->assertTrue((bool) $option->is_default);
        $this->assertFalse((bool) $option->is_quantifiable);
        $this->assertTrue($option->fresh()->hasMedia('addon_option'));
    }

    public function test_validation_errors_are_keyed_by_position_in_the_list(): void
    {
        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/addons'), $this->payload([
                'name' => '',
                'type' => 99,
                'options' => [
                    $this->option(),
                    ['name' => '', 'price' => 'free'],
                    $this->option(['price' => -1, 'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]),
                ],
            ]))
            ->assertSessionHasErrors(['name', 'type', 'options.1.name', 'options.1.price', 'options.1.image', 'options.2.price', 'options.2.image'])
            ->assertSessionDoesntHaveErrors(['options.0.name', 'options.0.image']);
    }

    public function test_the_edit_page_shows_the_options_with_their_images(): void
    {
        $addon = Addon::factory()->create(['name' => 'صوص', 'type' => AddonType::CHECKBOX]);
        $option = $this->savedOption($addon, 'حار');

        $this->page('/addons/'.$addon->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/Addon/Edit')
                ->where('addon.name', 'صوص')
                ->where('addon.type', AddonType::CHECKBOX->value)
                ->where('addon.options.0.id', $option->id)
                ->where('addon.options.0.name', 'حار')
                ->where('addon.options.0.is_default', false)
                ->has('addon.options.0.images', 1)
                ->missing('addon.created_by'));
    }

    public function test_editing_updates_the_same_rows_adds_and_removes_options(): void
    {
        $addon = Addon::factory()->create(['name' => 'صوص']);
        $keep = $this->savedOption($addon, 'قديم');
        $drop = $this->savedOption($addon, 'يحذف');
        $media = $keep->getFirstMedia('addon_option')->id;
        $count = Addon::query()->count();

        $this->actingAs($this->staff)
            ->post($this->tenantUrl('/ar/admin/addons/'.$addon->id), [...$this->payload(['name' => 'معدل', 'options' => [
                ['id' => $keep->id, 'name' => 'معدل', 'price' => 9, 'is_default' => '1', 'is_quantifiable' => '1'],
                $this->option(['name' => 'جديد']),
            ]]), '_method' => 'put'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($count, Addon::query()->count());
        $this->assertSame('معدل', $addon->fresh()->translate('ar')->name);

        $this->assertSame(9.0, (float) $keep->fresh()->price);
        $this->assertTrue((bool) $keep->fresh()->is_quantifiable);
        $this->assertSame('معدل', $keep->fresh()->translate('ar')->name);
        $this->assertSame($media, $keep->fresh()->getFirstMedia('addon_option')->id);

        $this->assertNull(AddonOption::query()->find($drop->id));
        $this->assertNotNull(AddonOption::withTrashed()->find($drop->id));
        $this->assertSame(2, $addon->addonOptions()->count());
    }

    public function test_a_new_option_image_replaces_the_old_one_and_removing_needs_a_new_one(): void
    {
        $addon = Addon::factory()->create();
        $option = $this->savedOption($addon, 'قديم');
        $old = $option->getFirstMedia('addon_option')->id;
        $url = $this->tenantUrl('/ar/admin/addons/'.$addon->id);

        $this->actingAs($this->staff)
            ->put($url, $this->payload(['options' => [['id' => $option->id, 'name' => 'قديم', 'price' => 1, 'removed_image' => [$old]]]]))
            ->assertSessionHasErrors('options.0.image');

        $this->actingAs($this->staff)
            ->put($url, $this->payload(['options' => [$this->option(['id' => $option->id, 'removed_image' => [$old]])]]))
            ->assertSessionHasNoErrors();

        $media = $option->fresh()->getMedia('addon_option');

        $this->assertCount(1, $media);
        $this->assertNotSame($old, $media->first()->id);
    }

    public function test_an_option_of_another_addon_cannot_be_taken_over(): void
    {
        $addon = Addon::factory()->create();
        $foreign = $this->savedOption(Addon::factory()->create(), 'غريب');

        $this->actingAs($this->staff)
            ->put($this->tenantUrl('/ar/admin/addons/'.$addon->id), $this->payload(['options' => [$this->option(['id' => $foreign->id, 'name' => 'مسروق'])]]))
            ->assertSessionHasErrors('options.0.id');

        $this->assertSame('غريب', $foreign->fresh()->translate('ar')->name);
    }

    public function test_delete_and_bulk_delete_leave_the_rows_for_orders_already_placed(): void
    {
        $one = Addon::factory()->create();
        $two = Addon::factory()->create();
        $three = Addon::factory()->create();

        $this->actingAs($this->staff)->delete($this->tenantUrl('/ar/admin/addons/'.$one->id))->assertRedirect();
        $this->actingAs($this->staff)->delete($this->tenantUrl('/ar/admin/addons'), ['ids' => [$two->id, $three->id]])->assertRedirect();

        $this->assertSame(0, Addon::query()->count());
        $this->assertSame(3, Addon::withTrashed()->count());
    }

    public function test_info_returns_explicit_fields(): void
    {
        $addon = Addon::factory()->create();

        $this->page('/addons/'.$addon->id.'/info')->assertOk()->assertJsonStructure(['created_by', 'created_at', 'updated_at', 'activities']);
    }

    public function test_a_bad_id_is_a_not_found_not_a_server_error(): void
    {
        $this->page('/addons/abc/edit')->assertNotFound();
        $this->page('/addons/999999/edit')->assertNotFound();
    }

    public function test_every_action_needs_its_permission(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->givePermissionTo('Addon View List');

        $addon = Addon::factory()->create();
        $base = $this->tenantUrl('/ar/admin/addons');

        $this->actingAs($viewer)->get($base)->assertOk();
        $this->actingAs($viewer)->get($base.'/add')->assertForbidden();
        $this->actingAs($viewer)->post($base, $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($base.'/'.$addon->id.'/edit')->assertForbidden();
        $this->actingAs($viewer)->get($base.'/'.$addon->id.'/info')->assertForbidden();
        $this->actingAs($viewer)->put($base.'/'.$addon->id, $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete($base.'/'.$addon->id)->assertForbidden();
        $this->actingAs($viewer)->delete($base, ['ids' => [$addon->id]])->assertForbidden();

        $this->assertNotNull($addon->fresh());
    }

    public function test_guests_go_to_the_login_page(): void
    {
        $this->get($this->tenantUrl('/ar/admin/addons'))->assertRedirect();
    }

    private function page(string $path)
    {
        return $this->actingAs($this->staff)->get($this->tenantUrl('/ar/admin'.$path));
    }

    private function savedOption(Addon $addon, string $name): AddonOption
    {
        $option = AddonOption::factory()->create(['addon_id' => $addon->id, 'name' => $name]);
        $option->addImage(UploadedFile::fake()->image('option.jpg'));

        return $option->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function option(array $overrides = []): array
    {
        return [
            'name' => 'حار',
            'price' => 2.5,
            'is_default' => '1',
            'is_quantifiable' => '0',
            'description' => 'وصف',
            'image' => UploadedFile::fake()->image('option.jpg'),
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'صوص',
            'type' => AddonType::CHECKBOX->value,
            'description' => 'وصف',
            'options' => [$this->option()],
            ...$overrides,
        ];
    }
}
