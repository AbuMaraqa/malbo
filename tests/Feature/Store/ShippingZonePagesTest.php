<?php

namespace Tests\Feature\Store;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Services\SidebarService;
use Modules\Store\Entities\ShippingZone;
use Modules\Store\Settings\StoreShippingSettings;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;
use Tests\Feature\Store\Concerns\MakesShippingZones;

/**
 * Shipping zones: a translated name, a fee, an expected delivery time, a free-delivery threshold,
 * and the cities they deliver to. A city belongs to at most one active zone.
 */
class ShippingZonePagesTest extends DatabaseTestCase
{
    use MakesShippingZones;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['locale' => 'ar']);
        app()->setLocale('ar');

        $this->staff = User::forceCreate([
            'name' => 'Staff',
            'email' => 'staff-'.Str::random(10).'@store.test',
            'password' => 'secret-password',
        ])->assignRole('Super Admin');
    }

    public function test_the_list_is_shaped_for_the_table(): void
    {
        $zone = $this->zone([$this->city('رام الله'), $this->city('البيرة')], ['name' => 'الوسط', 'fee' => 15, 'eta_min_days' => 1, 'eta_max_days' => 2, 'free_shipping_threshold' => 300]);

        $this->page('/shipping-zones')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/ShippingZone/Index')
            ->where('table.meta.total', 1)
            ->where('table.data.0.id', $zone->id)
            ->where('table.data.0.name', 'الوسط')
            ->where('table.data.0.fee', '15.00 ₪')
            ->where('table.data.0.eta', '1–2 أيام')
            ->where('table.data.0.free_shipping_threshold', '300.00 ₪')
            ->where('table.data.0.cities_count', 2)
            ->where('table.data.0.active', true)
            ->where('settings.default_free_shipping_threshold', null)
            ->missing('table.data.0.created_by'));
    }

    public function test_each_filter(): void
    {
        $north = $this->zone([], ['name' => 'Northern', 'fee' => 30]);
        $south = $this->zone([], ['name' => 'جنوب', 'fee' => 10, 'is_active' => false]);

        $only = fn (string $query, ShippingZone $zone) => $this->page('/shipping-zones?'.$query)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)->where('table.data.0.id', $zone->id));

        $only('filter[name]=NORTH', $north);
        $only('filter[is_active]=0', $south);
        $only('filter[fee_from]=20', $north);

        $this->page('/shipping-zones?filter[created_at_from]=2999-01-01')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
    }

    public function test_add_saves_the_zone_and_its_cities(): void
    {
        [$a, $b] = [$this->city('نابلس'), $this->city('جنين')];

        $this->actingAs($this->staff)->post($this->url('/shipping-zones'), $this->payload(['name' => 'الشمال', 'cities' => [$a->id, $b->id]]))
            ->assertRedirect($this->url('/shipping-zones'))->assertSessionHasNoErrors();

        $zone = ShippingZone::query()->with('cities')->latest('id')->firstOrFail();
        $this->assertSame('الشمال', $zone->translate('ar')->name);
        $this->assertSame('25.00', $zone->fee);
        $this->assertSame([2, 4], [$zone->eta_min_days, $zone->eta_max_days]);
        $this->assertNull($zone->free_shipping_threshold);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $zone->cities->modelKeys());
        $this->assertSame($this->staff->id, $zone->created_by);
    }

    public function test_edit_shows_and_saves_the_current_locale_only(): void
    {
        $city = $this->city();
        $zone = $this->zone([$city], ['name' => 'الوسط']);

        $this->page('/shipping-zones/'.$zone->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/ShippingZone/Edit')
            ->where('zone.name', 'الوسط')
            ->where('zone.cities', [$city->id])
            ->has('cities'));

        $this->actingAs($this->staff)->put($this->url('/shipping-zones/'.$zone->id, 'en'), $this->payload(['name' => 'Center', 'cities' => [$city->id], 'free_shipping_threshold' => 250]))
            ->assertSessionHasNoErrors();

        $zone->refresh();
        $this->assertSame('الوسط', $zone->translate('ar')->name);
        $this->assertSame('Center', $zone->translate('en')->name);
        $this->assertSame('250.00', $zone->free_shipping_threshold);
    }

    public function test_validation_errors(): void
    {
        $this->actingAs($this->staff)->post($this->url('/shipping-zones'), ['name' => '', 'fee' => -1, 'eta_min_days' => 5, 'eta_max_days' => 2, 'free_shipping_threshold' => 'x', 'cities' => [999999]])
            ->assertSessionHasErrors(['name', 'fee', 'eta_max_days', 'free_shipping_threshold', 'cities.0']);
    }

    public function test_a_city_cannot_be_in_two_active_zones(): void
    {
        $city = $this->city('الخليل');
        $this->zone([$city]);

        $this->actingAs($this->staff)->post($this->url('/shipping-zones'), $this->payload(['cities' => [$city->id]]))
            ->assertSessionHasErrors('cities');
        $this->assertSame(1, ShippingZone::query()->count());

        // An inactive zone may hold it.
        $this->actingAs($this->staff)->post($this->url('/shipping-zones'), $this->payload(['cities' => [$city->id], 'is_active' => false]))
            ->assertSessionHasNoErrors();
    }

    public function test_activating_a_zone_rechecks_its_cities(): void
    {
        $city = $this->city();
        $this->zone([$city]);
        $idle = $this->zone([$city], ['is_active' => false]);

        $this->actingAs($this->staff)->put($this->url('/shipping-zones/'.$idle->id.'/status'), ['active' => true])
            ->assertSessionHasErrors('active');

        $this->assertFalse($idle->fresh()->is_active);
    }

    public function test_the_default_threshold_is_saved(): void
    {
        $this->actingAs($this->staff)->put($this->url('/shipping-zones/settings'), ['default_free_shipping_threshold' => 400])
            ->assertSessionHasNoErrors();
        $this->assertSame(400.0, app(StoreShippingSettings::class)->default_free_shipping_threshold);

        $this->actingAs($this->staff)->put($this->url('/shipping-zones/settings'), ['default_free_shipping_threshold' => null]);
        $this->assertNull(app(StoreShippingSettings::class)->default_free_shipping_threshold);
    }

    public function test_delete_and_bulk_delete(): void
    {
        $city = $this->city();
        $one = $this->zone([$city]);
        $two = $this->zone();

        $this->actingAs($this->staff)->delete($this->url('/shipping-zones/'.$one->id))->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->delete($this->url('/shipping-zones'), ['ids' => [$two->id]])->assertSessionHasNoErrors();

        $this->assertSame(0, ShippingZone::query()->count());
        $this->assertNotNull($city->fresh(), 'the city stays');
    }

    public function test_every_action_needs_its_permission(): void
    {
        $viewer = User::forceCreate([
            'name' => 'Viewer',
            'email' => 'viewer-'.Str::random(10).'@store.test',
            'password' => 'secret-password',
        ])->givePermissionTo('Shipping Zone View List');
        $zone = $this->zone();

        $this->actingAs($viewer)->get($this->url('/shipping-zones'))->assertOk();
        $this->actingAs($viewer)->get($this->url('/shipping-zones/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->url('/shipping-zones'), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get($this->url('/shipping-zones/'.$zone->id.'/edit'))->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/shipping-zones/'.$zone->id), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/shipping-zones/'.$zone->id.'/status'), ['active' => false])->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/shipping-zones/settings'), ['default_free_shipping_threshold' => 1])->assertForbidden();
        $this->actingAs($viewer)->delete($this->url('/shipping-zones/'.$zone->id))->assertForbidden();
        $this->actingAs($viewer)->delete($this->url('/shipping-zones'), ['ids' => [$zone->id]])->assertForbidden();

        $this->assertTrue($zone->fresh()->is_active);
    }

    public function test_super_admin_has_the_permissions_and_the_sidebar_item(): void
    {
        $role = Role::findByName('Super Admin');

        foreach (['View List', 'Create', 'Update', 'Delete'] as $action) {
            $this->assertTrue($role->hasPermissionTo('Shipping Zone '.$action), $action);
        }

        $this->actingAs($this->staff);
        $settings = collect(app(SidebarService::class)->all())->where('title', 'Settings');
        $this->assertCount(1, $settings, 'one Settings group');
        $this->assertContains('shipping-zones.index', array_column($settings->first()['children'], 'route'));
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'منطقة',
            'fee' => 25,
            'eta_min_days' => 2,
            'eta_max_days' => 4,
            'free_shipping_threshold' => null,
            'is_active' => true,
            'sort_order' => 0,
            'cities' => [],
            ...$overrides,
        ];
    }

    private function page(string $path)
    {
        return $this->actingAs($this->staff)->get($this->url($path));
    }

    private function url(string $path, string $locale = 'ar'): string
    {
        return $this->siteUrl('/'.$locale.'/admin'.$path);
    }
}
