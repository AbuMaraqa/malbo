<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\Entities\User;
use Modules\Core\Services\SidebarService;
use Modules\Items\Entities\Order;
use Modules\Items\Entities\Product;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\DatabaseTestCase;
use Tests\Feature\Items\Concerns\MakesOrders;

/**
 * The global activity log: who did what to which record and when, with the changed values. Read
 * only, and never a password, token or secret.
 */
class ActivityLogPagesTest extends DatabaseTestCase
{
    use MakesOrders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrders();
        Activity::query()->delete();
    }

    public function test_the_list_shows_who_what_which_and_when(): void
    {
        $this->storeOrder([['product_id' => $this->stocked(5)->id, 'quantity' => 1]])->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/activity-log?filter[event]=created&filter[subject_type]='.urlencode(Order::class)))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Core/ActivityLog/Index')
            ->where('table.meta.total', 1)
            ->where('table.data.0.event', 'created')
            ->where('table.data.0.subject', __('Order'))
            ->where('table.data.0.subject_id', $order->id)
            ->where('table.data.0.causer', 'Staff')
            ->where('table.data.0.created_at', fn ($at) => is_string($at) && $at !== '')
            ->has('subjectTypes')
            ->has('events', 3)
            ->where('causers', fn ($causers) => collect($causers)->pluck('value')->contains($this->staff->id)));
    }

    public function test_changes_show_old_and_new(): void
    {
        $product = $this->stocked(5, ['sku' => 'OLD-SKU']);
        $this->actingAs($this->staff);
        $product->update(['sku' => 'NEW-SKU']);

        $this->get($this->siteUrl('/ar/admin/activity-log?filter[event]=updated&filter[subject_type]='.urlencode(Product::class)))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.data.0.changes', fn ($changes) => collect($changes)->contains(fn ($change) => $change['key'] === 'sku' && $change['old'] === 'OLD-SKU' && $change['new'] === 'NEW-SKU')));
    }

    public function test_secrets_are_masked(): void
    {
        Activity::query()->create([
            'log_name' => 'User',
            'description' => 'updated',
            'event' => 'updated',
            'subject_type' => User::class,
            'subject_id' => $this->customer->id,
            'causer_type' => User::class,
            'causer_id' => $this->staff->id,
            'attribute_changes' => [
                'attributes' => [
                    'name' => 'New', 'password' => 'hash-new', 'remember_token' => 'tok-new',
                    'settings' => ['client_secret' => 'sec-new', 'theme' => 'dark'],
                    'two_factor_recovery_codes' => 'rec-new',
                    // A JSON column logged as its text.
                    'integrations' => '{"courier": {"api_key": "json-key-new", "pin": "4321"}, "label": "visible"}',
                ],
                'old' => [
                    'name' => 'Old', 'password' => 'hash-old', 'remember_token' => 'tok-old',
                    'settings' => ['client_secret' => 'sec-old', 'theme' => 'light'],
                    'two_factor_recovery_codes' => 'rec-old',
                    'integrations' => '{"courier": {"private_key": "json-key-old"}}',
                ],
            ],
            'properties' => ['api_token' => 'prop-token'],
        ]);

        $response = $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/activity-log'))->assertOk();
        $json = json_encode($response->viewData('page')['props']['table'], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('visible', $json, 'what is not secret in a JSON string stays readable');

        foreach (['hash-new', 'hash-old', 'tok-new', 'tok-old', 'sec-new', 'sec-old', 'prop-token', 'rec-new', 'rec-old', 'json-key-new', 'json-key-old', '4321'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }

        $response->assertInertia(fn (Assert $page) => $page
            ->where('table.data.0.changes', fn ($changes) => collect($changes)->contains(fn ($change) => $change['key'] === 'password' && $change['old'] === '••••••' && $change['new'] === '••••••')
                && collect($changes)->contains(fn ($change) => $change['key'] === 'name' && $change['old'] === 'Old' && $change['new'] === 'New')
                && collect($changes)->contains(fn ($change) => $change['key'] === 'settings' && str_contains((string) $change['new'], 'dark'))));
    }

    public function test_each_filter(): void
    {
        $other = User::forceCreate(['name' => 'Other', 'email' => 'other-'.Str::random(8).'@orders.test', 'password' => 'secret-password'])->assignRole('Super Admin');
        $this->actingAs($this->staff);
        $mine = $this->stocked(5);
        $this->actingAs($other);
        $theirs = $this->stocked(5);
        $theirs->delete();

        $ids = fn (string $query) => collect($this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/activity-log?'.$query))
            ->viewData('page')['props']['table']['data'])->map(fn ($row) => $row['subject_id'].':'.$row['event'])->all();

        $this->assertSame([$theirs->id.':deleted', $theirs->id.':created'], $ids('filter[causer_id]='.$other->id.'&filter[subject_type]='.urlencode(Product::class)));
        $this->assertSame([$theirs->id.':deleted'], $ids('filter[event]=deleted'));
        $this->assertSame([$mine->id.':created'], $ids('filter[causer_id]='.$this->staff->id.'&filter[subject_type]='.urlencode(Product::class)));
        $this->assertSame([], $ids('filter[created_at_from]=2999-01-01'));
    }

    public function test_subject_types_are_friendly(): void
    {
        $this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/activity-log'))->assertInertia(fn (Assert $page) => $page
            ->where('subjectTypes', fn ($types) => collect($types)->contains(fn ($type) => $type['value'] === Order::class && $type['label'] === __('Order'))
                && collect($types)->contains(fn ($type) => $type['value'] === Product::class && $type['label'] === __('Product'))));
    }

    public function test_entries_follow_the_permissions_of_their_records(): void
    {
        $this->storeOrder([['product_id' => $this->stocked(5)->id, 'quantity' => 1]])->assertSessionHasNoErrors();
        $viewer = User::forceCreate(['name' => 'Viewer', 'email' => 'viewer-'.Str::random(8).'@orders.test', 'password' => 'secret-password'])
            ->givePermissionTo('Activity Log View List', 'Product View List');

        $page = fn (string $query = '') => $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/activity-log'.$query))->assertOk()->viewData('page')['props'];

        $props = $page();
        $types = collect($props['table']['data'])->pluck('subject')->unique()->values()->all();
        $this->assertSame([__('Product')], $types, 'only the records this user may see');
        $this->assertNotContains(Order::class, collect($props['subjectTypes'])->pluck('value')->all());
        $this->assertSame([Product::class], collect($props['subjectTypes'])->pluck('value')->all());
        $this->assertSame(0, $page('?filter[subject_type]='.urlencode(Order::class))['table']['meta']['total']);

        // The same entries are there for someone who may see orders.
        $this->assertGreaterThan(0, collect($this->actingAs($this->staff)->get($this->siteUrl('/ar/admin/activity-log?filter[subject_type]='.urlencode(Order::class)))
            ->viewData('page')['props']['table']['data'])->count());
    }

    public function test_it_is_read_only_and_needs_its_permission(): void
    {
        $viewer = User::forceCreate(['name' => 'Viewer', 'email' => 'viewer-'.Str::random(8).'@orders.test', 'password' => 'secret-password'])
            ->givePermissionTo('Order View List');

        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/activity-log'))->assertForbidden();

        $viewer->givePermissionTo('Activity Log View List');
        $this->actingAs($viewer)->get($this->siteUrl('/ar/admin/activity-log'))->assertOk();
        $this->actingAs($viewer)->delete($this->siteUrl('/ar/admin/activity-log'))->assertStatus(405);
    }

    public function test_super_admin_has_the_permission_and_the_sidebar_item(): void
    {
        $this->assertTrue(Role::findByName('Super Admin')->hasPermissionTo('Activity Log View List'));

        $this->actingAs($this->staff);
        $settings = collect(app(SidebarService::class)->all())->firstWhere('title', 'Settings');
        $this->assertContains('activity-log.index', array_column($settings['children'], 'route'));
    }
}
