# MALBO Phase 6A: B2B Wholesale Core Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Approved merchants buy wholesale from the same stock as the storefront:
- a public join form creates a `Merchant` account and a pending profile; staff approve, reject,
  suspend and reinstate it (each change logged with a note and its author, the merchant notified);
  each merchant has a sales rep, a price tier and an optional minimum-order override;
- price tiers (a discount off the base wholesale price), per-product tier prices that override it,
  and quantity breaks per product, all in one `WholesalePricing` service;
- a portal for approved merchants only: catalog, a size-run product grid, a wholesale cart of its
  own, checkout with a minimum order and payment terms, the merchant's orders;
- a B2B order is an ordinary order (channel B2B, awaiting confirmation, delivery fee 0), written by
  `OrderService`, its stock taken by `InventoryService`.

**Architecture:** a new nwidart module `Wholesale` (tables `wholesale_*`), which depends on Core,
Items and Store; they never depend on it.
- Enums: `MerchantStatus` (pending → approved | rejected; approved ↔ suspended), `PaymentTerms`
  (cash_on_delivery, bank_transfer offered; credit ready for 6B, not offered).
- Entities: `Merchant`, `MerchantStatusChange`, `Tier` (+ `TierTranslation`), `TierPrice`,
  `QuantityBreak`, `WholesaleCart`, `WholesaleCartItem`. Settings: `WholesaleSettings`
  (`minimum_order_value`, `minimum_order_pieces`, both nullable).
- Repositories: `MerchantRepository`, `TierRepository`, `WholesalePriceRepository` (tier prices and
  breaks), `WholesaleProductRepository` (the catalog and product-page reads over Items products),
  `WholesaleCartRepository`, `WholesaleOrderRepository` (a merchant's B2B orders).
- Services: `MerchantApplicationService` (join), `MerchantService` (profile, status), `TierService`,
  `WholesalePricing` (+ value object `PriceBook`), `WholesalePriceService` (tier prices, breaks,
  `wholesale_by_piece`), `WholesaleCatalog` (cards, the size-run grid), `WholesaleCartService`
  (incl. size-run rules), `WholesaleCheckoutService`, `MerchantOrders`.
- HTTP: middleware `merchant.approved`; admin `MerchantController`, `TierController`,
  `ProductPriceController`; portal `ApplyController`, `PortalController` (status page), `CatalogController`,
  `ProductController`, `CartController`, `CheckoutController`, `OrderController`.
- Portal routes are named `store.wholesale.*`: they are storefront pages (storefront layout, SSR,
  storefront Ziggy group, guests sent to the storefront sign-in) without being Store code.

**Extension points in other modules (small, explicit):**
- `Modules\Core\Enums\UserRole::MERCHANT` and `UserRole::nonStaff()`; `EnsureStaff` uses it.
- `OrderService::checkout(..., OrderChannel $channel = B2C)`: B2B orders always await confirmation.
- `Modules\Items\Support\ProductRowLinks`: modules add links to the admin product row menu.
- `OrderController::index`: a B2B row's name is the order's `customer_name` (the shop).
- `App\Support\FrontendTranslations`: the storefront gets the Store and Wholesale texts.
- `Modules/Items/Transformers/V1/ProductResource`: no `wholesale_price` in the public API.
- Wholesale `module.json` priority -1: its routes register before Store's `/{locale}/{path}` catch-all.

**Tech Stack:** Laravel 13, nwidart modules, PostgreSQL, Inertia 3 + Vue 3 + TypeScript, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-08-malbo-platform.md` (sections 13, 14, 15, 18, 22; phase 6).

## Global Constraints

- Follow `CLAUDE.md`: writes through services (one `DB::transaction` when several tables change),
  reads through repositories, thin controllers (`LayeringTest`), `jsonb`, indexed foreign keys,
  `whereLike(..., caseSensitive: false)`, Solar icons through `lib/icons.ts`, logical utilities, dark
  mode in the admin, every admin table a `DataTable` with filters allowed by `AdminFilter`.
- Migrations are additive: new `wholesale_*` tables and one column, `items_products.wholesale_by_piece`
  boolean default true. Partial unique indexes hold "one default tier" and "one open cart per user".
- `InventoryService` stays the only writer of stock, `OrderService` the only writer of orders.
- Wholesale prices are computed only by `WholesalePricing`, and leave the server only in responses of
  `merchant.approved` routes (and the admin). Guests, customers and merchants who are not approved
  never receive one: not in a page, a JSON response or the public API.
- Portal Vue pages follow the storefront's rules (no `dark:`, no admin components, texts in
  `Modules/Wholesale/Lang/ar.json` or Store's); admin pages follow the admin's.
- Tests extend `Tests\DatabaseTestCase` on the database `malbo_phase6_testing`. Single files while
  working; the whole suite once at the end. Do not commit.

## Review Focus

1. **A wholesale price reaching someone who may not see it.** Every portal route but apply/status sits
   behind `merchant.approved`, checked on every request against the saved status. Pinned in Task 5
   (`PortalVisibilityTest`: guest, customer, pending, rejected, suspended; pages, JSON, the public API).
2. **Overselling between B2C and B2B.** Both go through `InventoryService::sell` under row locks; the
   wholesale checkout re-reads lines and stock inside its transaction. Pinned in Task 8
   (`test_b2c_and_b2b_cannot_oversell_each_other`).
3. **Size runs bypassed.** `wholesale_by_piece = false` is enforced in `WholesaleCartService` on every
   write and again at checkout, not only in the browser. Pinned in Task 6.
4. **Price rules.** Override > wholesale price (variant, else parent) minus tier discount; then the
   highest break reached by the product's total quantity; rounded once. Pinned in Task 3 (unit tests).
5. **A merchant in the admin.** `EnsureStaff` treats Merchant as not staff. Pinned in Task 2.
6. **Double submit / flood.** Submit token + locked cart; `throttle:wholesale-checkout` and
   `throttle:wholesale-apply`. Pinned in Tasks 1 and 8.

---

### Task 1: Module, merchants and the join form

**Files:**
- Create: `Modules/Wholesale/{module.json,composer.json,config/config.php}`, `Providers/{Wholesale,Route,Repository,Sidebar,Event}ServiceProvider.php`,
  `routes/{web,api}.php`, `Lang/ar.json`, `database/seeders/{WholesaleDatabaseSeeder,PermissionSeeder}.php`
- Create: `database/migrations/2026_11_10_100001_create_wholesale_tiers_tables.php`,
  `2026_11_10_100002_create_wholesale_merchants_tables.php`, `2026_11_10_100003_create_wholesale_pricing_tables.php`,
  `2026_11_10_100004_create_wholesale_carts_tables.php`, `database/settings/2026_11_10_000001_create_wholesale_settings.php`
- Create: `Enums/MerchantStatus.php`, `Entities/{Merchant,MerchantStatusChange}.php`, `Repositories/{MerchantRepository,Contracts/...}.php`,
  `Services/MerchantApplicationService.php`, `Http/Requests/MerchantApplicationRequest.php`,
  `Http/Controllers/Storefront/{ApplyController,PortalController}.php`, `resources/js/Pages/Portal/{Apply,Status}.vue`
- Modify: `modules_statuses.json`, `config/settings.php`, `Modules/Core/Enums/UserRole.php`, `app/Support/FrontendTranslations.php`,
  `resources/js/lib/pages.ts`, `tests/Unit/Architecture/InertiaPagesExistTest.php` (module list)
- Test: `tests/Feature/Wholesale/MerchantApplicationTest.php`

**Interfaces:**
- `wholesale_merchants`: user_id (unique, cascade), shop_name, owner_name, city_id (nullOnDelete), phone,
  email, commercial_register, register_document_path/name (nullable, disk `local`), shop_address
  (nullable), status (indexed), tier_id, sales_rep_id (nullOnDelete, indexed), minimum_order_value
  decimal nullable, minimum_order_pieces int nullable, approved_at, timestamps.
  `wholesale_merchant_status_changes`: merchant_id, from_status nullable, to_status, note, created_by.
- `GET|POST /{locale}/wholesale/apply` (`store.wholesale.apply[.store]`, `throttle:wholesale-apply`:
  5 an hour per IP). `MerchantApplicationService::apply(array, ?UploadedFile, Request): Merchant` —
  user (role Merchant), pending profile, first status row, document on the private disk, signed in.
- `GET /{locale}/wholesale/account` (`store.wholesale.status`, auth): the profile's status, no prices.

- [ ] Tests: `test_the_form_lists_cities`, `test_applying_creates_a_pending_merchant_and_signs_in`,
  `test_the_register_document_is_stored_privately`, `test_the_document_must_be_a_pdf_or_image_under_5mb`,
  `test_the_email_must_be_free`, `test_applying_is_throttled_per_ip`, `test_the_status_page_shows_pending_review_without_prices`,
  `test_a_merchant_can_sign_in_on_the_storefront`.

### Task 2: Merchant statuses and the admin

**Files:**
- Create: `Services/MerchantService.php`, `Notifications` (GeneralNotification reused), `Http/Controllers/Admin/MerchantController.php`,
  `Http/Requests/Admin/{MerchantRequest,MerchantStatusRequest}.php`, `resources/js/Pages/Merchant/{Index,Edit}.vue`, `.../Merchant/parts/*`
- Modify: `app/Http/Middleware/EnsureStaff.php`, `Modules/Core/Support/PermissionLabels.php`, `resources/js/Components/layout/navIcons.ts`
- Test: `tests/Feature/Wholesale/MerchantStatusTest.php`, `tests/Feature/Wholesale/MerchantPagesTest.php`

**Interfaces:**
- `MerchantService::changeStatus(Merchant, MerchantStatus, ?string $note, int $userId)` — on the locked
  row; refused transitions say why; a note is required to reject or suspend; one history row; the
  merchant's user gets a database notification after the commit. `update(Merchant, array)` — profile,
  tier, sales rep (a staff user), minimum-order override.
- Routes (`/admin/wholesale/merchants`): `merchants.index` (View List; filters status, tier, sales rep,
  city), `merchants.edit` (View List), `merchants.update`, `merchants.status` (Update),
  `merchants.document` (View List, `signed`, 10-minute links).

- [ ] Tests: `test_transitions_follow_the_rules`, `test_each_change_is_logged_with_its_note_and_author`,
  `test_reject_and_suspend_need_a_note`, `test_the_merchant_is_notified`, `test_a_merchant_is_kept_out_of_the_admin`,
  `test_the_list_is_shaped_for_the_table`, `test_each_filter`, `test_the_edit_page`, `test_update_profile_tier_rep_and_minimum`,
  `test_the_sales_rep_must_be_staff`, `test_status_actions_from_the_page`, `test_the_document_needs_a_signed_link`,
  `test_every_action_needs_its_permission`, `test_super_admin_has_the_permissions_and_the_sidebar_group`.

### Task 3: Price tiers and `WholesalePricing`

**Files:**
- Create: `Entities/{Tier,TierTranslation,TierPrice,QuantityBreak}.php`, `Repositories/{TierRepository,WholesalePriceRepository}.php` (+ contracts),
  `Services/{TierService,WholesalePricing}.php`, `Support/PriceBook.php`, `Settings/WholesaleSettings.php`,
  `Http/Controllers/Admin/TierController.php`, `Http/Requests/Admin/{TierRequest,WholesaleSettingsRequest}.php`,
  `resources/js/Pages/Tier/{Index,Add,Edit,TierFields,MinimumOrderCard}.vue`
- Test: `tests/Unit/Wholesale/PriceBookTest.php`, `tests/Feature/Wholesale/WholesalePricingTest.php`, `tests/Feature/Wholesale/TierPagesTest.php`

**Interfaces:**
- `wholesale_tiers` (discount_percent decimal(5,2) default 0, sort_order, is_default; partial unique on
  is_default), `wholesale_tier_translations` (name), `wholesale_tier_prices` (product_id, tier_id,
  price; unique pair), `wholesale_quantity_breaks` (product_id, min_quantity, discount_percent; unique pair).
- `PriceBook` (pure): `base(Product $item): ?float` — the tier override, else the wholesale price
  (a variant's, else its parent's) minus the tier discount; `breakPercent(int $groupId, int $qty)`;
  `unit(Product $item, int $groupQuantity): ?float` — rounded to 2 once.
- `WholesalePricing::tierFor(Merchant): ?Tier` (its tier, else the default, else none = 0 %);
  `book(?Tier, list<int> $productIds): PriceBook`; `suggestedRetail(Product): Price` (PricingService).
- Routes `wholesale-tiers.*` (index/add/store/edit/update/destroy, `settings`), permissions
  `Wholesale Tier View List|Create|Update|Delete`. The default tier cannot be deleted.

- [ ] Unit tests: `test_override_wins`, `test_variant_price_minus_tier_discount`, `test_variant_falls_back_to_parent_price`,
  `test_no_price_means_not_sold_wholesale`, `test_zero_is_no_price`, `test_highest_break_reached_applies_on_top`,
  `test_break_counts_the_whole_product_quantity`, `test_override_with_break`, `test_rounding_once`, `test_no_tier_means_no_discount`.
- [ ] Feature tests: `test_merchant_tier_else_default_else_none`, `test_book_loads_overrides_and_breaks_for_the_tier`,
  tier pages CRUD, `test_one_default_tier`, `test_the_default_tier_cannot_be_deleted`, `test_minimum_order_settings`, permissions.

### Task 4: Product wholesale prices (admin)

**Files:**
- Create: `Services/WholesalePriceService.php`, `Http/Controllers/Admin/ProductPriceController.php`,
  `Http/Requests/Admin/ProductPriceRequest.php`, `resources/js/Pages/ProductPrice/{Index,Edit}.vue`
- Create: `Modules/Items/Support/ProductRowLinks.php`; modify `Items ProductController::index` (`rowLinks`), `Pages/Product/Index.vue`
- Test: `tests/Feature/Wholesale/ProductPricePagesTest.php`

**Interfaces:**
- Routes `wholesale-prices.index|edit|update` (`Wholesale Price View List|Update`). The edit page: each
  sellable item (variants, or the product) with its wholesale price and one override per tier; the
  breaks; "sold by the piece". `WholesalePriceService::save(Product $parent, array)` replaces them
  in one transaction; items of another product are refused.

- [ ] Tests: `test_the_list`, `test_the_edit_page_lists_items_and_tiers`, `test_saving_overrides_breaks_and_by_piece`,
  `test_an_item_of_another_product_is_refused`, `test_the_product_row_links_here`, permissions.

### Task 5: Portal access and catalog

**Files:**
- Create: `Http/Middleware/EnsureApprovedMerchant.php`, `Repositories/WholesaleProductRepository.php`, `Services/WholesaleCatalog.php`,
  `Http/Controllers/Storefront/{CatalogController,ProductController}.php`, `resources/js/Pages/Portal/{Catalog,Product}.vue` + parts
- Modify: `Modules/Items/Transformers/V1/ProductResource.php` (no `wholesale_price`)
- Test: `tests/Feature/Wholesale/PortalVisibilityTest.php`, `tests/Feature/Wholesale/CatalogTest.php`, `tests/Feature/Wholesale/ProductGridTest.php`

**Interfaces:**
- `merchant.approved`: guest → storefront sign-in (intended URL kept); signed in but not a merchant → 403;
  a merchant not approved → `store.wholesale.status` (JSON: 403).
- `GET /{locale}/wholesale` (`store.wholesale.catalog`; `q`, `category`, page): published, active parents
  sold wholesale to the merchant's tier; cards with the tier price ("from" when variants differ), the
  suggested retail price, availability.
- `GET /{locale}/wholesale/product/{slug}`: the grid — rows per color, a cell per size: variant id,
  live available quantity (null = no limit), unit price for the tier, in-cart quantity; the breaks; `by_piece`.

- [ ] Tests: visibility for guest/customer/pending/rejected/suspended on catalog, product, cart, checkout
  and JSON; `test_the_public_api_has_no_wholesale_price`; `test_the_store_product_page_has_no_wholesale_price`;
  catalog `test_only_products_sold_wholesale`, `test_search_and_category`, `test_cards_show_tier_price_from_and_suggested_retail`;
  grid `test_rows_per_color_and_cells_per_size`, `test_available_quantities_are_live`.

### Task 6: Wholesale cart

**Files:**
- Create: `Entities/{WholesaleCart,WholesaleCartItem}.php`, `Repositories/WholesaleCartRepository.php`, `Services/WholesaleCartService.php`,
  `Http/Controllers/Storefront/CartController.php`, `Http/Requests/CartQuantitiesRequest.php`, `resources/js/Pages/Portal/Cart.vue`
- Test: `tests/Feature/Wholesale/WholesaleCartTest.php`

**Interfaces:**
- `PUT /{locale}/wholesale/cart/{product}` sets the cart's quantities of one product (its variants);
  `DELETE` removes them. `WholesaleCartService::set(User, Product $parent, array<int,int>)`; `lines(cart, tier)`
  (live price with breaks, availability, problems); `summary()` (subtotal, pieces, minimum: met, missing).
- Size runs: a product with `wholesale_by_piece = false` takes, per color, the same quantity of every
  available size of that color (N runs), and nothing of a size that is not available.

- [ ] Tests: `test_set_creates_the_cart_for_the_merchant`, `test_quantities_are_replaced_per_product`, `test_zero_removes_a_line`,
  `test_more_than_the_stock_is_refused`, `test_an_item_of_another_product_is_refused`, `test_by_the_piece_takes_any_quantities`,
  `test_size_runs_are_enforced_on_the_server`, `test_breaks_are_recomputed_on_the_total_quantity`, `test_the_b2c_cart_is_untouched`,
  `test_the_minimum_order_is_reported`.

### Task 7: Checkout, B2B orders and the merchant's orders

**Files:**
- Create: `Enums/PaymentTerms.php`, `Services/{WholesaleCheckoutService,MerchantOrders}.php`, `Repositories/WholesaleOrderRepository.php`,
  `Http/Controllers/Storefront/{CheckoutController,OrderController}.php`, `Http/Requests/WholesaleCheckoutRequest.php`,
  `resources/js/Pages/Portal/{Checkout,CheckoutDone,Orders,OrderDetail}.vue`
- Modify: `Modules/Items/Services/OrderService.php` (`checkout(..., $channel)`), `Items OrderController::index` (B2B name)
- Test: `tests/Feature/Wholesale/WholesaleCheckoutTest.php`, `tests/Feature/Wholesale/MerchantOrdersTest.php`

**Interfaces:**
- `POST /{locale}/wholesale/checkout` (`throttle:wholesale-checkout`, 6 a minute per user): address
  (`saved` + address_id of the merchant's Store addresses, or `shop`), notes, payment_terms, submit_token.
  `WholesaleCheckoutService::place(WholesaleCart, Merchant, array): Order` — locks the cart (a second
  submit gets the same order), re-prices, checks problems, runs and the minimum, then
  `OrderService::checkout($header, $lines, OrderChannel::B2B)`: status AWAITING_CONFIRMATION, unit
  prices = wholesale prices, delivery fee 0, `customer_name` = shop name.
- `GET /{locale}/wholesale/checkout/done`, `/{locale}/wholesale/orders`, `/{locale}/wholesale/orders/{number}` (B2B orders of the merchant).

- [ ] Tests: `test_checkout_places_a_b2b_order_awaiting_confirmation`, `test_unit_prices_are_wholesale_prices_and_delivery_is_free`,
  `test_stock_is_taken`, `test_below_the_minimum_is_refused_with_what_is_missing`, `test_merchant_override_of_the_minimum`,
  `test_a_saved_address_or_the_shop_address`, `test_bank_transfer_terms`, `test_double_submit_places_one_order`,
  `test_checkout_is_rate_limited`, `test_the_admin_list_shows_b2b_with_the_shop_name`, orders list and detail (own orders only).

### Task 8: Shared inventory

- [ ] Test (`tests/Feature/Wholesale/SharedInventoryTest.php`): `test_b2c_and_b2b_cannot_oversell_each_other`,
  `test_a_cancelled_b2b_order_restocks`.

### Task 9: Whole suite, type check, dev database

- [ ] `php artisan test` (on `malbo_phase6_testing`); `npx vue-tsc --noEmit`.
- [ ] `php artisan migrate --force` on `malbo_app`; seed `Modules\Wholesale\database\seeders\WholesaleDatabaseSeeder`
  and `Modules\Core\database\seeders\PermissionSeeder` is NOT rerun (it deletes permissions); the Merchant role is created by the Wholesale seeder.
