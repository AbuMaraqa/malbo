# MALBO Phase 6B: Pre-orders, reorder, PDF catalog, merchant finance

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Strict TDD: each named
> test is run and seen failing before the code that makes it pass.

**Goal:** finish the B2B channel (spec sections 16, 17, 22; phase 6):
- merchants reserve incoming goods (pre-orders) out of the same inventory, B2B only;
- a past wholesale order goes back into the cart in one click (reorder);
- an approved merchant makes a PDF of the catalog at their own prices, downloads it or shares a
  7-day revocable link on WhatsApp;
- merchant finance: a ledger (invoices, payments, credit notes, adjustments), credit limit and
  credit terms at checkout, statements in the portal and the admin.

**Architecture:** additive on top of 6A. Items owns stock and order lines, so the two stock columns
(`items_products.incoming_quantity`, `incoming_expected_at`) and `items_order_items.preorder_quantity`
are an Items migration, written only by `InventoryService` and `OrderService`. Everything else is
Wholesale (`wholesale_ledger_entries`, `wholesale_catalog_shares`, two merchant columns).

**Extension points in other modules (small, explicit):**
- `InventoryService::sellIncludingIncoming()` (B2B sale down to `-incoming`), `setIncoming()`,
  `reservableIncoming()`; `receive()` lowers `incoming_quantity`.
- `OrderService::checkout(..., B2B)` sells through `sellIncludingIncoming` and records
  `preorder_quantity` on the lines.
- `Modules\Items\Events\ReturnRequestCompleted`, dispatched by `ReturnRequestService::complete`
  inside its transaction, before the order may move to RETURNED.
- `PaymentMethod::ON_ACCOUNT = 4` (credit orders); the mobile API refuses it.

## Global Constraints

- `CLAUDE.md`: services write (one `DB::transaction` across tables), repositories read, thin
  controllers (`LayeringTest`), DataTable + `AdminFilter` filters, `->can()` on every admin route,
  Solar icons, logical utilities, dark mode in the admin, PostgreSQL rules, `Lang/ar.json`.
- `InventoryService` stays the only stock writer (incoming included); `OrderService` the only order
  writer. `allow_backorder` is never used for pre-orders. B2C never sells incoming goods.
- Wholesale prices leave the server only to approved merchants and staff. The PDF and its shared
  link hold only the merchant's own tier prices: never another tier's, never a cost price.
- The ledger is append-only; invoices are idempotent per order, credit notes per return request;
  credits on an order never exceed its invoice.
- Credit is checked on the server under a lock on the merchant row.
- Additive migrations only. Tests on `malbo_phase6b_testing`. No commits.

## Review Focus

1. **Overselling through pre-orders.** Floor `-reservableIncoming` applies only in
   `sellIncludingIncoming` (B2B checkout), under the same FOR NO KEY UPDATE lock. Pinned in Task 1
   (`test_b2c_never_sells_incoming_goods`, `test_a_b2b_sale_stops_at_minus_incoming`).
2. **Double posting.** Partial unique indexes + checks inside the order's transaction. Pinned in
   Task 4 (`test_an_invoice_is_posted_once_per_order`, `test_a_returned_order_is_credited_only_what_is_left`).
3. **Credit limit races.** Merchant row locked inside the checkout transaction. Pinned in Task 5.
4. **Shared link leaks.** Signed + expiring + revocable + merchant still approved. Pinned in Task 3.

---

### Task 1: Incoming goods and pre-orders

**Files:**
- Create: `Modules/Items/database/migrations/2026_11_10_200001_add_incoming_goods_and_preorders.php`
- Modify: `Items/Services/InventoryService.php`, `Items/Services/OrderService.php` (B2B checkout),
  `Items/Entities/{Product,OrderItem}.php`, `Items/Exceptions/InsufficientStock.php` (if needed)
- Modify: `Wholesale/Support/WholesaleItems.php` (limit with incoming, `incoming()`), `Services/WholesaleCatalog.php`
  (cells: `in_stock`, `incoming`), `Services/WholesaleCartService.php` (line `preorder`), `Services/WholesalePriceService.php`
  (`saveIncoming`), `Http/Controllers/Admin/ProductPriceController.php` (`incoming`), `Http/Requests/Admin/IncomingRequest.php`
- Create: `Repositories/PreorderRepository.php` (+ contract), `Http/Controllers/Admin/PreorderController.php`,
  `resources/js/Pages/Preorder/Index.vue`, `resources/js/Pages/ProductPrice/parts/IncomingCard.vue`
- Tests: `tests/Feature/Wholesale/PreorderTest.php`, `tests/Feature/Wholesale/PreorderPagesTest.php`

**Interfaces:**
- `InventoryService::reservableIncoming(Product): int` — `incoming_quantity` when > 0 and dated, else 0.
- `InventoryService::sellIncludingIncoming(array $quantities, ?Order, ?int): array<int,int>` — pieces
  per product taken beyond the stock (pre-ordered); throws `InsufficientStock` below `-incoming`.
- `InventoryService::setIncoming(Product, int, ?string $date, ?int)` — refused below what is pre-ordered.
- `receive()` lowers `incoming_quantity` by the received pieces, floored at 0 (date cleared at 0).
- Routes: `wholesale-prices.incoming` (PUT, Wholesale Price Update), `wholesale-preorders.index`
  (GET, `Wholesale Preorder View List`; filters search, expected date range, received).

- [x] `test_b2c_never_sells_incoming_goods`, `test_the_storefront_shows_incoming_only_as_out_of_stock`,
  `test_the_grid_shows_incoming_on_a_date`, `test_a_merchant_can_order_up_to_stock_plus_incoming`,
  `test_undated_incoming_is_not_offered`, `test_checkout_records_the_preorder_part_of_each_line`,
  `test_a_b2b_sale_stops_at_minus_incoming`, `test_receiving_lowers_incoming_floored_at_zero`,
  `test_cancelling_a_preorder_restocks`, `test_incoming_cannot_drop_below_what_is_preordered`.
- [x] Pages: `test_the_incoming_card_saves_quantities_and_dates`, `test_incoming_of_another_product_is_refused`,
  `test_the_preorders_list_groups_lines_by_product`, `test_closed_orders_are_left_out`, `test_each_filter`, permissions.
- [x] `tests/Feature/Wholesale/PortalGuestRoutesTest.php`: the 6B portal routes for a real guest (no `URL::defaults`).

### Task 2: Reorder

**Files:** Create `Services/ReorderService.php`; modify `Http/Controllers/Storefront/OrderController.php` (`reorder`),
`CartController::show` (`reorder` flash), `MerchantOrders::detail` (`reorder_url`), `Pages/Portal/{OrderDetail,Cart}.vue`.
Test: `tests/Feature/Wholesale/ReorderTest.php`.

**Interfaces:** `POST /{locale}/wholesale/orders/{number}/reorder` (`store.wholesale.orders.reorder`).
`ReorderService::reorder(Merchant, Order): array{added: int, skipped: list<string>}` — groups lines by
product, skips lines not on sale/not sold wholesale/unavailable, caps at the limit, drops a color whose
run is broken, merges with the cart, writes through `WholesaleCartService::set`.

- [x] `test_reorder_copies_lines_at_current_prices`, `test_existing_cart_lines_merge_within_the_limit`,
  `test_unavailable_and_not_wholesale_lines_are_skipped_with_a_message`, `test_a_broken_size_run_is_skipped`,
  `test_another_merchants_order_cannot_be_reordered`, `test_the_cart_shows_what_was_skipped`.

### Task 3: PDF catalog and share links

**Files:** migration `2026_11_10_200002_create_wholesale_catalog_shares_table.php`; `Entities/CatalogShare.php`;
`Repositories/CatalogShareRepository.php` (+ contract); `WholesaleProductRepository::forCatalogPdf`;
`Services/{CatalogPdfService,CatalogShareService}.php`; `resources/views/pdf/catalog.blade.php`;
`Http/Controllers/Storefront/CatalogPdfController.php`; `Pages/Portal/CatalogPdf.vue`.
Test: `tests/Feature/Wholesale/CatalogPdfTest.php`.

**Interfaces:**
- `GET /wholesale/catalog-pdf` (`store.wholesale.catalog-pdf`), `GET …/download` (`.download`,
  `throttle:wholesale-catalog`), `POST …/shares` (`.shares.store`, throttled), `DELETE …/shares/{share}`
  (`.shares.destroy`); public `GET /{locale}/wholesale/shared-catalog/{token}` (`store.wholesale.catalog-pdf.shared`,
  `signed`, `throttle:wholesale-catalog-shared`).
- `CatalogPdfService::document(Merchant, ?string $category, string $locale): array` (max 200 products) and `render(array): string`.

- [x] `test_the_document_holds_the_merchants_tier_prices_breaks_and_retail_only`, `test_the_category_filter`,
  `test_it_is_capped_at_200_products`, `test_download_returns_a_pdf`, `test_generation_is_rate_limited`,
  `test_a_share_link_opens_without_login_for_7_days`, `test_a_revoked_or_expired_or_tampered_link_is_refused`,
  `test_a_suspended_merchants_link_stops_working`, `test_only_the_owner_revokes`, `test_the_page_lists_shares_with_a_whatsapp_link`.

### Task 4: Ledger, invoices and credit notes

**Files:** migration `2026_11_10_200003_create_wholesale_ledger_tables.php` (entries, merchant credit
columns); `Enums/{LedgerEntryType,LedgerPaymentMethod}.php`; `Entities/LedgerEntry.php`;
`Repositories/LedgerRepository.php` (+ contract); `Services/MerchantLedger.php`;
`Listeners/{PostB2BOrderEntries,PostReturnCredit}.php`; `Items/Events/ReturnRequestCompleted.php`;
modify `Items/Services/ReturnRequestService.php`. Test: `tests/Feature/Wholesale/MerchantLedgerTest.php`.

- [x] `test_delivering_a_b2b_order_posts_its_invoice`, `test_an_invoice_is_posted_once_per_order`,
  `test_b2c_orders_post_nothing`, `test_a_completed_return_posts_a_credit_note`,
  `test_a_returned_order_is_credited_only_what_is_left`, `test_a_full_return_request_credits_once`,
  `test_payments_and_adjustments`, `test_the_balance_and_running_balance`.

### Task 5: Credit limit and credit terms

**Files:** `Services/MerchantCredit.php`; modify `Enums/PaymentTerms.php` (`offeredTo`), `Items/Enums/PaymentMethod.php`
(`ON_ACCOUNT`), `Items/Support/OrderDocument.php`, `Items/Http/Requests/OrderRequest.php`,
`WholesaleCheckoutService`, `WholesaleCheckoutRequest`, `CheckoutController::show`, `Pages/Portal/Checkout.vue`,
`MerchantService::update`, `MerchantRequest`, `Merchant/parts/MerchantProfileFields.vue`.
Test: `tests/Feature/Wholesale/CreditTermsTest.php`.

- [x] `test_credit_is_not_offered_without_a_limit`, `test_credit_within_the_limit_places_an_on_account_order`,
  `test_credit_counts_balance_and_open_orders`, `test_credit_over_the_limit_says_how_much_room_is_left`,
  `test_cancelled_and_delivered_orders_do_not_count_as_open`, `test_staff_edit_credit_settings`, `test_the_api_refuses_on_account`.

### Task 6: Admin finance page and statements

**Files:** `Http/Controllers/Admin/MerchantLedgerController.php`, `Http/Requests/Admin/{LedgerPaymentRequest,LedgerAdjustmentRequest}.php`,
`Services/MerchantStatement.php`, `resources/views/pdf/statement.blade.php`, `Pages/Merchant/Ledger.vue` (+ parts),
`Http/Controllers/Storefront/StatementController.php`, `Pages/Portal/Statement.vue`; `PermissionSeeder` (`Merchant Payment Create`,
`Wholesale Preorder View List`); sidebar; `PortalNav`. Test: `tests/Feature/Wholesale/MerchantFinancePagesTest.php`.

**Interfaces:** admin `merchants.ledger` (View List; filters type, method, entry_date range), `merchants.payments.store`,
`merchants.adjustments.store` (Merchant Payment Create), `merchants.receipt` (View List, signed), `merchants.statement`
(View List, PDF). Portal `GET /wholesale/account/statement` (`store.wholesale.statement`, `from`, `to`), `GET …/statement/pdf`
(`store.wholesale.statement.pdf`).

- [x] `test_the_ledger_page_shows_running_balance_and_totals`, `test_each_filter`, `test_recording_a_payment_with_a_private_receipt`,
  `test_an_adjustment_needs_a_note`, `test_the_receipt_needs_a_signed_link`, `test_the_admin_statement_pdf`,
  `test_the_portal_statement_with_opening_balance_and_range`, `test_the_portal_statement_pdf`, `test_a_merchant_sees_only_their_own_statement`,
  `test_every_action_needs_its_permission`.

### Task 7: Whole suite, type check, dev database

- [x] `DB_DATABASE=malbo_phase6b_testing php artisan test`; `npx vue-tsc --noEmit`.
- [x] `php artisan migrate --force` on `malbo_app`; `php artisan db:seed --class="Modules\Wholesale\database\seeders\PermissionSeeder" --force`.

---

## Review fix pass (2026-11-11)

- [x] 1 Ledger survives deletes: `wholesale_merchants.user_id` and `wholesale_ledger_entries.merchant_id` NO ACTION (`MerchantDeletionTest`).
- [x] 2 Incoming card sends changed rows with what it loaded; a changed row is refused; Adjust page and product form hint (`PreorderPagesTest`).
- [x] 3 Credits never include the delivery fee; 4 total changes after delivery post a difference (`order_total`); 5 cash on delivery paid posts a payment (`cash_collected`, reversals), unpaid invoices flagged (`MerchantLedgerTest`, `MerchantFinancePagesTest`). Items `OrderUpdated` event.
- [x] 6 Catalog prices `retail` (default) or `wholesale`; 7 thumbnails, image budget, 10-minute PDF cache, per-link limit (`CatalogPdfTest`).
- [x] 8 `Merchant Credit Update`; 12 admin "on account" through Items `OnAccountPolicy` (`CreditTermsTest`).
- [x] 9 Modal hints; 10 expected dates today or later, a date gone by is not reservable; 11 entries not in the future; 13 statement behind `merchant.profile`, throttled, 2-year cap; 14 `preorder_quantity` follows lowered lines; 15 value-based store page check.
