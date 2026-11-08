<?php

namespace Tests\Feature\Api;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Core\Entities\Currency;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Items\Entities\Order;
use Modules\Items\Enums\OrderStatus;
use Spatie\Activitylog\Models\Activity;
use Tests\TenantTestCase;

/**
 * Every route of the API is listed here as staff, buyer or public. A new route that is in no list
 * fails `test_every_api_route_is_classified`, so nobody adds an endpoint without deciding who may
 * call it. Staff endpoints need the permission of their admin page.
 */
class ApiAuthorizationTest extends TenantTestCase
{
    /** "METHOD uri" => the permission it needs. */
    private const STAFF = [
        'GET api/v1/users' => 'User View List',
        'GET api/v1/users/{user}' => 'User View List',
        'PATCH api/v1/users/{user}' => 'User Update',
        'DELETE api/v1/users/{user}' => 'User Delete',
        'GET api/v1/sync/users' => 'User View List',
        'GET api/v1/sync/roles' => 'Roles And Permissions View List',
        'GET api/v1/activity-logs' => 'Roles And Permissions View List',
        'GET api/v1/activity-logs/{activity}' => 'Roles And Permissions View List',
        'GET api/v1/currencies' => 'Currency View List',
        'GET api/v1/currencies/{currency}' => 'Currency View List',
        'GET api/v1/branches/sync/branches' => 'Branch View List',
        'PATCH api/v1/items/orders/{order}/status' => 'Order Update',
        'GET api/v1/items/sync/products' => 'Product View List',
        'GET api/v1/items/sync/categories' => 'Category View List',
        'GET api/v1/items/sync/addons' => 'Addon View List',
        'GET api/v1/items/sync/addonsOptions' => 'Addon View List',
        'GET api/v1/items/sync/offers' => 'Offer View List',
        'GET api/v1/items/sync/orders' => 'Order View List',
    ];

    /** Any signed-in user, on their own records only (tested below). */
    private const BUYER = [
        'POST api/v1/auth/logout',
        'GET api/v1/items/orders',
        'GET api/v1/items/orders/{order}',
        'POST api/v1/items/orders',
    ];

    private const PUBLIC = [
        'POST api/v1/auth/login',
        'POST api/v1/auth/register',
        'GET api/v1/config',
        'GET api/v1/branches',
        'GET api/v1/branches/{branch}',
        'GET api/v1/geolocation/cities',
        'GET api/v1/geolocation/cities/{city}',
        'GET api/v1/geolocation/countries',
        'GET api/v1/geolocation/countries/{country}',
        'GET api/v1/geolocation/districts',
        'GET api/v1/geolocation/districts/{district}',
        'GET api/v1/geolocation/governorates',
        'GET api/v1/geolocation/governorates/{governorate}',
        'GET api/v1/items/categories',
        'GET api/v1/items/categories/{category}',
        'GET api/v1/items/products',
        'GET api/v1/items/products/{product}',
        'GET api/v1/items/products/{product}/attributes',
        'GET api/v1/items/products/{product}/addons',
        'GET api/v1/items/attributes',
        'GET api/v1/items/attributes/{attribute}',
        'GET api/v1/items/offers',
        'GET api/v1/items/offers/{offer}',
        'GET api/v1/items/addons',
        'GET api/v1/items/addons/{addon}',
        'GET api/v1/store/appearance',
        'GET api/v1/store/home',
        'GET api/v1/store/contact',
        'POST api/v1/store/contact-messages',
        'POST api/v1/store/members',
        'GET api/v1/store/pages',
        'GET api/v1/store/pages/{slug}',
    ];

    public function test_every_api_route_is_classified(): void
    {
        $routes = $this->apiRoutes();
        $listed = [...array_keys(self::STAFF), ...self::BUYER, ...self::PUBLIC];

        $this->assertSame([], array_values(array_diff(array_keys($routes), $listed)), 'New API routes: decide who may call them and list them here.');
        $this->assertSame([], array_values(array_diff($listed, array_keys($routes))), 'Listed routes that no longer exist.');
    }

    public function test_only_public_routes_are_open(): void
    {
        foreach ($this->apiRoutes() as $key => $route) {
            $this->assertSame(
                ! in_array($key, self::PUBLIC, true),
                in_array('auth:sanctum', $route->gatherMiddleware(), true),
                "{$key}: a public route has no auth:sanctum, every other route has it.",
            );
        }
    }

    public function test_every_listed_permission_is_seeded(): void
    {
        $this->assertSame([], array_values(array_filter(
            array_unique(self::STAFF),
            fn (string $permission) => ! \Spatie\Permission\Models\Permission::where('name', $permission)->exists(),
        )));
    }

    public function test_a_staff_endpoint_without_a_token_is_401(): void
    {
        foreach (array_keys(self::STAFF) as $key) {
            $this->call_($key)->assertUnauthorized();
        }
    }

    public function test_a_buyer_endpoint_without_a_token_is_401(): void
    {
        foreach (self::BUYER as $key) {
            $this->call_($key)->assertUnauthorized();
        }
    }

    public function test_a_token_without_the_permission_is_403(): void
    {
        $token = $this->tokenFor($this->user());

        foreach (array_keys(self::STAFF) as $key) {
            $this->assertSame(403, $this->call_($key, $token)->getStatusCode(), "{$key} must refuse a token without the permission.");
        }
    }

    public function test_a_token_with_another_permission_is_403(): void
    {
        foreach (self::STAFF as $key => $permission) {
            $other = $permission === 'Tag View List' ? 'Label View List' : 'Tag View List';
            $token = $this->tokenFor($this->user()->givePermissionTo($other));

            $this->assertSame(403, $this->call_($key, $token)->getStatusCode(), "{$key} must refuse a token with an unrelated permission.");
        }
    }

    public function test_a_token_with_the_permission_passes_and_the_endpoint_works(): void
    {
        foreach (self::STAFF as $key => $permission) {
            $token = $this->tokenFor($this->user()->givePermissionTo($permission));
            $response = $this->call_($key, $token);

            // 422: PATCH with an empty body on the status route; the request reached its validation.
            $this->assertContains($response->getStatusCode(), [200, 422], "{$key}: ".mb_substr((string) $response->getContent(), 0, 300));
        }
    }


    public function test_a_made_up_token_is_refused(): void
    {
        $this->getJson($this->tenantUrl('/api/v1/users'), ['Authorization' => 'Bearer 999999|not-a-token-of-this-store'])->assertUnauthorized();
    }

    /* ---- Own records ---- */

    public function test_a_buyer_lists_only_their_own_orders_whatever_the_filter(): void
    {
        $buyer = $this->user();
        $mine = $this->order($buyer);
        $theirs = $this->order($this->user());
        $token = $this->tokenFor($buyer);

        $this->assertSame([$mine->id], $this->orderIds('/api/v1/items/orders', $token));
        $this->assertSame([], $this->orderIds('/api/v1/items/orders?filter[user_id]='.$theirs->user_id, $token));
    }

    public function test_staff_with_the_permission_list_every_order(): void
    {
        $mine = $this->order($this->user());
        $theirs = $this->order($this->user());
        $token = $this->tokenFor($this->user()->givePermissionTo('Order View List'));

        $ids = $this->orderIds('/api/v1/items/orders', $token);

        $this->assertContains($mine->id, $ids);
        $this->assertContains($theirs->id, $ids);
    }

    public function test_a_buyer_reads_only_their_own_order(): void
    {
        $buyer = $this->user();
        $token = $this->tokenFor($buyer);

        $this->getJson($this->tenantUrl('/api/v1/items/orders/'.$this->order($buyer)->id), $this->bearer($token))->assertOk();
        $this->forgetGuards();
        $this->getJson($this->tenantUrl('/api/v1/items/orders/'.$this->order($this->user())->id), $this->bearer($token))->assertForbidden();
    }

    public function test_a_buyer_cannot_change_the_status_of_their_own_order(): void
    {
        $buyer = $this->user();
        $order = $this->order($buyer);

        $this->patchJson($this->tenantUrl("/api/v1/items/orders/{$order->id}/status"), ['status' => OrderStatus::CONFIRMED->value], $this->bearer($this->tokenFor($buyer)))->assertForbidden();

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_staff_change_the_status_and_invalid_transitions_are_refused(): void
    {
        $order = $this->order($this->user());
        $token = $this->tokenFor($this->user()->givePermissionTo('Order Update'));
        $url = $this->tenantUrl("/api/v1/items/orders/{$order->id}/status");

        $this->patchJson($url, ['status' => OrderStatus::DELIVERED->value], $this->bearer($token))->assertStatus(422);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);

        $this->forgetGuards();
        $this->patchJson($url, ['status' => OrderStatus::CONFIRMED->value], $this->bearer($token))->assertOk();
        $this->assertSame(OrderStatus::CONFIRMED, $order->fresh()->status);
    }

    public function test_a_user_reads_and_updates_their_own_record_only(): void
    {
        $user = $this->user();
        $other = $this->user();
        $token = $this->tokenFor($user);

        $this->getJson($this->tenantUrl("/api/v1/users/{$user->id}"), $this->bearer($token))->assertOk()->assertJsonPath('data.id', $user->id);

        $this->forgetGuards();
        $this->patchJson($this->tenantUrl("/api/v1/users/{$user->id}"), ['name' => 'New name', 'email' => 'NEW-'.Str::lower(Str::random(6)).'@Api.test'], $this->bearer($token))->assertOk();
        $this->assertSame('New name', $user->fresh()->name);
        $this->assertSame(Str::lower($user->fresh()->email), $user->fresh()->email);

        $this->forgetGuards();
        $this->getJson($this->tenantUrl("/api/v1/users/{$other->id}"), $this->bearer($token))->assertForbidden();

        $this->forgetGuards();
        $this->patchJson($this->tenantUrl("/api/v1/users/{$other->id}"), ['name' => 'Hijacked'], $this->bearer($token))->assertForbidden();
        $this->assertNotSame('Hijacked', $other->fresh()->name);
    }

    public function test_updating_a_record_cannot_give_a_role_a_branch_or_points(): void
    {
        $user = $this->user();

        $this->patchJson($this->tenantUrl("/api/v1/users/{$user->id}"), [
            'name' => 'Same person',
            'roles' => [UserRole::SUPER_ADMIN->value],
            'role' => UserRole::SUPER_ADMIN->value,
            'point_balance' => 99999,
        ], $this->bearer($this->tokenFor($user)))->assertOk();

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasRole(UserRole::SUPER_ADMIN->value));
        $this->assertNotEquals(99999, $fresh->point_balance);
    }

    public function test_an_email_of_someone_else_is_refused(): void
    {
        $user = $this->user();
        $other = $this->user();

        $this->patchJson($this->tenantUrl("/api/v1/users/{$user->id}"), ['email' => Str::upper($other->email)], $this->bearer($this->tokenFor($user)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email', 'data');
    }

    public function test_only_a_super_admin_edits_or_deletes_a_super_admin(): void
    {
        $admin = $this->user()->assignRole(UserRole::SUPER_ADMIN->value);
        $token = $this->tokenFor($this->user()->givePermissionTo(['User Update', 'User Delete']));

        $this->patchJson($this->tenantUrl("/api/v1/users/{$admin->id}"), ['password' => 'hijacked-pass'], $this->bearer($token))->assertForbidden();
        $this->forgetGuards();
        $this->deleteJson($this->tenantUrl("/api/v1/users/{$admin->id}"), [], $this->bearer($token))->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }

    public function test_nobody_deletes_their_own_account_through_the_api(): void
    {
        $user = $this->user()->givePermissionTo('User Delete');

        $this->deleteJson($this->tenantUrl("/api/v1/users/{$user->id}"), [], $this->bearer($this->tokenFor($user)))->assertStatus(422);

        $this->assertNotNull($user->fresh());
    }

    public function test_a_bad_id_is_a_404_not_a_database_error(): void
    {
        $token = $this->tokenFor($this->user()->assignRole(UserRole::SUPER_ADMIN->value));

        foreach (['/api/v1/users/abc', '/api/v1/items/orders/abc', '/api/v1/currencies/abc', '/api/v1/activity-logs/abc'] as $path) {
            $this->forgetGuards();
            $this->getJson($this->tenantUrl($path), $this->bearer($token))->assertNotFound()->assertJson(['status' => false]);
        }
    }

    public function test_a_malformed_since_is_a_422_not_a_database_error(): void
    {
        $token = $this->tokenFor($this->user()->assignRole(UserRole::SUPER_ADMIN->value));

        foreach (['/api/v1/sync/users', '/api/v1/items/sync/orders', '/api/v1/items/sync/addons', '/api/v1/branches/sync/branches'] as $path) {
            $this->forgetGuards();
            $this->getJson($this->tenantUrl($path.'?since=not-a-date'), $this->bearer($token))->assertStatus(422);
        }
    }

    /* ---- Sign-up and sign-in ---- */

    public function test_register_makes_a_buyer_with_no_permissions(): void
    {
        $email = 'Buyer-'.Str::lower(Str::random(6)).'@Api.test';

        $this->postJson($this->tenantUrl('/api/v1/auth/register'), $this->registration(['email' => $email]))->assertOk();

        $user = User::where('email', Str::lower($email))->firstOrFail();

        $this->assertTrue($user->hasRole(UserRole::CUSTOMER->value));
        $this->assertSame([], $user->getAllPermissions()->pluck('name')->all());
    }

    public function test_register_refuses_a_staff_role(): void
    {
        foreach ([UserRole::SUPER_ADMIN->value, UserRole::USER->value, 'Student', 'Company Supervisor'] as $role) {
            $email = 'x-'.Str::lower(Str::random(6)).'@api.test';

            $this->postJson($this->tenantUrl('/api/v1/auth/register'), $this->registration(['email' => $email, 'role' => $role]))->assertStatus(422);
            $this->assertFalse(User::where('email', $email)->exists());
        }
    }

    public function test_register_accepts_the_customer_role(): void
    {
        $this->postJson($this->tenantUrl('/api/v1/auth/register'), $this->registration(['role' => UserRole::CUSTOMER->value]))->assertOk();
    }

    public function test_register_refuses_a_taken_email_in_another_letter_case(): void
    {
        $this->postJson($this->tenantUrl('/api/v1/auth/register'), $this->registration(['email' => Str::upper($this->user()->email)]))->assertStatus(422);
    }

    public function test_sign_in_in_another_letter_case_gives_a_token_that_works(): void
    {
        $user = $this->user();

        $token = $this->postJson($this->tenantUrl('/api/v1/auth/login'), ['email' => Str::upper($user->email), 'password' => 'password'])
            ->assertOk()
            ->json('data.token');

        $this->forgetGuards();
        $this->getJson($this->tenantUrl('/api/v1/items/orders'), $this->bearer($token))->assertOk();
    }

    public function test_sign_in_and_sign_up_are_rate_limited(): void
    {
        $routes = $this->apiRoutes();

        $this->assertContains('throttle:api-auth', $routes['POST api/v1/auth/login']->gatherMiddleware());
        $this->assertContains('throttle:api-auth', $routes['POST api/v1/auth/register']->gatherMiddleware());
    }

    public function test_logout_ends_only_that_token(): void
    {
        $user = $this->user();
        [$first, $second] = [$this->tokenFor($user), $this->tokenFor($user)];

        $this->postJson($this->tenantUrl('/api/v1/auth/logout'), [], $this->bearer($first))->assertOk();

        $this->forgetGuards();
        $this->getJson($this->tenantUrl('/api/v1/items/orders'), $this->bearer($first))->assertUnauthorized();
        $this->forgetGuards();
        $this->getJson($this->tenantUrl('/api/v1/items/orders'), $this->bearer($second))->assertOk();
    }

    /* ---- Helpers ---- */

    /**
     * @return array<string, RoutingRoute> "METHOD uri" => route, for everything under api/
     */
    private function apiRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    $routes[$method.' '.$route->uri()] = $route;
                }
            }
        }

        return $routes;
    }

    /**
     * Calls a listed route, on fresh records, as a client with this token or without one.
     */
    private function call_(string $key, ?string $token = null): TestResponse
    {
        [$method, $uri] = explode(' ', $key, 2);

        // Records first: logging their activity resolves the user of the previous request.
        $url = $this->tenantUrl('/'.$this->fill($uri));

        $this->forgetGuards();

        return $this->json($method, $url, [], $token ? $this->bearer($token) : []);
    }

    private function fill(string $uri): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn (array $match) => (string) match ($match[1]) {
            'user' => $this->user()->id,
            'order' => $this->order($this->user())->id,
            'currency' => Currency::forceCreate(['name' => 'Test', 'code' => Str::upper(Str::random(3)), 'symbol' => '$', 'exchange_rate' => 1])->id,
            'activity' => activity()->log('probe')->id ?? Activity::query()->value('id'),
        }, $uri);
    }

    private function user(): User
    {
        return User::forceCreate([
            'name' => 'API '.Str::random(5),
            'email' => 'api-'.Str::lower(Str::random(12)).'@api.test',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
    }

    private function order(User $buyer): Order
    {
        return Order::create([
            'user_id' => $buyer->id,
            'created_by' => $buyer->id,
            'order_number' => 'T-'.Str::random(10),
            'sub_total' => 10,
            'total' => 10,
            'delivery_address' => '1',
            'contact_phone' => '0599000000',
            'status' => OrderStatus::PENDING,
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    /**
     * What a new request sees: Sanctum's guard keeps the user it resolved for the whole process.
     */
    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * @return list<int>
     */
    private function orderIds(string $path, string $token): array
    {
        $this->forgetGuards();

        return array_column($this->getJson($this->tenantUrl($path), $this->bearer($token))->assertOk()->json('data'), 'id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registration(array $overrides = []): array
    {
        return [
            'name' => 'Buyer',
            'email' => 'buyer-'.Str::lower(Str::random(8)).'@api.test',
            'password' => 'secret-pass-1',
            'phone' => '0599000000',
            ...$overrides,
        ];
    }
}
