<?php

namespace Tests\Feature\Items;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Items\Entities\Attribute;
use Modules\Items\Entities\AttributeValue;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\AttributeType;
use Tests\DatabaseTestCase;

class AttributePagesTest extends DatabaseTestCase
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
        $attribute = Attribute::factory()->create(['name' => 'لون', 'slug' => 'color', 'type' => AttributeType::COLOR->value]);

        $this->page('/attributes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/Attribute/Index')
                ->where('table.meta.total', 1)
                ->where('table.data.0.id', $attribute->id)
                ->where('table.data.0.type', AttributeType::COLOR->label())
                ->has('types', 4)
                ->missing('table.data.0.created_by'));
    }

    public function test_search_finds_a_slug_in_another_letter_case_and_a_name(): void
    {
        Attribute::factory()->create(['name' => 'لون', 'slug' => 'color']);
        Attribute::factory()->create(['name' => 'مقاس', 'slug' => 'size']);

        $this->page('/attributes?filter[search]=COL')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'color'));

        $this->page('/attributes?filter[search]=مقا')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'size'));
    }

    public function test_each_filter_and_malformed_values(): void
    {
        Attribute::factory()->create(['name' => 'لون', 'slug' => 'color', 'type' => AttributeType::COLOR->value]);
        Attribute::factory()->create(['name' => 'مقاس', 'slug' => 'size', 'type' => AttributeType::SELECT->value]);

        $this->page('/attributes?filter[type]='.AttributeType::COLOR->value)->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'color'));

        $this->page('/attributes?filter[name]=مقا')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.slug', 'size'));

        $this->page('/attributes?filter[created_at_from]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));

        $this->page('/attributes?filter[type]=abc')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 2));
        $this->page('/attributes?filter[created_at_to]=nope')->assertOk();
    }

    public function test_sorting_by_the_translated_name_and_type(): void
    {
        Attribute::factory()->create(['name' => 'بقالة', 'slug' => 'b', 'type' => 3]);
        Attribute::factory()->create(['name' => 'أدوات', 'slug' => 'a', 'type' => 1]);

        $this->page('/attributes?sort=name')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.slug', 'a'));
        $this->page('/attributes?sort=-type')->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.data.0.slug', 'b'));
    }

    public function test_adding_saves_the_attribute_and_its_values_in_order(): void
    {
        $this->page('/attributes/add')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Items/Attribute/Add')
            ->where('colorType', AttributeType::COLOR->value));

        $this->actingAs($this->staff)
            ->post($this->siteUrl('/ar/admin/attributes'), $this->payload(['values' => [
                ['name' => 'أحمر', 'description' => 'غامق', 'color_code' => '#ff0000'],
                ['name' => 'أزرق', 'description' => '', 'color_code' => '#0000ff'],
            ]]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $attribute = Attribute::query()->where('slug', 'color')->firstOrFail();

        $this->assertSame('لون', $attribute->translate('ar')->name);
        $this->assertSame($this->staff->id, $attribute->created_by);
        $this->assertSame(AttributeType::COLOR->value, (int) $attribute->type);

        $values = $attribute->attributeValues()->get();

        $this->assertSame(['#ff0000', '#0000ff'], $values->pluck('color_code')->all());
        $this->assertSame([0, 1], $values->pluck('sort_order')->map(fn ($order) => (int) $order)->all());
        $this->assertSame('أحمر', $values[0]->translate('ar')->name);
        $this->assertSame('غامق', $values[0]->translate('ar')->description);
        $this->assertSame($this->staff->id, $values[0]->created_by);
    }

    public function test_only_a_color_attribute_keeps_colors(): void
    {
        $this->actingAs($this->staff)
            ->post($this->siteUrl('/ar/admin/attributes'), $this->payload([
                'type' => AttributeType::SELECT->value,
                'values' => [['name' => 'كبير', 'color_code' => '#ff0000']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Attribute::query()->where('slug', 'color')->firstOrFail()->attributeValues()->first()->color_code);
    }

    public function test_validation_errors_are_keyed_by_position_in_the_list(): void
    {
        Attribute::factory()->create(['slug' => 'taken']);

        $this->actingAs($this->staff)
            ->post($this->siteUrl('/ar/admin/attributes'), $this->payload([
                'name' => '',
                'slug' => 'taken',
                'type' => 99,
                'values' => [
                    ['name' => 'صحيح', 'color_code' => '#000000'],
                    ['name' => '', 'color_code' => 'red;background:url(x)', 'description' => str_repeat('x', 256)],
                ],
            ]))
            ->assertSessionHasErrors(['name', 'slug', 'type', 'values.1.name', 'values.1.color_code', 'values.1.description'])
            ->assertSessionDoesntHaveErrors(['values.0.name']);
    }

    public function test_a_slug_used_by_a_category_is_free_for_an_attribute(): void
    {
        Category::factory()->create(['slug' => 'color']);

        $this->actingAs($this->staff)
            ->post($this->siteUrl('/ar/admin/attributes'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Attribute::query()->where('slug', 'color')->first());
    }

    public function test_the_edit_page_shows_the_values_in_their_order(): void
    {
        $attribute = Attribute::factory()->create(['name' => 'لون', 'slug' => 'color', 'type' => AttributeType::COLOR->value]);
        $second = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'name' => 'ثاني', 'sort_order' => 2, 'color_code' => '#222222']);
        $first = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'name' => 'أول', 'sort_order' => 1]);

        $this->page('/attributes/'.$attribute->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Items/Attribute/Edit')
                ->where('attribute.name', 'لون')
                ->where('attribute.type', AttributeType::COLOR->value)
                ->where('attribute.values.0.id', $first->id)
                ->where('attribute.values.0.name', 'أول')
                ->where('attribute.values.1.id', $second->id)
                ->where('attribute.values.1.color_code', '#222222')
                ->missing('attribute.created_by'));
    }

    public function test_editing_updates_the_same_rows_adds_and_deletes_values_and_keeps_the_creator(): void
    {
        $attribute = Attribute::factory()->create(['slug' => 'color', 'type' => AttributeType::COLOR->value, 'created_by' => null]);
        $keep = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'name' => 'قديم', 'sort_order' => 0]);
        $keep->translateOrNew('en')->name = 'Old';
        $keep->save();
        $drop = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'sort_order' => 1]);
        $count = Attribute::query()->count();

        $this->actingAs($this->staff)
            ->put($this->siteUrl('/ar/admin/attributes/'.$attribute->id), $this->payload(['values' => [
                ['name' => 'جديد', 'color_code' => '#00ff00'],
                ['id' => $keep->id, 'name' => 'معدل', 'color_code' => '#111111'],
            ]]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $values = $attribute->attributeValues()->get();

        $this->assertSame($count, Attribute::query()->count());
        $this->assertNull(AttributeValue::query()->find($drop->id));
        $this->assertCount(2, $values);
        $this->assertSame('جديد', $values[0]->translate('ar')->name);
        $this->assertSame($keep->id, $values[1]->id);
        $this->assertSame('معدل', $values[1]->translate('ar')->name);
        $this->assertSame('Old', $values[1]->translate('en')->name);
        $this->assertSame('#111111', $values[1]->color_code);
        $this->assertNull($attribute->fresh()->created_by);
    }

    public function test_a_request_without_values_leaves_them_alone(): void
    {
        $attribute = Attribute::factory()->create(['slug' => 'color']);
        $value = AttributeValue::factory()->create(['attribute_id' => $attribute->id]);

        $payload = $this->payload();
        unset($payload['values']);

        $this->actingAs($this->staff)->put($this->siteUrl('/ar/admin/attributes/'.$attribute->id), $payload)->assertSessionHasNoErrors();

        $this->assertNotNull(AttributeValue::query()->find($value->id));
    }

    public function test_a_value_of_another_attribute_cannot_be_taken_over(): void
    {
        $attribute = Attribute::factory()->create(['slug' => 'color']);
        $foreign = AttributeValue::factory()->create(['name' => 'غريب']);

        $this->actingAs($this->staff)
            ->put($this->siteUrl('/ar/admin/attributes/'.$attribute->id), $this->payload(['values' => [['id' => $foreign->id, 'name' => 'مسروق']]]))
            ->assertSessionHasErrors('values.0.id');

        $this->assertSame('غريب', $foreign->fresh()->translate('ar')->name);
    }

    public function test_the_drag_route_saves_the_order(): void
    {
        $attribute = Attribute::factory()->create();
        $a = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'sort_order' => 0]);
        $b = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'sort_order' => 1]);
        $c = AttributeValue::factory()->create(['attribute_id' => $attribute->id, 'sort_order' => 2]);

        $this->actingAs($this->staff)
            ->put($this->siteUrl('/ar/admin/attributes/'.$attribute->id.'/values/order'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame([$c->id, $a->id, $b->id], $attribute->attributeValues()->pluck('id')->all());
    }

    public function test_the_drag_route_refuses_a_value_of_another_attribute(): void
    {
        $attribute = Attribute::factory()->create();
        $foreign = AttributeValue::factory()->create(['sort_order' => 7]);

        $this->actingAs($this->staff)
            ->put($this->siteUrl('/ar/admin/attributes/'.$attribute->id.'/values/order'), ['ids' => [$foreign->id]])
            ->assertSessionHasErrors('ids.0');

        $this->assertSame(7, (int) $foreign->fresh()->sort_order);
    }

    public function test_delete_and_bulk_delete(): void
    {
        $one = Attribute::factory()->create();
        $value = AttributeValue::factory()->create(['attribute_id' => $one->id]);
        $two = Attribute::factory()->create();
        $three = Attribute::factory()->create();

        $this->actingAs($this->staff)->delete($this->siteUrl('/ar/admin/attributes/'.$one->id))->assertRedirect();
        $this->actingAs($this->staff)->delete($this->siteUrl('/ar/admin/attributes'), ['ids' => [$two->id, $three->id]])->assertRedirect();

        $this->assertSame(0, Attribute::query()->count());
        $this->assertNull(AttributeValue::query()->find($value->id));
    }

    public function test_info_returns_explicit_fields(): void
    {
        $attribute = Attribute::factory()->create();

        $this->page('/attributes/'.$attribute->id.'/info')->assertOk()->assertJsonStructure(['created_by', 'created_at', 'updated_at', 'activities']);
    }

    public function test_a_bad_id_is_a_not_found_not_a_server_error(): void
    {
        $this->page('/attributes/abc/edit')->assertNotFound();
        $this->page('/attributes/999999/edit')->assertNotFound();
    }

    public function test_every_action_needs_its_permission(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer-'.Str::random(10).'@items.test',
            'password' => 'secret-password',
        ])->givePermissionTo('Attribute View List');

        $attribute = Attribute::factory()->create();
        $base = $this->siteUrl('/ar/admin/attributes');

        $this->actingAs($viewer)->get($base)->assertOk();
        $this->actingAs($viewer)->get($base.'/add')->assertForbidden();
        $this->actingAs($viewer)->post($base, $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($base.'/'.$attribute->id.'/edit')->assertForbidden();
        $this->actingAs($viewer)->get($base.'/'.$attribute->id.'/info')->assertForbidden();
        $this->actingAs($viewer)->put($base.'/'.$attribute->id, $this->payload())->assertForbidden();
        $this->actingAs($viewer)->put($base.'/'.$attribute->id.'/values/order', ['ids' => []])->assertForbidden();
        $this->actingAs($viewer)->delete($base.'/'.$attribute->id)->assertForbidden();
        $this->actingAs($viewer)->delete($base, ['ids' => [$attribute->id]])->assertForbidden();

        $this->assertNotNull($attribute->fresh());
    }

    public function test_guests_go_to_the_login_page(): void
    {
        $this->get($this->siteUrl('/ar/admin/attributes'))->assertRedirect();
    }

    private function page(string $path)
    {
        return $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin'.$path));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'لون',
            'slug' => 'color',
            'type' => AttributeType::COLOR->value,
            'description' => 'وصف',
            'values' => [],
            ...$overrides,
        ];
    }
}
