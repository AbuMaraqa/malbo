# MALBO Phase 4: Customer Accounts, Returns and Exchanges Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A buyer can have an account on the storefront, and MALBO can take back or exchange what
it sold:
- register, sign in, sign out and reset a forgotten password on the storefront (Fortify's staff
  `/login` stays as it is); the guest cart merges into the account's cart and follows it;
- an account area: profile and password, saved addresses, orders with their details, a wishlist;
- checkout prefilled from the default address, the order tied to the account, guest orders with the
  same phone linked to it;
- return and exchange requests: from the account (delivered orders, inside a window set in the
  admin) and from the admin; an admin list and a detail page that approves, rejects, receives and
  completes them; completion moves the stock and the order's status in one transaction;
- the Users admin tells customers apart and shows how many orders each has.

**Architecture:**
- Store module (storefront):
  - `CustomerAuthService` (register, login with a per IP + email limit, logout, reset link, reset),
    `CustomerResetPassword` (the reset mail, with a storefront link), controllers `AccountAuthController`
    and `PasswordResetController`, their requests and pages under `Pages/Storefront/Account`.
  - `CartService::adopt()` (merge on sign-in), `current()` that knows the signed-in user.
  - `Address` (+ repository, `AddressService`, `AddressRequest`), `WishlistItem` (+ repository,
    `WishlistService`), `AccountOrders` (the account's order list and detail, built on
    `OrderTracking::result()`).
  - `StoreReturnSettings::return_window_days` (default 14).
  - `StorefrontLayout` gains `account` (signed in, name, links) and `wishlist` (product ids).
- Items module (orders and returns):
  - `ReturnRequest`, `ReturnRequestLine`, `ReturnRequestHistory`, enums `ReturnRequestType`,
    `ReturnRequestStatus`, `ReturnReason`; `ReturnRequestRepository`; `ReturnRequestService` (the
    only writer); admin `ReturnRequestController` and its pages.
  - `OrderService::changeStatus(..., moveStock: false)`, `OrderService::linkGuestOrders()`;
    `checkout()` takes `user_id`. `OrderRepository` gains `couponUses()`, `forCustomer()`,
    `findForCustomer()`.
- Core: the Users list gains a `customers` filter and `orders_count`; guests of `store.*` pages are
  sent to the storefront sign-in.

**Tech Stack:** Laravel 13, nwidart modules, PostgreSQL, Inertia 3 + Vue 3 + TypeScript, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-08-malbo-platform.md` (sections 6 and 12; phase 4).

## Global Constraints

- Follow `CLAUDE.md`: writes through services (one `DB::transaction` when several tables change),
  reads through repositories, thin controllers (`LayeringTest`), `jsonb`, indexed foreign keys,
  `whereLike(..., caseSensitive: false)`, Solar icons through `lib/icons.ts`, logical utilities, dark
  mode in the admin.
- Migrations are additive (new tables only). Partial unique indexes (PostgreSQL) hold "one default
  address per user" and "one open request per order".
- `InventoryService` stays the only writer of stock, `OrderService` the only writer of orders,
  `ReturnRequestService` the only writer of return requests.
- Storefront texts in `Modules/Store/Lang/ar.json` (`StorefrontFrontendTest`); admin texts in the
  module's `Lang/ar.json`. Storefront Vue: no `dark:`, no admin colors or components.
- Customer pages never show `internal_notes` or a staff note; staff notes on a return request are
  for staff only, the customer sees the status.
- Tests extend `Tests\DatabaseTestCase` (`StorefrontTestCase` for storefront pages). Single files
  while working; the whole suite once at the end. Do not commit.

## Review Focus

1. **Stock moved twice on a full return.** `OrderService::changeStatus` restocks every line when the
   order reaches RETURNED. Completing a request restocks exactly its own lines and calls
   `changeStatus(..., moveStock: false)`, so the order's status follows without a second restock.
   Pinned in Task 6 (`test_completing_a_full_return_restocks_once_and_returns_the_order`).
2. **A customer reads another buyer's orders.** Orders are listed by `user_id`; guest orders with
   the account's phone appear only once a signed-in order with that phone exists, and checkout links
   guest orders only to the account that placed an order with that phone. The detail is the
   tracking result (no name, address, phone or note). Pinned in Task 4.
3. **Returning more than was sold.** A line's quantity is capped by what earlier non-rejected
   requests already took; the order row is locked while a request is written. Pinned in Task 6.
4. **An exchange short of stock.** `InventoryService::sell` throws; the whole completion rolls back
   with a clear message. Pinned in Task 6.
5. **Login guessing.** Five attempts a minute per IP + email, as Fortify. Pinned in Task 1.
6. **Cart merge.** Quantities add up, capped by the cart's limit; a cart that belongs to another
   account is never taken. Pinned in Task 2.

---

### Task 1: Storefront sign-in, registration, password reset

**Files:**
- Create: `Modules/Store/Services/CustomerAuthService.php`, `Modules/Store/Notifications/CustomerResetPassword.php`
- Create: `Modules/Store/Http/Controllers/Storefront/{AccountAuthController,PasswordResetController}.php`,
  `Http/Requests/{CustomerRegisterRequest,CustomerLoginRequest,ForgotPasswordRequest,ResetPasswordRequest}.php`
- Create: `Modules/Store/resources/js/Pages/Storefront/Account/{Login,Register,ForgotPassword,ResetPassword}.vue`,
  `resources/js/Components/storefront/{AccountMenu,AuthCard}.vue`
- Modify: `Modules/Store/routes/web.php`, `Support/StorefrontLayout.php` (`account`), `bootstrap/app.php`
  (guests of `store.*` go to `store.account.login`), `StorefrontHeader.vue`, `types.ts`, `lib/icons.ts`, `Lang/ar.json`
- Test: `tests/Feature/Store/CustomerAuthTest.php`

**Interfaces:**
- Routes (guest): `store.account.login` GET/POST, `store.account.register` GET/POST,
  `store.account.password.request` GET, `store.account.password.email` POST,
  `store.account.password.reset` GET `{token}`, `store.account.password.update` POST; `store.account.logout` POST (auth).
- `CustomerAuthService::register(array, Request): User` (role Customer, phone canonical, email lower-cased),
  `login(string $email, string $password, bool $remember, Request): void` (throws on `email`),
  `logout(Request)`, `sendResetLink(string $email): void`, `reset(array): string` (broker status).
- Login limit `store-login:{email}|{ip}` 5/minute; register limit 5/minute per IP + email.
- `store.account` layout prop: `{signed_in, name, login_url, register_url, account_url, orders_url, wishlist_url, logout_url}`.

- [ ] Tests: `test_register_creates_a_customer_and_signs_in`, `test_register_validation`,
  `test_login_and_logout`, `test_wrong_password_is_refused`, `test_login_is_rate_limited_per_email_and_ip`,
  `test_login_returns_to_the_intended_page`, `test_staff_can_sign_in_on_the_storefront`,
  `test_staff_login_page_is_unchanged`, `test_forgot_password_sends_a_storefront_link_and_never_says_who_exists`,
  `test_reset_password`, `test_guests_of_account_pages_go_to_the_storefront_login`, `test_the_layout_knows_the_account`.

### Task 2: The cart follows the account

**Files:**
- Modify: `Modules/Store/Services/CartService.php` (`current`, `count`, `open`, `adopt`),
  `Repositories/{CartRepository,Contracts/CartRepositoryInterface}.php` (`findOpenForUser`), `CustomerAuthService`
- Test: `tests/Feature/Store/CartMergeTest.php`

**Interfaces:**
- `CartService::adopt(Request, User): void` — the browser's guest cart and the user's open cart
  become one (same product: quantities added, capped by `ProductConfigurator::maxQuantity`); the
  cookie then holds the user's cart. A cart of another user is never read or merged.
- A new cart opened by a signed-in user carries `user_id`; with no cookie, a signed-in user gets
  their latest open cart. Logout forgets the cookie.

- [ ] Tests: `test_a_guest_cart_becomes_the_users_cart`, `test_carts_merge_and_quantities_are_capped`,
  `test_the_cart_follows_the_user_to_another_browser`, `test_another_users_cart_is_never_taken`,
  `test_logout_leaves_the_cart_with_the_account`.

### Task 3: Account profile and addresses

**Files:**
- Create: `Modules/Store/database/migrations/2026_11_09_100001_create_store_addresses_table.php`
- Create: `Modules/Store/Entities/Address.php`, `Repositories/{AddressRepository,Contracts/AddressRepositoryInterface}.php`,
  `Services/{AddressService,CustomerProfileService}.php`, `Http/Requests/{AddressRequest,ProfileRequest,AccountPasswordRequest}.php`,
  `Http/Controllers/Storefront/{AccountController,AddressController}.php`
- Create: `Modules/Store/resources/js/Pages/Storefront/Account/{Profile,Addresses}.vue`,
  `resources/js/Components/storefront/{AccountShell,AddressForm,AddressCard}.vue`
- Test: `tests/Feature/Store/AccountAreaTest.php`

**Interfaces:**
- `store_addresses`: id, user_id → users cascade (indexed), label string(60) nullable, recipient_name
  string(120), phone string(40), city_id → cities nullOnDelete (indexed), district_id → districts
  nullOnDelete (indexed), address_line string(500), is_default bool, timestamps; partial unique
  index on user_id where is_default.
- Routes (auth): `store.account` (profile), `store.account.profile.update` PUT, `store.account.password.change` PUT,
  `store.account.addresses` GET, `.store` POST, `.update` PUT `{address}`, `.destroy` DELETE, `.default` PUT.
- `AddressService::create(User, array)`, `update(Address, array)`, `delete(Address)`, `makeDefault(Address)`:
  the first address is the default; a new default clears the old one; deleting the default makes
  the newest left the default. At most 20 addresses.
- City from active-zone cities only (`ShippingZoneRepository::deliverableCities()`), the district of that city.

- [ ] Tests: profile (`test_profile_shows_and_saves`, `test_email_must_stay_unique`,
  `test_password_change_needs_the_current_password`); addresses (`test_add_list_edit_delete`,
  `test_only_one_default`, `test_deleting_the_default_moves_it`, `test_city_must_be_deliverable`,
  `test_another_users_address_is_404`, `test_address_limit`).

### Task 4: Account orders and guest-order linking

**Files:**
- Create: `Modules/Store/Services/AccountOrders.php`, `Http/Controllers/Storefront/AccountOrderController.php`
- Create: `Modules/Store/resources/js/Pages/Storefront/Account/{Orders,OrderDetail}.vue`
- Modify: `Modules/Items/Repositories/{OrderRepository,Contracts/OrderRepositoryInterface}.php`
  (`forCustomer`, `findForCustomer`), `Services/OrderService.php` (`linkGuestOrders`)
- Test: `tests/Feature/Store/AccountOrdersTest.php`

**Interfaces:**
- Routes (auth): `store.account.orders` GET (10 a page, newest first), `store.account.orders.show` GET `{number}`.
- Visible orders: `user_id` = the user; plus guest orders (no user) whose `contact_phone` is the
  account's canonical phone, once at least one of the user's own orders has that phone.
- Detail: `OrderTracking::result()` + coupon code + placed date + return requests and the return form props.

- [ ] Tests: `test_lists_only_the_users_orders_newest_first`, `test_guest_orders_with_the_phone_show_only_after_a_signed_in_order`,
  `test_detail_shows_lines_totals_timeline_and_coupon_but_no_internal_notes`, `test_another_users_order_is_404`.

### Task 5: Checkout when signed in, coupons per buyer

**Files:**
- Modify: `Modules/Store/Services/{CheckoutService,CouponService}.php`, `Http/Controllers/Storefront/CheckoutController.php`,
  `Http/Requests/CheckoutRequest.php` (`save_address`, `address_label`), `Pages/Storefront/Checkout.vue`
- Modify: `Modules/Items/Services/OrderService.php` (`checkout` takes `user_id`),
  `Repositories/OrderRepository.php` (`couponUses` replaces `couponUsePhones`)
- Test: `tests/Feature/Store/CheckoutSignedInTest.php`

**Interfaces:**
- Checkout props gain `account` (`{name, email, phone}` or null) and `addresses` (deliverable ones,
  default first). The page fills the form from the chosen address; the server takes the typed fields.
- `CheckoutService::place(Cart, array, ?User)`: `user_id` on the order, guest orders with the same
  canonical phone linked, the address saved when asked (skipped silently at the limit).
- `CouponService::check(..., ?int $userId)`: a use is the buyer's when its `user_id` is theirs or its phone is the same.

- [ ] Tests: `test_the_form_is_prefilled_from_the_default_address`, `test_a_signed_in_order_gets_the_user`,
  `test_guest_orders_with_the_same_phone_are_linked`, `test_save_this_address`,
  `test_the_coupon_limit_counts_the_users_orders_with_another_phone`, `test_guest_checkout_still_works`.

### Task 6: Return requests (domain)

**Files:**
- Create: `Modules/Items/database/migrations/2026_11_09_000001_create_items_return_requests_tables.php`
- Create: `database/settings/2026_11_09_000001_create_store_return_settings.php`, `Modules/Store/Settings/StoreReturnSettings.php` (registered)
- Create: `Modules/Items/Enums/{ReturnRequestType,ReturnRequestStatus,ReturnReason}.php`,
  `Entities/{ReturnRequest,ReturnRequestLine,ReturnRequestHistory}.php`,
  `Repositories/{ReturnRequestRepository,Contracts/ReturnRequestRepositoryInterface}.php`,
  `Services/ReturnRequestService.php`
- Modify: `Modules/Items/Services/OrderService.php` (`changeStatus(..., bool $moveStock = true)`)
- Test: `tests/Feature/Items/ReturnRequestServiceTest.php`

**Interfaces:**
- `items_return_requests`: id, order_id → orders cascade, user_id → users nullOnDelete, type, status,
  reason, customer_note, staff_note, refund_amount decimal(10,2), refunded bool, refunded_at,
  created_by → users nullOnDelete, timestamps; partial unique index on order_id while open.
  `items_return_request_lines`: request → cascade, order_item_id → order items cascade, quantity,
  exchange_product_id → products nullOnDelete. `items_return_request_history`: request → cascade,
  status, note, created_by → users nullOnDelete, timestamps.
- Statuses `requested → approved | rejected`, `approved → received`, `received → completed`.
- `ReturnRequestService::create(Order, array $data, ?User $customer, ?int $staffId)`,
  `approve|reject|receive(ReturnRequest, ?string $note, int $staffId)`, `complete(ReturnRequest, int $staffId)`,
  `setRefunded(ReturnRequest, bool, int)`, `updateStaffNote`, `eligibility(Order, ?User): ?string`,
  `remaining(Order): array<itemId, qty>`, `exchangeOptions(OrderItem)`, `saveWindow(int)`.
- Completion: one transaction — restock the lines (RETURN), sell the exchange products, the
  order to RETURNED (every line fully returned) or EXCHANGED with `moveStock: false`; refund amount
  = returned units × the line's sold unit price (0 for an exchange). The customer is notified
  (database) after each status change.

- [ ] Tests: `test_a_customer_requests_a_return_of_a_delivered_order`, `test_requests_need_a_delivered_order_inside_the_window`,
  `test_staff_may_request_outside_the_window`, `test_one_open_request_per_order`,
  `test_quantity_is_capped_by_earlier_requests`, `test_customer_exchange_must_be_a_sibling_variant`,
  `test_transitions_follow_the_rules_and_write_history`, `test_completing_a_partial_return_restocks_and_keeps_the_order_delivered`,
  `test_completing_a_full_return_restocks_once_and_returns_the_order`, `test_completing_an_exchange_moves_both_stocks_and_exchanges_the_order`,
  `test_an_exchange_short_of_stock_is_refused_and_nothing_moves`, `test_the_customer_is_notified_on_each_change`.

### Task 7: Return requests (admin and account)

**Files:**
- Create: `Modules/Items/Http/Controllers/Admin/ReturnRequestController.php`,
  `Http/Requests/Admin/{ReturnRequestRequest,ReturnRequestStatusRequest,ReturnRequestUpdateRequest,ReturnSettingsRequest}.php`
- Create: `Modules/Items/resources/js/Pages/ReturnRequest/{Index,Add,Edit,ReturnLinesFields,ReturnSettingsCard}.vue`
- Create: `Modules/Store/Http/Controllers/Storefront/AccountReturnController.php`, `Http/Requests/AccountReturnRequest.php`,
  `resources/js/Components/storefront/{ReturnRequestForm,ReturnRequestList}.vue`
- Modify: `Modules/Items/routes/web.php`, `Providers/{RepositoryServiceProvider,SidebarServiceProvider}.php`,
  `database/seeders/PermissionSeeder.php`, `Lang/ar.json`, `Pages/Order/Edit.vue` (a link for delivered orders)
- Test: `tests/Feature/Items/ReturnRequestPagesTest.php`; the account side in `tests/Feature/Store/AccountOrdersTest.php`

**Interfaces:**
- Routes `return-requests.index/add/store/edit/update/status/settings/products` with
  `->can('Return Request View List|Create|Update')`. Sidebar: "Return requests" next to the orders.
- Index filters: status, type, created_at range, order number. Add takes `?order=<number>`.
- Account: `store.account.orders.returns.store` POST `{number}`.

- [ ] Tests (admin): `test_the_list_is_shaped_for_the_table`, `test_each_filter`, `test_add_finds_the_order_and_creates`,
  `test_edit_shows_the_request`, `test_status_actions`, `test_complete_shortage_shows_the_message`,
  `test_refunded_flag_and_note`, `test_the_window_setting_is_saved`, `test_every_action_needs_its_permission`,
  `test_super_admin_has_the_permissions_and_the_sidebar_item`.
  (account): `test_the_order_detail_offers_the_form_when_eligible`, `test_customer_submits_a_request`,
  `test_the_customer_sees_status_but_not_staff_notes`.

### Task 8: Wishlist

**Files:**
- Create: `Modules/Store/database/migrations/2026_11_09_100002_create_store_wishlist_items_table.php`
- Create: `Modules/Store/Entities/WishlistItem.php`, `Repositories/{WishlistRepository,Contracts/WishlistRepositoryInterface}.php`,
  `Services/WishlistService.php`, `Http/Controllers/Storefront/WishlistController.php`
- Create: `Modules/Store/resources/js/Pages/Storefront/Account/Wishlist.vue`, `resources/js/Components/storefront/WishlistButton.vue`
- Modify: `ProductCard.vue`, `ProductPage.vue`, `StorefrontLayout` (`wishlist` ids), `StorefrontCatalog::cardsFor()`,
  `ProductRepository::publishedByIds()`
- Test: `tests/Feature/Store/WishlistTest.php`

**Interfaces:**
- `store_wishlist_items`: id, user_id → users cascade, product_id → products cascade (indexed),
  timestamps, unique (user_id, product_id).
- Routes: `store.account.wishlist` GET (auth), `store.wishlist.toggle` POST (auth). Guests' heart links
  to `store.account.login?back=<path>`; the login page keeps a local `back` as the intended URL.
- Only a parent or single product, published and active.

- [ ] Tests: `test_toggle_adds_and_removes`, `test_only_parents_and_single_products`, `test_the_page_lists_cards`,
  `test_the_layout_lists_the_ids`, `test_guests_are_sent_to_login_and_back`.

### Task 9: Customers in the Users admin

**Files:**
- Modify: `Modules/Core/Http/Controllers/Admin/UserController.php`, `Repositories/UserRepository.php`
  (`withCount('orders')`), `Pages/Users/Index.vue`, `Lang/ar.json`
- Test: `tests/Feature/Core/UserPagesTest.php` (add `test_customers_filter_and_order_count`)

### Task 10: Whole suite, type check, dev database

- [ ] `php artisan test`; `npm run typecheck`.
- [ ] `php artisan migrate --force`; seed `Modules\Items\database\seeders\PermissionSeeder`.
