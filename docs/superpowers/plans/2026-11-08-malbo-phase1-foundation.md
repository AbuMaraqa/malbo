# MALBO Phase 1: Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the order record and the stock fit MALBO:
- Hebrew is supported.
- Orders carry their channel and customer, a snapshot of what was sold, the MALBO statuses and
  internal notes.
- One stock ledger changes stock for every sale, cancellation and return, and never oversells.

**Architecture:**
- A new `InventoryService` in the Items module is the only code that changes `stock_quantity`. It
  locks product rows, writes a `StockMovement` per change and reports items whose stock fell low.
- `OrderService` becomes the only place orders are written. The admin pages and the mobile API both
  call it, and it calls `InventoryService`.

**Tech Stack:** Laravel 13, nwidart modules, PostgreSQL, Inertia 3 + Vue 3 + TypeScript, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-08-malbo-platform.md` (sections 1, 18, 19, 22 and phase 1).

## Global Constraints

- Follow `CLAUDE.md`:
  - Writes go through services, inside `DB::transaction` when more than one table changes.
  - Reads go through repositories.
  - Use `jsonb`, never `json`. Foreign keys are indexed.
  - Search with `whereLike(..., caseSensitive: false)`.
- Items tables use the prefix `config('items.table_prefix')` (`items_`). New migrations go in
  `Modules/Items/database/migrations`.
- Every new `t('...')` / `__('...')` text gets an entry in `Modules/Items/Lang/ar.json` (or
  `Modules/Core/Lang/ar.json` for Core texts), because `ArabicTextsTest` enforces it.
- Tests extend `Tests\DatabaseTestCase`. Run single files while working (the machine is low on RAM)
  and the whole suite once at the end.
- Keep the existing enum case names and integer values of `OrderStatus`: stored orders and the Vue
  pages use them. Only labels, transitions and new cases change.
- Do not commit unless the user asks.

## Review Focus

1. **Two orders race for the last item.** One succeeds and the other gets a validation error;
   stock never goes below 0. Pinned in Task 4: two `sell()` calls on one unit in the same test, the
   second throws.
2. **An admin edits a confirmed order's quantity.** Stock moves by the difference only, never by the
   full quantity again. Pinned in Task 5.
3. **An order is cancelled, then a cancel is submitted again.** Stock comes back once. The transition
   to the same status is a no-op. Pinned in Task 5.
4. **A product with `manage_stock = false`, or one that allows backorders.** No error. A product
   that allows backorders may go below 0. Pinned in Task 4.
5. **The mobile API sends a status the order cannot reach.** It gets 422, as the admin does, and
   nothing changes. Pinned in Task 6.

---

### Task 1: Hebrew locale

**Files:**
- Modify: `config/app.php:84-87`
- Modify: `config/translatable.php:13-20`
- Create: `lang/he.json`, and `Modules/{Core,Items,Store,Branch,GeoLocation}/Lang/he.json`, each `{}`
- Test: `tests/Feature/Core/HebrewLocaleTest.php`

**Interfaces:**
- Produces: locale code `he`, rtl. `App\Support\Locales::keys()` returns `['ar', 'en', 'he']`.

- [ ] **Step 1: Write the failing test** `HebrewLocaleTest` with these tests:
  - `test_hebrew_is_a_right_to_left_locale`: assert `Locales::keys() === ['ar', 'en', 'he']` and `Locales::isRtl('he')`.
  - `test_the_storefront_answers_in_hebrew`: `get(siteUrl('/he'))->assertOk()`, and the Inertia props have `locale === 'he'` and `dir === 'rtl'`.
  - `test_a_missing_hebrew_text_falls_back_to_english`: `app()->setLocale('he'); assertSame('Dashboard', __('Dashboard'))`.
- [ ] **Step 2: Run it.** `php artisan test tests/Feature/Core/HebrewLocaleTest.php`. Expected: FAIL (`he` not in the keys).
- [ ] **Step 3: Implement.**
  - Add `'he' => ['native' => 'עברית', 'dir' => 'rtl', 'flag' => 'IL']` to `config/app.php` `locales`.
  - Add `'he'` to `config/translatable.php` `locales`.
  - Create the empty `he.json` files. The keys are English, so a missing Hebrew text shows the
    English key, which is the intended fallback until MALBO sends translations.
- [ ] **Step 4: Run the test.** Expected: PASS.
- [ ] **Step 5: Run `tests/Feature tests/Unit` and fix what a third locale breaks.** Likely spots:
  - admin forms and requests that loop over `config('app.locales')` and require each locale;
  - `InertiaPagesExistTest`;
  - sitemap and alternate-link tests that expect two locales.

  Hebrew fields stay optional: only the request's own locale is required, as for `en` today.
  Expected after the fixes: all pass.

### Task 2: Order statuses per MALBO

**Files:**
- Modify: `Modules/Items/Enums/OrderStatus.php`
- Modify: `Modules/Items/Lang/ar.json`
- Test: `tests/Unit/Items/OrderStatusTest.php`; existing `tests/Feature/Items/OrderPagesTest.php`

**Interfaces:**
- Produces:
  - New cases `AWAITING_CONFIRMATION = 10` and `EXCHANGED = 11`.
  - `OrderStatus::releasesStock(): bool`, true for `CANCELLED`, `REJECTED`, `RETURNED`.
  - `OrderStatus::isFinal(): bool`, true for `CANCELLED`, `REJECTED`, `RETURNED`, `EXCHANGED`.
  - Labels:

    | Case | English | Arabic |
    |------|---------|--------|
    | `PENDING` | `New` | جديد |
    | `AWAITING_CONFIRMATION` | `Awaiting confirmation` | بانتظار التأكيد |
    | `OUT_FOR_DELIVERY` | `With courier` | مع شركة التوصيل |
    | `EXCHANGED` | `Exchanged` | استبدال |

- [ ] **Step 1: Write the failing test.** `OrderStatusTest` has a data provider of `[from, to, allowed]` covering the whole table below. The test asserts `$from->canTransitionTo($to) === $allowed`. It also has `test_final_statuses_cannot_move` and `test_released_stock_statuses`.

  Allowed transitions (the same status is always allowed; anything not listed is refused):

  | From | To |
  |------|----|
  | PENDING | AWAITING_CONFIRMATION, CONFIRMED, REJECTED, CANCELLED |
  | AWAITING_CONFIRMATION | CONFIRMED, REJECTED, CANCELLED |
  | CONFIRMED | PREPARING, CANCELLED |
  | PREPARING | OUT_FOR_DELIVERY, READY_FOR_PICKUP, CANCELLED |
  | READY_FOR_PICKUP | OUT_FOR_DELIVERY, DELIVERED, CANCELLED |
  | OUT_FOR_DELIVERY | DELIVERED, RETURNED |
  | DELIVERED | RETURNED, EXCHANGED |
  | CANCELLED, REJECTED, RETURNED, EXCHANGED | none (final) |

- [ ] **Step 2: Run it.** `php artisan test tests/Unit/Items/OrderStatusTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement.**
  - Add the cases, labels, colors (`AWAITING_CONFIRMATION` warning, `EXCHANGED` info), `releasesStock()` and `isFinal()`.
  - Replace `canTransitionTo` with the table, rejecting moves out of `isFinal()` statuses.
  - Add the Arabic entries.
- [ ] **Step 4: Run** `OrderStatusTest` and `OrderPagesTest`. Update `OrderPagesTest` expectations that encoded the old table (`test_the_add_page_offers_what_can_follow_pending`, `test_a_finished_order_cannot_change_status`) to the new one. Expected: PASS.

### Task 3: Order channel, customer, internal notes, item snapshots

**Files:**
- Create: `Modules/Items/database/migrations/2026_11_08_000001_add_malbo_fields_to_orders.php`
- Create: `Modules/Items/Enums/OrderChannel.php`
- Modify: `Modules/Items/Entities/Order.php`, `Modules/Items/Entities/OrderItem.php`
- Modify: `Modules/Items/Http/Requests/Admin/OrderRequest.php`
- Modify: `Modules/Items/Services/OrderService.php` (`COLUMNS`, line creation)
- Modify: `Modules/Items/Http/Controllers/Admin/OrderController.php` (edit/index props)
- Modify: `Modules/Items/resources/js/Pages/Order/parts/OrderInfoCard.vue`, `Modules/Items/resources/js/Pages/Order/Add.vue`, `Modules/Items/resources/js/Pages/Order/Edit.vue`
- Test: `tests/Feature/Items/OrderSnapshotTest.php`

**Interfaces:**
- Produces:
  - Order columns:
    - `channel` string(8), not null, default `'b2c'`, cast `OrderChannel`;
    - `customer_name` string nullable;
    - `customer_email` string nullable;
    - `internal_notes` text nullable.
  - `OrderChannel: string { case B2C = 'b2c'; case B2B = 'b2b'; getLabel() }`.
  - OrderItem columns:
    - `sku` string nullable;
    - `product_name` jsonb nullable: `{locale: name}` over every locale the product has;
    - `options` jsonb nullable: `list<{name: {locale: string}, value: {locale: string}}>`, built from
      the variant's `attributeValues` and their `attribute`.

    Both jsonb columns are cast to `array`.
  - `OrderService::snapshot(Product $product): array{sku: ?string, product_name: array, options: array}`
    (public, static-free). A variant's name map is its parent's name map.

- [ ] **Step 1: Write the failing tests** in `OrderSnapshotTest`:
  - `test_a_new_line_keeps_sku_name_and_options`: create a parent with a variant valued Color=Black and Size=L. Post an admin order for the variant. Assert the item's `sku` equals the variant's SKU, `product_name['ar']` is the parent's Arabic name, and `options` holds two entries whose `value['en']` are `Black` and `L`.
  - `test_the_snapshot_survives_a_rename`: rename the product after ordering, then assert the item's `product_name` is unchanged.
  - `test_internal_notes_and_customer_are_saved_but_not_sent_to_the_customer_api`: the admin saves `internal_notes`, `customer_name` and `customer_email`. `GET /api/v1/items/orders/{id}` as the owner has no `internal_notes` key.
  - `test_channel_defaults_to_b2c`.
- [ ] **Step 2: Run it.** Expected: FAIL (unknown columns).
- [ ] **Step 3: Implement.**
  - Write the migration.
  - Update the model `$fillable` and `$casts`.
  - In `OrderRequest`: `internal_notes` nullable string max:5000, `customer_name` nullable string max:255, `customer_email` nullable email max:255.
  - Add the three fields to `OrderService::COLUMNS`. Every line created (create and update) merges `snapshot($product)`; an updated line keeps its snapshot unless its product changed.
  - In `OrderInfoCard.vue`, add text inputs for customer name and email, and a textarea "Internal notes" with hint "Only staff see this". Add the Arabic texts.
- [ ] **Step 4: Run** `OrderSnapshotTest` and `OrderPagesTest`. Expected: PASS.

### Task 4: Stock ledger and InventoryService

**Files:**
- Create: `Modules/Items/database/migrations/2026_11_08_000002_create_stock_movements_table.php`
- Create: `Modules/Items/Entities/StockMovement.php`
- Create: `Modules/Items/Enums/StockMovementType.php`
- Create: `Modules/Items/Services/InventoryService.php`
- Create: `Modules/Items/Exceptions/InsufficientStock.php` (extends `ValidationException`)
- Test: `tests/Feature/Items/InventoryServiceTest.php`

**Interfaces:**
- Produces:
  - Table `items_stock_movements`:
    - `id`;
    - `product_id` foreignId → `items_products`, cascade, indexed;
    - `type` string(16);
    - `quantity` integer, signed: negative takes stock away;
    - `balance_after` integer;
    - `order_id` foreignId nullable → `items_orders`, nullOnDelete, indexed;
    - `note` string nullable;
    - `created_by` foreignId nullable → users, nullOnDelete, indexed;
    - timestamps.
  - `StockMovementType: string`, cases `SALE='sale'`, `CANCEL='cancel'`, `RETURN='return'`, `ADJUST='adjust'`, `INCOMING='incoming'`, with `getLabel()`.
  - `InventoryService` methods:
    - `sell(array $quantities, ?Order $order = null, ?int $userId = null): void`. `$quantities` is `array<int productId, int quantity>`. Positive takes stock, negative gives it back as `SALE`. Used for order lines and edits.
    - `restock(array $quantities, StockMovementType $type, ?Order $order = null, ?int $userId = null): void`. Type is `CANCEL` or `RETURN`; quantities are positive.
    - `setLevel(Product $product, int $quantity, StockMovementType $type, ?string $note = null, ?int $userId = null): void`. Type is `ADJUST` or `INCOMING`; it writes the difference.
  - All three must run inside the caller's transaction and lock rows with `lockForUpdate()` in ascending id order.
  - After the commit (`DB::afterCommit`), they call `LowStockAlert::send(Collection $products)` from Task 7 for each product that crossed down to `low_stock_threshold` or below. Until Task 7 exists, collect the crossings in `InventoryService::$lowStock` (public read-only array of product ids) so Task 4 can test them.
  - Rules:
    - `manage_stock = false`: no stock change, no movement.
    - `allow_backorder = true`: may go negative.
    - Otherwise a shortage throws `InsufficientStock` for key `items`, with message `__('Only :count left of :name.')`. Everything rolls back.
    - `stock_status` is recomputed on each change: `in_stock` if above 0, else `on_backorder` if it allows backorders, else `out_of_stock`.
- [ ] **Step 1: Write the failing tests** in `InventoryServiceTest`:
  - `test_a_sale_takes_stock_and_writes_a_movement`: 5 in stock, sell 2 → 3, one movement `SALE` −2 with `balance_after` 3.
  - `test_the_last_unit_cannot_be_sold_twice`: 1 in stock. `sell([id => 1])` succeeds. A second `sell([id => 1])` throws `InsufficientStock`. Stock is 0 and there is exactly one movement.
  - `test_a_shortage_on_one_line_rolls_back_every_line`: inside `DB::transaction`, sell A (enough) and B (short). Expect `InsufficientStock`, and A is unchanged.
  - `test_unmanaged_stock_is_left_alone` and `test_backorder_may_go_below_zero` (status `on_backorder`).
  - `test_restock_gives_stock_back`: `CANCEL` +2.
  - `test_set_level_writes_the_difference`: 10 → 4, so `ADJUST` −6.
  - `test_crossing_the_threshold_is_reported_once`: threshold 3, stock 5. Sell 2: reported. Sell 1 more: not reported again.
  - `test_status_follows_the_stock`.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Implement** the migration, entity (with `product()`, `order()`, `createdBy()` relations, `setTable` with the prefix, `$casts` type enum), enum, exception and service. Add the Arabic texts.
- [ ] **Step 4: Run it.** Expected: PASS.

### Task 5: Orders move stock (admin path)

**Files:**
- Modify: `Modules/Items/Services/OrderService.php`
- Test: `tests/Feature/Items/OrderStockTest.php`

**Interfaces:**
- Consumes: `InventoryService::sell`, `InventoryService::restock`, `OrderStatus::releasesStock()`, `OrderStatus::isFinal()`.
- Produces: `OrderService` constructor gains `private readonly InventoryService $inventory`.

- [ ] **Step 1: Write the failing tests** in `OrderStockTest`, through the admin routes:
  - `test_creating_an_order_takes_its_stock`;
  - `test_an_order_beyond_stock_is_refused_and_nothing_is_saved`: 422 on `items`, no order row, stock unchanged;
  - `test_editing_a_quantity_moves_only_the_difference`: 3 → 5 takes 2 more; 5 → 1 gives 4 back;
  - `test_removing_a_line_or_changing_its_product_gives_the_old_stock_back`;
  - `test_cancelling_gives_the_stock_back_once`: cancel, then submit cancelled again, and stock rises only once;
  - `test_returned_gives_the_stock_back_as_return`: the movement type is `RETURN`;
  - `test_lines_of_a_final_order_are_not_restocked_again_on_edit`.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Implement.**
  - `create`: after building the lines, call `sell(quantitiesByProduct, $order, $userId)` inside the transaction.
  - `update`: compare the saved lines' quantities by product with the new ones, then call `sell` with the per-product differences. When `$new->releasesStock()` and `! $old->releasesStock()`, call `restock` with the order's remaining quantities, using type `RETURN` for `RETURNED` and `CANCEL` otherwise. Skip the line difference when the order was already in a stock-releasing status.
- [ ] **Step 4: Run** `OrderStockTest` and `OrderPagesTest`. Expected: PASS. Existing tests that create orders now need stock on their products: give the test helper products `stock_quantity` 100, or `manage_stock` false.

### Task 6: The mobile API writes through OrderService

**Files:**
- Modify: `Modules/Items/Services/OrderService.php` (add `place`, `changeStatus`)
- Modify: `Modules/Items/Http/Controllers/Api/V1/OrderController.php` (`store`, `updateOrderStatus`)
- Modify: `tests/Unit/Architecture/LayeringTest.php` `KNOWN` (remove the entries this task fixes)
- Test: `tests/Feature/Api/OrderApiTest.php` (add tests)

**Interfaces:**
- Produces:
  - `OrderService::place(array $header, list<array{product_id:int, quantity:int, price:float, total_price:float, notes:?string, options_to_attach:array}> $lines, int $userId): Order`.
    - Starting status: `AWAITING_CONFIRMATION` when `payment_method` is CASH, else `PENDING`.
    - Channel `B2C`, with snapshots.
    - It calls `sell`, then attaches the addon options.
  - `OrderService::changeStatus(Order $order, OrderStatus $to, int $userId): Order`. It locks the order, checks the transition (the same `ValidationException` as `update`), restocks on release and notifies after the commit.
- [ ] **Step 1: Write the failing tests** in `OrderApiTest`:
  - `test_a_cash_order_waits_for_confirmation`;
  - `test_an_api_order_takes_stock_and_is_refused_beyond_it`: 422, no order;
  - `test_the_api_refuses_an_unreachable_status`: `PENDING` → `DELIVERED` gives 422 and the status is unchanged;
  - `test_the_api_cancel_gives_stock_back`.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Implement.** The controller keeps request parsing and pricing (`unitPrice`, addon validation) and hands `$header` and `$lines` to `place`. The 500 catch stays for unexpected errors, but `ValidationException` is rethrown so it reaches the client as 422. `updateOrderStatus` calls `changeStatus`.
- [ ] **Step 4: Run** `OrderApiTest`, `OrderPagesTest` and `LayeringTest`. Expected: PASS.

### Task 7: Stock from the product form, and low-stock alerts

**Files:**
- Create: `Modules/Items/Notifications/LowStockAlert.php` (static `send(Collection $products): void`)
- Modify: `Modules/Items/Services/InventoryService.php` (call `LowStockAlert::send` after the commit; drop `$lowStock` or keep it as test support)
- Modify: `Modules/Items/Services/ProductService.php` (`fillAndSave`)
- Modify: `Modules/Items/Services/ProductVariations.php` (`save`)
- Test: `tests/Feature/Items/ProductStockTest.php`

**Interfaces:**
- Consumes: `InventoryService::setLevel`.
- Produces:
  - When the product form changes `stock_quantity`, the change goes through `setLevel(..., ADJUST, __('Changed in the product form'), $userId)`. A new product's first stock is an `INCOMING` movement from 0.
  - `LowStockAlert::send` notifies, with one `GeneralNotification` per product, every active user who has the permission `Product Update` (spatie `User::permission('Product Update')`, which includes role permissions).
    - Title: `__('Low stock')`.
    - Message: `__(':name has :count left.')`.
    - It never throws: failures are logged as in `OrderService::notifyStatusChanged`.
- [ ] **Step 1: Write the failing tests** in `ProductStockTest`:
  - `test_changing_stock_in_the_form_writes_an_adjust_movement`: edit a product from 10 to 4 and assert one `ADJUST` −6;
  - `test_a_new_product_records_its_opening_stock_as_incoming`;
  - `test_a_variation_stock_change_is_recorded`;
  - `test_staff_who_manage_products_get_a_low_stock_notification`: use `Notification::fake()`. A user with `Product Update` is notified; a Customer is not.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Implement.** `ProductService` and `ProductVariations` remove `stock_quantity` from the attributes they fill. When the value differs from the stored one, they call `setLevel` after the save, in the same transaction. Add the Arabic texts.
- [ ] **Step 4: Run** `ProductStockTest`, `ProductServiceTest` and `ProductPagesTest`. Expected: PASS.

### Task 8: Whole suite and type check

- [ ] **Step 1:** `php artisan test`. Expected: all pass.
- [ ] **Step 2:** `npm run typecheck`. Expected: no errors.
- [ ] **Step 3:** `php artisan migrate --force` on the dev database, then sign in at https://malbo.test/admin. Create an order, then cancel it, and check the product's stock goes down, then back up.
