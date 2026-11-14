# MALBO Phase 5: Admin Operations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The tools MALBO's staff need to run the store day to day:
- a printable invoice (A4) and a waybill (100 × 150 mm) per order, and the waybills of many orders
  in one PDF;
- shipments: a courier, a tracking number and a status per order, moving the order along
  (with courier, delivered, returned) through the order rules, shown on the tracking page;
- a global, read-only activity log, secrets masked;
- stock pages: the movement ledger, low stock, receiving goods (INCOMING) and manual adjustments (ADJUST);
- dashboard cards for what needs attention: orders awaiting confirmation, low stock, open returns.

**Architecture:**
- Items module:
  - `OrderDocument` (Support): an order as the invoice and the waybill print it, in one locale.
    `ItemsPdfService::invoice(Order, locale)`, `waybills(Collection<Order>)` render the Blade views
    `items::pdf.invoice` and `items::pdf.waybill` with mPDF (DejaVu Sans: Arabic and Hebrew, RTL).
  - `Shipment`, `ShipmentEvent`, enum `ShipmentStatus`, `ShipmentRepository`, `ShipmentService`
    (the only writer), `Couriers\CourierGateway` (contract), `Couriers\ManualCourier`,
    `Couriers\CourierRegistry` (reads `config/couriers.php`).
  - `InventoryService::receive()` and `adjust()`; `ProductRepository::lowStockQuery()`, `isSellable()`;
    `StockMovementRepository` (the ledger table).
  - Admin controllers: `OrderDocumentController` (invoice, waybill, waybills), `ShipmentController`
    (index, store, status), `StockController` (movements, low stock, receive, adjust, product lookup).
- Core module: `ActivityLogRepository`, `Support\ActivityLogRows` (subject labels, masked changes),
  admin `ActivityLogController` (index); `DashboardService` gains `alerts`.
- Store module: `OrderTracking::result()` gains `shipment` (courier name, tracking number).

**Tech Stack:** Laravel 13, nwidart modules, PostgreSQL, Inertia 3 + Vue 3 + TypeScript, mPDF 8, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-08-malbo-platform.md` (sections 11, 18, 19; phase 5).

## Global Constraints

- Follow `CLAUDE.md`: writes through services (one `DB::transaction` when several tables change),
  reads through repositories, thin controllers (`LayeringTest`), `jsonb`, indexed foreign keys,
  `whereLike(..., caseSensitive: false)`, Solar icons through `lib/icons.ts`, logical utilities, dark
  mode in the admin, every admin table a `DataTable` with filters allowed by `AdminFilter`.
- Migrations are additive (two new tables). A partial unique index (PostgreSQL) holds "one active
  shipment per order".
- `InventoryService` stays the only writer of stock, `OrderService` the only writer of orders,
  `ShipmentService` the only writer of shipments. A shipment moves its order only through
  `OrderService::changeStatus`.
- PDFs reuse `ItemsPdfService` (mPDF, DejaVu Sans, a temp dir for font metrics only). Locale is
  switched for the render and restored afterwards.
- Admin texts in the module's `Lang/ar.json` (and `he.json` for the PDF texts); storefront texts in
  `Modules/Store/Lang/ar.json`.
- Tests extend `Tests\DatabaseTestCase`. Single files while working; the whole suite once at the end. Do not commit.

## Review Focus

1. **Stock moved twice when a shipment comes back.** `returned_to_sender` calls
   `changeStatus(order, RETURNED)` once, which restocks the lines minus units a completed return
   already gave back. Pinned in Task 2 (`test_returned_to_sender_returns_the_order_and_restocks_once`).
2. **A shipment moving an order where it cannot go.** The order's transition is checked on the
   locked order before anything is written; a refusal rolls back the shipment too and says why.
   Pinned in Task 2 (`test_a_transition_the_order_cannot_make_is_refused_and_nothing_changes`).
3. **Secrets in the activity log.** Any attribute whose name holds password, token or secret is
   masked, old and new, at any depth. Pinned in Task 4 (`test_secrets_are_masked`).
4. **An adjustment below zero.** Checked on the locked row; a backorder product may go below.
   Pinned in Task 5 (`test_adjust_refuses_going_below_zero_without_backorder`).
5. **Documents for another order / without permission.** Every document route needs `Order Invoice`;
   the bulk route takes at most 100 ids. Pinned in Task 1.

---

### Task 1: Invoice and waybill PDFs

**Files:**
- Create: `Modules/Items/Support/OrderDocument.php`, `Http/Controllers/Admin/OrderDocumentController.php`,
  `resources/views/pdf/{invoice,waybill}.blade.php`,
  `resources/js/Pages/Order/parts/{OrderPrintActions,OrderRowPrint}.vue`
- Modify: `Services/ItemsPdfService.php` (`invoice`, `waybills`), `Entities/Order.php` (`city`, `district`,
  `shipment`), `Repositories/{OrderRepository,Contracts/OrderRepositoryInterface}.php` (`forDocuments`),
  `routes/web.php`, `database/seeders/PermissionSeeder.php` (`Order Invoice`), `Pages/Order/{Index,Edit}.vue`,
  `resources/js/Components/ui/Button.vue` (`newTab`), `Lang/{ar,he}.json`
- Test: `tests/Feature/Items/OrderDocumentsTest.php`

**Interfaces:**
- Routes (`->can('Order Invoice')`): `orders.invoice` GET `/{order}/invoice?locale=ar|en|he`,
  `orders.waybill` GET `/{order}/waybill`, `orders.waybills` GET `/waybills?ids[]=` (1–100 ids).
  An unknown `locale` falls back to Arabic (the default locale).
- `OrderDocument::for(Order, string $locale): array` — store (name, logo file path, phone), number,
  date, customer, phone, address, city, district, lines (name in the locale with fallback, options,
  sku, quantity, unit price, total), subtotal, coupon code, discount, delivery fee, total, payment
  method and status, cash to collect (cash and unpaid: the total; else 0), item count, courier and
  tracking number (from the active shipment).
- `ItemsPdfService::invoice(Order, string $locale): Response` (A4, inline, `invoice-<number>.pdf`,
  title `Invoice <number>`); `waybills(Collection $orders): Response` (100 × 150 mm, a page an
  order, a C128 barcode of the number, `waybills-<first>.pdf` or `waybill-<number>.pdf`).

- [ ] Tests: `test_invoice_is_an_a4_pdf_with_the_order_number`, `test_invoice_speaks_the_requested_language`,
  `test_an_unknown_locale_falls_back_to_arabic`, `test_the_document_data`, `test_cash_to_collect_only_for_unpaid_cash_orders`,
  `test_waybill_is_100_by_150`, `test_bulk_waybills_print_a_page_an_order`, `test_bulk_waybills_need_1_to_100_ids`,
  `test_documents_need_the_invoice_permission`, `test_super_admin_has_the_invoice_permission`.

### Task 2: Shipments (domain)

**Files:**
- Create: `Modules/Items/database/migrations/2026_11_09_300001_create_items_shipments_tables.php`,
  `config/couriers.php`
- Create: `Modules/Items/Enums/ShipmentStatus.php`, `Entities/{Shipment,ShipmentEvent}.php`,
  `Repositories/{ShipmentRepository,Contracts/ShipmentRepositoryInterface}.php`, `Services/ShipmentService.php`,
  `Services/Couriers/{CourierGateway,ManualCourier,CourierRegistry}.php`
- Test: `tests/Feature/Items/ShipmentServiceTest.php`

**Interfaces:**
- `items_shipments`: id, order_id → orders cascade (indexed), courier string(40), tracking_number
  string(100) nullable, status string(30) indexed, cost decimal(10,2) default 0, notes text nullable,
  shipped_at, delivered_at nullable, created_by → users nullOnDelete (indexed), timestamps; partial
  unique index on order_id where status <> 'cancelled'.
  `items_shipment_events`: id, shipment_id → shipments cascade (indexed), status string(30), note
  text nullable, at timestamp, created_by → users nullOnDelete (indexed), timestamps.
- `ShipmentStatus`: pending → picked_up | in_transit | delivered | cancelled; picked_up → in_transit |
  delivered | returned_to_sender; in_transit → delivered | returned_to_sender. The others are final.
  `orderStatus(): ?OrderStatus` — picked_up, in_transit → OUT_FOR_DELIVERY; delivered → DELIVERED;
  returned_to_sender → RETURNED.
- `CourierGateway::createShipment(Shipment): ?string` (the courier's tracking number, or null to keep
  the typed one), `fetchStatus(Shipment): ?ShipmentStatus`. `CourierRegistry::options()`, `name(key)`,
  `gateway(key)` (unknown key or one with no gateway: `InvalidArgumentException`).
- `ShipmentService::create(Order, array{courier, tracking_number?, cost?, notes?}, int $userId): Shipment`
  — the order is confirmed, preparing, ready or with the courier, and has no active shipment (checked
  on the locked order). `move(Shipment, ShipmentStatus, ?string $note, int $userId): Shipment` — the
  order locked first, then the shipment; the shipment's own transition, then the order's (already
  there: nothing; not reachable: refused with the order's current and target status); dates set
  (`shipped_at` on picked up or in transit, `delivered_at` on delivered); one event per move.

- [ ] Tests: `test_a_shipment_is_created_with_a_pending_event`, `test_only_one_active_shipment_per_order`,
  `test_a_shipment_needs_an_order_on_its_way`, `test_an_unknown_courier_is_refused`,
  `test_picked_up_moves_the_order_to_with_courier`, `test_in_transit_after_picked_up_leaves_the_order_alone`,
  `test_delivered_delivers_the_order`, `test_returned_to_sender_returns_the_order_and_restocks_once`,
  `test_a_transition_the_order_cannot_make_is_refused_and_nothing_changes`, `test_shipment_transitions_follow_the_rules`,
  `test_a_cancelled_shipment_frees_the_order_for_a_new_one`, `test_the_manual_courier_keeps_the_typed_tracking_number`.

### Task 3: Shipments (admin and tracking)

**Files:**
- Create: `Modules/Items/Http/Controllers/Admin/ShipmentController.php`,
  `Http/Requests/Admin/{ShipmentRequest,ShipmentStatusRequest}.php`,
  `resources/js/Pages/Shipment/Index.vue`, `resources/js/Pages/Order/parts/ShipmentCard.vue`
- Modify: `routes/web.php`, `Providers/{RepositoryServiceProvider,SidebarServiceProvider}.php`,
  `PermissionSeeder.php`, `OrderController::edit` (`shipment`, `couriers`, `shipmentTransitions`),
  `Pages/Order/Edit.vue`, `navIcons.ts`, `Lang/ar.json`, `Core/Support/PermissionLabels.php`
- Modify: `Modules/Store/Services/OrderTracking.php` (`shipment`), `OrderRepository::findForTracking|findForCustomer`
  (eager `shipment`), `Pages/Storefront/TrackOrder.vue`, `Components/storefront/types.ts`, `Store/Lang/ar.json`
- Test: `tests/Feature/Items/ShipmentPagesTest.php`

**Interfaces:**
- Routes: `shipments.index` GET (`Shipment View List`), `shipments.store` POST (`Shipment Create`),
  `shipments.status` PUT `{shipment}` (`Shipment Update`). Sidebar "Shipments" after Return requests.
- Index filters: status, courier, created_at range, order number; search on tracking or order number.

- [ ] Tests: `test_the_list_is_shaped_for_the_table`, `test_each_filter`, `test_the_order_page_carries_the_shipment`,
  `test_create_and_move_from_the_order_page`, `test_a_refused_move_shows_the_message`,
  `test_tracking_shows_the_courier_and_tracking_number`, `test_every_action_needs_its_permission`,
  `test_super_admin_has_the_permissions_and_the_sidebar_item`.

### Task 4: Global activity log

**Files:**
- Create: `Modules/Core/Repositories/{ActivityLogRepository,Contracts/ActivityLogRepositoryInterface}.php`,
  `Support/ActivityLogRows.php`, `Http/Controllers/Admin/ActivityLogController.php`,
  `resources/js/Pages/ActivityLog/{Index,ActivityChanges}.vue`
- Modify: `Modules/Core/routes/web.php`, `Providers/{SidebarServiceProvider,CoreServiceProvider or RepositoryServiceProvider}.php`,
  `database/seeders/PermissionSeeder.php` (`Activity Log View List`), `Support/PermissionLabels.php`, `Lang/ar.json`
- Test: `tests/Feature/Core/ActivityLogPagesTest.php`

**Interfaces:**
- Route `activity-log.index` GET `/admin/activity-log` (`Activity Log View List`). Sidebar: Settings → "Activity log".
- Filters: subject_type (the logged models, labelled), causer_id (users who caused something), event
  (created, updated, deleted), created_at range. Newest first.
- Row: id, event + label, description, subject (label, id), causer name, created_at, changes
  `list<{key, old, new}>`; values of keys matching `/password|token|secret/i` become `••••••`.

- [ ] Tests: `test_the_list_shows_who_what_which_and_when`, `test_changes_show_old_and_new`,
  `test_secrets_are_masked`, `test_each_filter`, `test_subject_types_are_friendly`,
  `test_it_is_read_only_and_needs_its_permission`, `test_super_admin_has_the_permission_and_the_sidebar_item`.

### Task 5: Receive goods and adjust stock (domain)

**Files:**
- Modify: `Modules/Items/Services/InventoryService.php` (`receive`, `adjust`),
  `Repositories/{ProductRepository,Contracts/ProductRepositoryInterface}.php` (`isSellable`, `lowStockQuery`)
- Test: `tests/Feature/Items/InventoryReceiveAdjustTest.php`

**Interfaces:**
- `receive(Product, int $quantity, ?string $note, ?int $userId): void` — quantity > 0, a sellable
  product (a variant, or a product without variants); an INCOMING movement of +quantity; never sets a level.
- `adjust(Product, int $delta, string $reason, ?int $userId): void` — delta ≠ 0, a reason; an ADJUST
  movement with the reason as its note; refused (`delta`) when managed, without backorders and the
  stock would go below 0.
- `lowStockQuery()`: sellable products with managed stock at or below `low_stock_threshold`.

- [ ] Tests: `test_receive_adds_stock_as_incoming`, `test_receive_refuses_zero_or_negative`,
  `test_receive_refuses_a_product_with_variants`, `test_adjust_moves_by_the_delta_with_the_reason`,
  `test_adjust_refuses_going_below_zero_without_backorder`, `test_adjust_may_go_below_zero_with_backorder`,
  `test_adjust_needs_a_delta_and_a_reason`, `test_low_stock_query`.

### Task 6: Stock pages

**Files:**
- Create: `Modules/Items/Repositories/{StockMovementRepository,Contracts/StockMovementRepositoryInterface}.php`,
  `Http/Controllers/Admin/StockController.php`, `Http/Requests/Admin/{StockReceiveRequest,StockAdjustRequest}.php`,
  `resources/js/Pages/Stock/{Movements,LowStock,Receive,Adjust,StockProductField}.vue`
- Modify: `routes/web.php`, `RepositoryServiceProvider`, `SidebarServiceProvider` (group "Inventory"),
  `PermissionSeeder.php` (`Stock View List`, `Stock Update`), `navIcons.ts`, `Lang/ar.json`, `PermissionLabels.php`
- Test: `tests/Feature/Items/StockPagesTest.php`

**Interfaces:**
- Routes: `stock.movements` GET, `stock.low` GET (`Stock View List`); `stock.receive` GET, `stock.receive.store` POST,
  `stock.adjust` GET, `stock.adjust.store` POST, `stock.products` GET (`Stock Update`).
- Movements filters: product (name or SKU), type, created_at range, order (number). Rows link to the
  order's and the product's edit pages (a variant's is its parent's).
- Low stock filters: category (the product's or its parent's), out_of_stock (boolean). Sorted by stock.

- [ ] Tests: `test_movements_are_shaped_for_the_table`, `test_movement_filters`, `test_low_stock_lists_what_is_at_or_below_its_threshold`,
  `test_low_stock_filters`, `test_receive_goods`, `test_adjust_stock`, `test_an_adjustment_below_zero_shows_the_message`,
  `test_the_product_lookup_lists_sellable_products`, `test_every_action_needs_its_permission`,
  `test_super_admin_has_the_permissions_and_the_sidebar_group`.

### Task 7: Dashboard cards

**Files:**
- Modify: `Modules/Core/Services/DashboardService.php` (`alerts`), `Repositories/{DashboardRepository,Contracts/DashboardRepositoryInterface}.php`,
  `resources/js/Pages/Home/Index.vue`, `Pages/Home/parts/types.ts`; create `Pages/Home/parts/AlertCards.vue`
- Modify: `Modules/Items/Http/Controllers/Admin/ReturnRequestController.php` (filter `open`), `Pages/ReturnRequest/Index.vue`
- Test: `tests/Feature/Core/DashboardPageTest.php` (add), `tests/Feature/Items/ReturnRequestPagesTest.php` (the `open` filter)

**Interfaces:**
- `alerts`: `list<{key: 'awaiting_confirmation'|'low_stock'|'open_returns', title, value, url}>`, each
  only with its permission (`Order View List`, `Stock View List`, `Return Request View List`).
  Links: `orders.index?filter[awaiting_confirmation]=1`, `stock.low`, `return-requests.index?filter[open]=1`.

- [ ] Tests: `test_alerts_count_what_needs_attention_and_link_to_it`, `test_alerts_follow_permissions`,
  `test_the_open_filter`.

### Task 8: Whole suite, type check, dev database

- [ ] `php artisan test`; `npx vue-tsc --noEmit`.
- [ ] `php artisan migrate --force` on `malbo_app`; seed `Modules\Items\database\seeders\PermissionSeeder`
  and `Modules\Core\database\seeders\PermissionSeeder`.
