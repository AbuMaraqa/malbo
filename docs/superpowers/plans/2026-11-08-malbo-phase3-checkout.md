# MALBO Phase 3: Shipping, Cart, Checkout and Order Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A buyer can put products in a cart, check out as a guest with cash on delivery, and follow
the order without logging in:
- shipping zones give a city its delivery fee, its expected delivery time and a free-delivery rule;
- a database cart, held by a long-lived cookie, priced live;
- coupons (offers with a code) applied at checkout;
- a checkout that writes the order through `OrderService`, takes stock through `InventoryService`
  and tells the staff;
- a tracking page by order number and phone, with a timeline from a new status-history table;
- the admin sees the channel and customer, filters orders awaiting confirmation, confirms one in a
  click and sees its history.

**Architecture:**
- Store module (storefront and its settings):
  - `ShippingZone` (+ translations, + a pivot to GeoLocation cities), its repository, service,
    request, admin controller and pages. `StoreShippingSettings::default_free_shipping_threshold`.
  - `Cart` and `CartItem`, `CartRepository`, `CartService` (lines, add, quantity, remove, count),
    `CartCookie` (the token cookie).
  - `CouponService` (an `Offer` with a code), `CheckoutService` (`quote()` and `place()`), the
    `PaymentGateway` contract (nothing bound: online payment stays hidden).
  - Storefront controllers `CartController`, `CheckoutController`, `TrackOrderController` and their
    pages; `Phone::normalize()`.
- Items module (orders):
  - `OrderService` writes an `OrderStatusHistory` row on every status change and gains `checkout()`
    next to `place()`.
  - `OrderRepository` gains `findForTracking()` and `couponUsePhones()`; the admin order list, edit
    page and a `confirm` action.
  - `NewOrderAlert` tells staff with `Order View List`, after the commit and never fatal.
- Money is only ever computed on the server: `PricingService` for unit prices, `CheckoutService` for
  the subtotal, discount, delivery fee and total. The browser reloads a `quote` prop.

**Tech Stack:** Laravel 13, nwidart modules, PostgreSQL, Inertia 3 + Vue 3 + TypeScript, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-08-malbo-platform.md` (sections 7, 8 and 11; phase 3).

## Global Constraints

- Follow `CLAUDE.md`: writes through services (one `DB::transaction` when several tables change),
  reads through repositories, thin controllers (`LayeringTest`), `jsonb`, indexed foreign keys,
  `whereLike(..., caseSensitive: false)`, Solar icons, logical utilities, dark mode in the admin.
- Migrations are additive. Allowed changes: `items_orders.created_by` becomes nullable (a guest order
  has no creator). `branch_id` is already nullable.
- `InventoryService` stays the only writer of `stock_quantity`. The cart never reserves stock.
- Cart prices are never stored: every read prices the lines with `PricingService`.
- Texts: admin texts in the module's `Lang/ar.json`; every storefront text, and every server text
  the storefront shows, in `Modules/Store/Lang/ar.json` (`StorefrontFrontendTest`).
- Storefront Vue: no `dark:`, no admin colors or components, no browser globals.
- Tests extend `Tests\DatabaseTestCase` (`StorefrontTestCase` for storefront pages). Single files
  while working; the whole suite once at the end. Do not commit.

## Review Focus

1. **Two buyers race for a coupon's last use.** The offer row is locked inside the checkout
   transaction before its uses are counted (`CouponService::check(..., lock: true)` from
   `CheckoutService::place`). The counting is pinned in Task 4 (`test_a_used_up_coupon_is_refused`).
2. **The browser sends a price or a total.** Nothing priced comes from the request; the order's money
   equals the server quote. Pinned in Task 5 (`test_totals_from_the_browser_are_ignored`).
3. **Double submit.** The token runs once; a second submit lands on the same confirmation, and the
   converted cart is never ordered twice. Pinned in Task 5.
4. **Stock short at checkout.** Nothing is saved, the cart stays, each short line gets its message.
   Pinned in Task 5.
5. **Tracking leaks.** A wrong phone and an unknown number give the same message; the result holds
   no address, no name and no internal notes. Pinned in Task 6.
6. **A city in two zones.** Refused on save while both zones are active. Pinned in Task 1.

---

### Task 1: Shipping zones (admin)

**Files:**
- Create: `Modules/Store/database/migrations/2026_11_08_100001_create_store_shipping_zones_tables.php`
- Create: `database/settings/2026_11_08_000001_create_store_shipping_settings.php`,
  `Modules/Store/Settings/StoreShippingSettings.php` (registered in `config/settings.php`)
- Create: `Modules/Store/Entities/{ShippingZone,ShippingZoneTranslation}.php`
- Create: `Modules/Store/Repositories/ShippingZoneRepository.php`, `Repositories/Contracts/ShippingZoneRepositoryInterface.php`
- Create: `Modules/Store/Services/ShippingZoneService.php`, `Http/Requests/Admin/{ShippingZoneRequest,ShippingSettingsRequest}.php`,
  `Http/Controllers/Admin/ShippingZoneController.php`
- Create: `Modules/Store/resources/js/Pages/ShippingZone/{Index,Add,Edit,ShippingZoneFields,ShippingSettingsCard}.vue`
- Modify: `Modules/Store/routes/web.php`, `Providers/{RepositoryServiceProvider,SidebarServiceProvider}.php`,
  `database/seeders/PermissionSeeder.php`, `Lang/ar.json`
- Modify: `Modules/Core/Services/{SidebarService,SidebarGroup}.php` (find a group by title)
- Test: `tests/Feature/Store/ShippingZonePagesTest.php`

**Interfaces:**
- Tables:
  - `store_shipping_zones`: id, fee decimal(10,2), eta_min_days smallint, eta_max_days smallint,
    free_shipping_threshold decimal(10,2) nullable, is_active bool, sort_order int, created_by →
    users nullOnDelete, timestamps.
  - `store_shipping_zone_translations`: shipping_zone_id → cascade, locale, name; unique per locale.
  - `store_shipping_zone_city`: shipping_zone_id → cascade, city_id → cascade, primary key, index city_id.
- `store_shipping.default_free_shipping_threshold` (nullable float).
- Routes `shipping-zones.index/add/store/edit/update/destroy/destroy-many/status/settings`, each with
  `->can('Shipping Zone View List|Create|Update|Delete')`. Permissions given to Super Admin. Sidebar:
  Settings → "Shipping zones".
- `ShippingZoneRepositoryInterface`: `tableQuery()`, `forEdit(int)`, `activeForCity(int): ?ShippingZone`,
  `takenCityIds(list<int>, ?int $exceptZoneId): list<int>`, `deliverableCities(): list<array{value, label, districts}>`.
- `ShippingZoneService::create(array, string $locale, int $userId)`, `update`, `setActive`, `delete`,
  `deleteMany`, `saveSettings(?float)`. Activating a zone re-checks its cities.

- [ ] **Step 1: Write the failing tests** in `ShippingZonePagesTest`:
  `test_the_list_is_shaped_for_the_table`, `test_each_filter`, `test_add_saves_the_zone_and_its_cities`,
  `test_edit_shows_and_saves_the_current_locale_only`, `test_validation_errors`,
  `test_a_city_cannot_be_in_two_active_zones`, `test_activating_a_zone_rechecks_its_cities`,
  `test_the_default_threshold_is_saved`, `test_delete_and_bulk_delete`, `test_every_action_needs_its_permission`,
  `test_super_admin_has_the_permissions_and_the_sidebar_item`.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** it, `LayeringTest`, `TableFiltersTest`, `InertiaPagesExistTest`, `ArabicTextsTest`,
  `ForeignKeysAreIndexedTest`. Expected: PASS.

### Task 2: Order status history, checkout write path, created_by nullable

**Files:**
- Create: `Modules/Items/database/migrations/2026_11_08_000006_add_checkout_to_orders.php`
- Create: `Modules/Items/Entities/OrderStatusHistory.php`, `Modules/Items/Notifications/NewOrderAlert.php`
- Modify: `Modules/Items/Entities/Order.php`, `Services/OrderService.php`, `Repositories/OrderRepository.php`,
  `Repositories/Contracts/OrderRepositoryInterface.php`
- Test: `tests/Feature/Items/OrderStatusHistoryTest.php`

**Interfaces:**
- `items_orders.coupon_code` string(50) nullable; `created_by` nullable.
- `items_order_status_history`: id, order_id → cascade, status int, created_by → users nullOnDelete,
  timestamps.
- `Order::statusHistory()` (oldest first). Every `create`, `place`, `checkout`, and every `update`
  or `changeStatus` that moves the status writes a row.
- `OrderService::checkout(array $header, list<array{product_id: int, quantity: int, price: float}> $lines): Order`
  — no user, no creator, channel B2C, AWAITING_CONFIRMATION for cash (else PENDING), UNPAID,
  snapshots, stock taken. Runs in the caller's transaction (nested is fine).
- `OrderRepositoryInterface::findForTracking(string $number): ?Order`,
  `couponUsePhones(string $code): list<?string>` (orders not cancelled or rejected).
- `NewOrderAlert::send(Order)`.

- [ ] **Step 1: Write the failing tests:** `test_every_way_an_order_starts_writes_its_first_status`,
  `test_a_status_change_writes_a_row_and_staying_does_not`, `test_checkout_writes_a_guest_order`,
  `test_checkout_shortage_saves_nothing`, `test_new_order_alert_reaches_order_staff_and_never_fails`.
- [ ] **Step 2: Run.** FAIL. **Step 3: Implement.** **Step 4: Run** it, `OrderPagesTest`, `OrderStockTest`,
  `OrderApiTest`, `OrderSnapshotTest`. PASS.

### Task 3: Cart

**Files:**
- Create: `Modules/Store/database/migrations/2026_11_08_100002_create_store_carts_tables.php`
- Create: `Modules/Store/Entities/{Cart,CartItem}.php`, `Repositories/{CartRepository,Contracts/CartRepositoryInterface}.php`
- Create: `Modules/Store/Services/CartService.php`, `Support/CartCookie.php`
- Create: `Modules/Store/Http/Controllers/Storefront/CartController.php`, `Http/Requests/CartItemRequest.php`
- Create: `Modules/Store/resources/js/Pages/Storefront/Cart.vue`, `resources/js/Components/storefront/{CartLineRow,LineQuantity,CartButton}.vue`
- Modify: `Modules/Store/routes/web.php`, `Support/StorefrontLayout.php` (`cart_count`, `cart_url`),
  `Services/ProductPage.php` (`can_add_to_cart`), `Http/Controllers/Storefront/ProductController.php`,
  `ProductPage.vue`, `ProductOrderBar.vue`, `StorefrontHeader.vue`, `types.ts`, `Lang/ar.json`
- Test: `tests/Feature/Store/CartTest.php`

**Interfaces:**
- `store_carts`: id, uuid unique, token string(64) unique, user_id nullable, order_id nullable,
  last_activity_at, converted_at nullable, timestamps. `store_cart_items`: cart_id → cascade,
  product_id → cascade, quantity, timestamps, unique (cart_id, product_id).
- Routes `store.cart` (GET), `store.cart.add` (POST), `store.cart.update` (PUT `{item}`),
  `store.cart.remove` (DELETE `{item}`).
- `CartService`: `current(Request): ?Cart`, `add(Request, int $productId, int $quantity): Cart`,
  `setQuantity(Cart, int $itemId, int)`, `remove(Cart, int $itemId)`, `count(?Cart): int`,
  `lines(Cart): list<array>` (each: id, product_id, name, options, image, url, unit price, quantity,
  max_quantity, total, available, problem), `subtotal(lines)`.
- Sellable: a variation of a published, active parent (itself active, its values shown), or a parent
  without active variations; no addons (the cart does not carry them). Quantity ≤
  `ProductConfigurator::maxQuantity`; in stock by `ProductConfigurator::available`.

- [ ] **Step 1: Write the failing tests** in `CartTest`: `test_adding_creates_the_cart_and_its_cookie`,
  `test_the_same_product_is_merged`, `test_a_quantity_beyond_the_limit_or_stock_is_refused`,
  `test_only_sellable_products_can_be_added`, `test_the_cart_page_prices_lines_live`,
  `test_a_line_whose_product_is_gone_is_flagged_and_left_out`, `test_update_and_remove_only_touch_this_cart`,
  `test_the_layout_counts_the_cart`, `test_the_product_page_says_whether_it_can_go_in_the_cart`.
- [ ] **Step 2–4:** FAIL, implement, PASS (with `ProductPageTest`, `StorefrontFrontendTest`).

### Task 4: Coupons and the checkout quote

**Files:**
- Create: `Modules/Store/Services/{CouponService,CheckoutService}.php`, `Modules/Store/Support/{Phone,Coupon}.php`,
  `Modules/Store/Contracts/PaymentGateway.php`
- Modify: `Modules/Items/Repositories/{OfferRepository,Contracts/OfferRepositoryInterface}.php` (`findCoupon`, `lockCoupon`)
- Test: `tests/Feature/Store/CouponTest.php`, `tests/Unit/Store/PhoneTest.php`

**Interfaces:**
- `CouponService::check(string $code, float $subtotal, ?string $phone = null, bool $lock = false): Coupon`
  throws a ValidationException on `coupon`: not valid / not started / expired / below minimum /
  used up / already used by this phone. A midnight end date means "through that day".
- `Coupon`: code, type, discount for a subtotal, free shipping.
- `CheckoutService::quote(array $lines, ?int $cityId, ?string $coupon): array` — subtotal, discount,
  coupon state, zone (name, fee, eta), threshold, free shipping and what is left to reach it,
  delivery fee, total, labels.
- `Phone::normalize(string): string` — digits; `00`/`+` and 970/972 dropped; one leading 0 dropped.

- [ ] Tests: `test_percentage_and_fixed_codes_lower_the_subtotal`, `test_free_shipping_code`,
  `test_codes_are_matched_in_any_letter_case`, `test_each_refusal_has_its_message`,
  `test_an_offer_without_a_code_is_no_coupon`, `test_a_used_up_coupon_is_refused`,
  `test_a_cancelled_order_gives_its_use_back`, `test_the_zone_threshold_wins_over_the_store_default`;
  `PhoneTest::test_normalize`.

### Task 5: Checkout pages

**Files:**
- Create: `Modules/Store/Http/Controllers/Storefront/CheckoutController.php`, `Http/Requests/CheckoutRequest.php`
- Create: `Modules/Store/resources/js/Pages/Storefront/{Checkout,CheckoutDone}.vue`,
  `resources/js/Components/storefront/{CheckoutSummary,FreeShippingBar,StoreSelect}.vue`
- Modify: `Modules/Store/routes/web.php`, `Providers/StoreServiceProvider.php` (rate limiter `store-checkout`)
- Test: `tests/Feature/Store/CheckoutTest.php`

**Interfaces:**
- Routes `store.checkout` (GET), `store.checkout.place` (POST, `throttle:store-checkout`),
  `store.checkout.done` (GET; shows the order placed in this session only).
- Props: `lines`, `cities` (deliverable only, with their districts), `quote` (reloaded with `city_id`
  and `coupon`), `paymentMethods`, `submitToken`.
- `CheckoutService::place(Cart, array $data): Order`: one transaction — lock cart, re-check lines,
  lock coupon, compute, `OrderService::checkout`, mark the cart converted. `NewOrderAlert` after.

- [ ] Tests: `test_an_empty_cart_goes_back_to_the_cart`, `test_the_page_offers_only_deliverable_cities`,
  `test_the_quote_follows_the_city_and_coupon`, `test_placing_a_cash_order`,
  `test_totals_from_the_browser_are_ignored`, `test_a_city_without_a_zone_is_refused`,
  `test_the_district_must_belong_to_the_city`, `test_a_shortage_keeps_the_cart_and_names_each_line`,
  `test_double_submit_places_one_order`, `test_the_confirmation_is_only_for_this_session`,
  `test_staff_are_told`, `test_checkout_is_rate_limited`, `test_online_payment_is_hidden_until_a_gateway_is_bound`.

### Task 6: Order tracking

**Files:**
- Create: `Modules/Store/Http/Controllers/Storefront/TrackOrderController.php`, `Http/Requests/TrackOrderRequest.php`,
  `Modules/Store/Services/OrderTracking.php`
- Create: `Modules/Store/resources/js/Pages/Storefront/TrackOrder.vue`, `resources/js/Components/storefront/OrderTimeline.vue`
- Test: `tests/Feature/Store/TrackOrderTest.php`

**Interfaces:**
- Routes `store.track` (GET, `?number=` prefills) and `store.track.find` (POST).
- `OrderTracking::result(Order): array` — number, status, date, timeline (stages placed → confirmed →
  preparing → with courier → delivered, with dates; a final status ends it), lines (name, options,
  quantity, total) and totals. Nothing else.
- Rate limit: 10 lookups a minute per IP, a validation message.

- [ ] Tests: `test_the_page_prefills_the_number`, `test_a_matching_number_and_phone_show_the_order`, `test_the_phone_is_matched_loosely`,
  `test_a_wrong_phone_and_an_unknown_number_say_the_same`, `test_the_result_holds_nothing_private`,
  `test_a_cancelled_order_ends_its_timeline`, `test_lookups_are_rate_limited`.

### Task 7: Admin orders

**Files:**
- Modify: `Modules/Items/Http/Controllers/Admin/OrderController.php`, `Http/Requests/Admin/OrderRequest.php`,
  `routes/web.php` (`orders.confirm`), `resources/js/Pages/Order/{Index,Edit}.vue`,
  `parts/OrderStatusHistory.vue` (new), `Lang/ar.json`
- Test: `tests/Feature/Items/OrderConfirmTest.php`

**Interfaces:**
- List rows gain `channel`, `customer_name`; filters `awaiting_confirmation` (boolean) and `channel`.
- `PUT orders/{order}/confirm` (`Order Update`) → `changeStatus(CONFIRMED)`.
- Edit props gain `history` and `canConfirm`. A guest order (no user, branch or district) can be
  saved without them.

- [ ] Tests: `test_the_list_shows_channel_and_customer_name`, `test_the_awaiting_confirmation_filter`,
  `test_confirm_moves_an_awaiting_order_and_writes_history`, `test_confirm_refuses_other_statuses_and_needs_update`,
  `test_the_edit_page_shows_the_history`, `test_a_guest_order_can_be_edited`.

### Task 8: Whole suite, type check, dev database

- [ ] `php artisan test`; `npm run typecheck`.
- [ ] `php artisan migrate --force`; seed `Modules\Store\database\seeders\PermissionSeeder`.
