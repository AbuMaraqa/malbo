<?php

namespace Tests\Feature\Wholesale;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Wholesale\Entities\Tier;
use Modules\Wholesale\Entities\TierTranslation;
use Modules\Wholesale\Settings\WholesaleSettings;

/**
 * Price tiers (a translated name, a discount off the wholesale price, one default) and the store's
 * minimum order.
 */
class TierPagesTest extends WholesaleTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->assignRole('Super Admin');
    }

    public function test_the_list_and_its_filters(): void
    {
        $gold = $this->tier('Gold', 15, default: true);
        $this->merchant(attributes: ['tier_id' => $gold->id]);
        $silver = $this->tier('فضي', 5);

        $this->page('/tiers')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wholesale/Tier/Index')
            ->where('table.meta.total', 2)
            ->where('table.data.0.name', 'Gold')
            ->where('table.data.0.discount_percent', 15)
            ->where('table.data.0.is_default', true)
            ->where('table.data.0.merchants_count', 1)
            ->where('settings.minimum_order_value', null)
            ->etc());

        $this->page('/tiers?filter[name]=gol')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.id', $gold->id)->etc());
        $this->page('/tiers?filter[is_default]=0')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.id', $silver->id)->etc());
        $this->page('/tiers?filter[discount_percent_from]=10')->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1)->where('table.data.0.id', $gold->id)->etc());
    }

    public function test_add_and_edit(): void
    {
        $this->actingAs($this->admin)->post($this->url('/tiers'), ['name' => 'برونزي', 'discount_percent' => 3.5, 'sort_order' => 2, 'is_default' => false])
            ->assertRedirect($this->url('/tiers'))->assertSessionHasNoErrors();

        $tier = Tier::query()->sole();
        $this->assertSame('برونزي', $tier->translate('ar')->name);
        $this->assertSame('3.50', $tier->discount_percent);

        $this->page('/tiers/'.$tier->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Wholesale/Tier/Edit')->where('tier.name', 'برونزي')->etc());

        $this->actingAs($this->admin)->put($this->url('/tiers/'.$tier->id), ['name' => 'برونزي+', 'discount_percent' => 4, 'sort_order' => 1, 'is_default' => true])->assertSessionHasNoErrors();
        $this->assertTrue($tier->fresh()->is_default);

        $this->actingAs($this->admin)->post($this->url('/tiers'), ['name' => 'x', 'discount_percent' => 101])->assertSessionHasErrors('discount_percent');
    }

    public function test_a_tier_cannot_give_the_goods_away(): void
    {
        $this->actingAs($this->admin)->post($this->url('/tiers'), ['name' => 'مجاني', 'discount_percent' => 100])->assertSessionHasErrors('discount_percent');
        $this->actingAs($this->admin)->post($this->url('/tiers'), ['name' => 'شبه مجاني', 'discount_percent' => 99.99])->assertSessionHasNoErrors();

        $this->assertSame(['شبه مجاني'], Tier::query()->get()->map(fn (Tier $tier) => $tier->translate('ar')->name)->all());
    }

    public function test_one_default_tier(): void
    {
        $first = $this->tier('أول', 0, default: true);

        $this->actingAs($this->admin)->post($this->url('/tiers'), ['name' => 'ثاني', 'discount_percent' => 2, 'is_default' => true])->assertSessionHasNoErrors();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, Tier::query()->where('is_default', true)->count());
    }

    public function test_two_defaults_at_once_give_a_form_error_not_a_500(): void
    {
        // Another request makes its own tier the default between this one's "step down" and its save.
        Tier::saving(function (Tier $tier): void {
            if ($tier->is_default && ! $tier->exists) {
                DB::table($tier->getTable())->insert(['discount_percent' => 0, 'sort_order' => 0, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        $this->actingAs($this->admin)->post($this->url('/tiers'), ['name' => 'ثاني', 'discount_percent' => 2, 'is_default' => true])
            ->assertRedirect()
            ->assertSessionHasErrors('is_default');

        // Nothing of the refused save remains (the simulated rival rolls back with it here).
        $this->assertFalse(TierTranslation::query()->where('name', 'ثاني')->exists());
    }

    public function test_the_default_tier_cannot_be_deleted(): void
    {
        $default = $this->tier('أساسي', 0, default: true);
        $other = $this->tier('آخر', 5);
        $merchant = $this->merchant(attributes: ['tier_id' => $other->id]);

        $this->actingAs($this->admin)->delete($this->url('/tiers/'.$default->id))->assertSessionHasErrors('tier');
        $this->assertNotNull($default->fresh());

        $this->actingAs($this->admin)->delete($this->url('/tiers/'.$other->id))->assertSessionHasNoErrors();
        $this->assertNull($other->fresh());
        $this->assertNull($merchant->fresh()->tier_id);
    }

    public function test_minimum_order_settings(): void
    {
        $this->actingAs($this->admin)->put($this->url('/tiers/settings'), ['minimum_order_value' => 2000, 'minimum_order_pieces' => 36])->assertSessionHasNoErrors();

        $settings = app(WholesaleSettings::class);
        $settings->refresh();
        $this->assertSame(2000.0, $settings->minimum_order_value);
        $this->assertSame(36, $settings->minimum_order_pieces);

        $this->actingAs($this->admin)->put($this->url('/tiers/settings'), ['minimum_order_value' => null, 'minimum_order_pieces' => null])->assertSessionHasNoErrors();
        $settings->refresh();
        $this->assertNull($settings->minimum_order_value);
    }

    public function test_every_action_needs_its_permission(): void
    {
        $viewer = User::forceCreate(['name' => 'V', 'email' => 'v-'.Str::random(8).'@store.test', 'password' => 'secret-password'])->givePermissionTo('Wholesale Tier View List');
        $tier = $this->tier('x', 1);

        $this->actingAs($viewer)->get($this->url('/tiers'))->assertOk();
        $this->actingAs($viewer)->get($this->url('/tiers/add'))->assertForbidden();
        $this->actingAs($viewer)->post($this->url('/tiers'), ['name' => 'y', 'discount_percent' => 1])->assertForbidden();
        $this->actingAs($viewer)->get($this->url('/tiers/'.$tier->id.'/edit'))->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/tiers/'.$tier->id), ['name' => 'y', 'discount_percent' => 1])->assertForbidden();
        $this->actingAs($viewer)->put($this->url('/tiers/settings'), ['minimum_order_value' => 1])->assertForbidden();
        $this->actingAs($viewer)->delete($this->url('/tiers/'.$tier->id))->assertForbidden();

        $this->assertNotNull($tier->fresh());
    }

    private function url(string $path): string
    {
        return $this->siteUrl('/ar/admin/wholesale'.$path);
    }

    private function page(string $path)
    {
        return $this->actingAs($this->admin)->get($this->url($path));
    }
}
