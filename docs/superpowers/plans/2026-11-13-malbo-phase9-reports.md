# MALBO Phase 9: Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Strict TDD: each named
> test is run and seen failing before the code that makes it pass.

**Goal:** spec area 20: eight read-only admin reports (sales by day/month, B2C vs B2B, sales by
product/category/colour/size/bundle, best and slow sellers, return rate, merchant performance,
abandoned carts, conversion and AOV), each with shared filters, a chart, a paginated table and a
streamed CSV export.

**Architecture:** a new nwidart module `Reports` that depends on Core, Items, Store and Wholesale;
nothing depends on it.
- All numbers are aggregated in PostgreSQL by three repositories (`SalesReportRepository`,
  `MerchantReportRepository`, `CartReportRepository`); money stays `numeric` until it is rounded to
  2 places in SQL and leaves PHP as a string (`ReportMoney`), never through float arithmetic.
- One SQL vocabulary for "a sale" lives in `Reports\Support\SaleSql` (statuses, the per-line discount
  share, returned/exchanged units, returns value). Every report builds on it, so the reports agree
  with each other.
- Services shape repository rows into page props (KPIs, chart series with empty buckets filled,
  table rows). Controllers are thin: parse filters, call the service, render or stream.
- Tables: the grouped SQL is wrapped as `ReportRow::query()->fromSub($sql, 'report')` and handed to
  the existing `AdminTable` (pagination, whitelisted sorts, `filter[...]` echo). Report-wide filters
  are applied inside the subquery and registered on the table as allowed no-ops
  (`ReportFilters::allowed()`); table-only filters (search, number ranges on aggregates) are real
  `AdminFilter`s on the outer query.

**Tech Stack:** Laravel 13, nwidart modules, PostgreSQL 18, Inertia 3 + Vue 3 + TypeScript,
ApexCharts (`Components/dashboard/ApexChart.vue`, async), PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-08-malbo-platform.md` (area 20; statuses area 19;
channels; phase 9). Earlier plans: phase 1 (order record, statuses), 3 (checkout totals), 4
(returns), 6A/6B (merchants, tiers, ledger), 8A (carts, reminders), 8B (bundle lines).

## Global Constraints

- `CLAUDE.md`: thin controllers (`LayeringTest`), reads through named repository methods, `->can()`
  on every admin route, permissions in the module `PermissionSeeder`, sidebar `->section(...)`,
  Solar icons via `resources/js/lib/icons.ts` and `navIcons.ts`, every `t()` text in `Lang/ar.json`,
  logical utilities only, dark-mode tokens, DataTable with non-empty `filters`, `jsonb`,
  `whereLike(..., caseSensitive: false)`, booleans as `true/false`, heavy libs lazy.
- **Read-only:** no report writes to any table. No change to Items/Store/Wholesale behaviour.
- Additive migration only (indexes). Tests on `DB_DATABASE=malbo_phase9_testing` (port 5433 from
  `.env`). Single test files while working; the whole suite once at the end. No commits.
- Vitest is run from `C:\laragon\www\malbo` (capital C).

## Decisions (cost if wrong)

1. **A sale** = an order not soft-deleted whose status is CONFIRMED, PREPARING, READY_FOR_PICKUP,
   OUT_FOR_DELIVERY, DELIVERED, RETURNED or EXCHANGED (`SaleSql::STATUSES`). Excluded: PENDING and
   AWAITING_CONFIRMATION (not confirmed by MALBO yet; every path to delivery passes CONFIRMED),
   CANCELLED, REJECTED. RETURNED/EXCHANGED stay sales; what came back is subtracted as returns.
   *Cost:* a shop that skips confirmation would see nothing until it confirms. The sales page shows
   "awaiting confirmation" orders and value next to the KPIs so they are never invisible.
2. **Date basis** = the order's `created_at` (placement), in the reports timezone. Returns are
   counted against the date of the order they came from (cohort), so a period's return rate is
   "of what sold in the period, how much came back" and never exceeds 100 %. *Cost:* an old
   period's net sales go down when a late return completes; the page says so.
3. **Timezone**: `config('reports.timezone')` = env `REPORTS_TIMEZONE`, default `config('app.timezone')`
   (UTC today). Timestamps are stored naive in the app timezone; SQL converts with
   `(col AT TIME ZONE app) AT TIME ZONE reports` for buckets; range bounds are computed in PHP
   (local midnight → app timezone) so the WHERE stays index-friendly. *Cost:* with UTC, orders between
   00:00 and 02:00/03:00 Palestine time land on the previous day; deploy note: set
   `REPORTS_TIMEZONE=Asia/Hebron`.
4. **Money**: one currency, ILS (₪): orders carry no currency column and the store prices in ₪
   (`Store\Support\Price`). Definitions (Shopify's):
   - gross = Σ line `total_price` (= order `sub_total`);
   - discounts = Σ `LEAST(discount, sub_total)` (order-level: coupons and staff discounts; sale
     prices are already in the line price);
   - a line's discount share = `total_price × LEAST(discount, sub_total) / sub_total` (the formula
     `ReturnRequestService::unitDiscount` uses for refunds);
   - returned units per line = all of it when the order is RETURNED, else Σ completed RETURN-type
     request lines (capped at the line quantity); exchanged units = Σ completed EXCHANGE-type request
     lines (capped at what is left);
   - returns value = returned units × (total_price − discount share) / quantity: the line's allocated
     price after its discount share (a bundle piece's `total_price` is its allocated price);
   - net sales = gross − discounts − returns; delivery fees are reported apart; total = net + delivery;
   - AOV = (gross − discounts) ÷ orders (at order time; returns not deducted).
   *Cost:* the returns value is computed, not read from `refund_amount` (which is capped per order and
   rounded per request): totals can differ from recorded refunds by a cent; reports agree with each
   other instead.
5. **Exchanges** do not change sales value or the product/size a line was sold as; they are counted
   as exchanged units on the returns report (rate and reasons). *Cost:* size reports keep the size
   first sold, not the size the buyer kept.
6. **Colour and size** come from the line's `options` snapshot: an option counts as colour when its
   snapshot name equals (in that locale) a translation name of an attribute of type COLOR, as size
   when it matches the attribute with `is_size`. Values are labelled in the viewer's locale, falling
   back to ar, then en. Lines without such an option form one "Not set" row. *Cost:* an attribute
   renamed after the sale no longer matches; its old lines fall into "Not set".
7. **Bundles**: pieces count under their own product, category, colour and size (8B decision 8). The
   "Sets and bundles" grouping adds one row per bundle (`bundle_product_id`): bundles sold = Σ per
   `bundle_group` of `bundle->>'quantity'`, value = Σ of its pieces' lines.
8. **Categories**: a line belongs to the categories of its parent product (a variant's parent, else
   itself); a product in several categories counts in each (the table says so); the category filter
   includes subcategories (recursive CTE).
9. **Branch filter**: `orders.branch_id`; storefront and wholesale orders have none, so it is offered
   only when branches exist and it narrows to orders of that branch.
10. **Slow sellers**: parent products (not bundles, active, not deleted) with managed stock on hand
    > 0 (Σ of their sellable items' positive `stock_quantity`), ordered by units sold in the period
    ascending then stock descending; "last sold" is all-time, read for the page's rows only.
    Unmanaged-stock products are left out (no stock to report).
11. **Abandoned carts** (B2C only: reminders exist only for store carts): carts *started* in the
    period (`created_at`); converted = `order_id` or `converted_at` set; abandoned = not converted,
    has items, quiet for `abandoned_after_minutes`; reachable = abandoned with `contact_consent_at`;
    value at today's prices in SQL (a mirror of `PricingService`, pinned by a test that compares the
    two), all lines counted (the 8A list skips lines that cannot be bought now; the page says
    "all lines at today's prices"); reminded = ≥1 reminder `sent`; recovered = converted after a
    sent reminder and the order not cancelled/rejected; recovered value = those orders' `total`;
    recovery rate = recovered ÷ reminded; reminders sent are counted by `sent_at` in the period,
    split by channel.
12. **Conversion** = carts that became an order ÷ carts started, per channel (store carts for B2C,
    `wholesale_carts` for B2B), by cart `created_at`. No visits or sessions are tracked, so the page
    says it is cart-to-order conversion, not visit-to-order. *Cost:* none invented; a later
    analytics source can add a visit rate beside it.
13. **Filters** live in `filter[...]` (so DataTable paging keeps them): `preset`
    (`today|7d|this_month|last_month|custom`, default `this_month`), `date_from`, `date_to`
    (explicit dates win over a preset), `channel` (`b2c|b2b`), `branch`, `category`, `interval`
    (`day|month`; default day up to 62 days, else month; day is refused beyond 366 days), `by`
    (grouping). Invalid values fall back to the defaults rather than erroring (GET pages); the range
    is capped at 5 years.
14. **Permissions** (module `Reports`, one per report, export included): `Sales Report View`,
    `Channel Report View`, `Product Report View`, `Seller Report View`, `Return Report View`,
    `Merchant Report View`, `Cart Report View`, `Conversion Report View`. Sidebar: group "Reports"
    (icon key `reports`, Solar `chart-2-bold-duotone`), section `Sales`, sort 5 (after Wholesale).
15. **CSV**: `text/csv; charset=UTF-8`, UTF-8 BOM, comma separated, header row in the viewer's
    language, plain numbers with a dot (no currency sign), all rows of the table (not one page),
    streamed in chunks of 1000, file name `<report>-<from>-<to>.csv`.
16. **Dashboard KPIs**: not added (Core must not depend on Reports; the sales page is one click away).

## Review Focus

1. **Double counting through joins**: an order with several lines, a line in several categories, or
   several return requests must not multiply order-level money. Pinned in Task 2
   (`test_order_money_is_counted_once_whatever_the_lines`) and Task 4 (`test_a_product_in_two_categories_counts_in_each_but_once_in_totals`).
2. **Status rule**: awaiting/cancelled/rejected/deleted orders never count; returned orders net to 0.
   Pinned in Task 2 (`test_only_confirmed_orders_count`, `test_a_returned_order_nets_to_zero`).
3. **Timezone/day edges**: an order at 23:30 local is that day, the range end day is included.
   Pinned in Task 1 (`ReportPeriodTest`) and Task 2 (`test_days_follow_the_reports_timezone`).
4. **Exact money**: 0.10 + 0.20 style sums and a 3-way discount split stay exact to the cent.
   Pinned in Task 2 (`test_money_is_exact`).
5. **CSV is Excel-safe Arabic**: BOM, UTF-8, header in Arabic, all rows, same filters. Pinned in
   Task 2 (`test_the_csv_export`) and one export test per report.

---

### Task 1: Module, filters, period, permissions, sidebar

**Files:**
- Create: `Modules/Reports/{module.json,composer.json,config/config.php,Lang/ar.json,Lang/he.json}`,
  `Providers/{ReportsServiceProvider,RouteServiceProvider,RepositoryServiceProvider,SidebarServiceProvider}.php`,
  `routes/web.php`, `database/seeders/{ReportsDatabaseSeeder,PermissionSeeder}.php`,
  `database/migrations/2026_11_13_000001_add_report_indexes.php`
- Create: `Support/{ReportPeriod,ReportFilters,ReportMoney,ReportRow,CsvExport,SaleSql}.php`
- Modify: `modules_statuses.json`, `resources/js/lib/pages.ts` (MODULES), `tests/Unit/Architecture/InertiaPagesExistTest.php`
  (MODULES), `resources/js/Components/layout/navIcons.ts` (`reports`), `Modules/Core/Support/PermissionLabels.php` (AR_MODULES)
- Test: `tests/Unit/Reports/ReportPeriodTest.php`, `tests/Unit/Reports/ReportMoneyTest.php`, `tests/Feature/Reports/ReportsModuleTest.php`

**Interfaces (produced):**
- `ReportPeriod::resolve(?string $preset, ?string $from, ?string $to, CarbonImmutable $today): self`;
  props `from`, `to` (CarbonImmutable dates in the reports tz), `preset` (the preset these dates
  match, else `custom`); `bounds(): array{0: string, 1: string}` start inclusive / end exclusive as
  `Y-m-d H:i:s` in the app timezone; `previous(): self` (same length, just before);
  `days(): int`; `buckets(string $interval): list<string>` (`Y-m-d` or `Y-m` keys covering the range).
- `ReportFilters::fromRequest(Request $request): self` with `period`, `channel` (?OrderChannel),
  `branchId`, `categoryId`, `interval`, `by` (string, validated against the caller's list via
  `by(list<string> $allowed, string $default)`); `query(): array` (the `filter[...]` values to echo
  and to build the export URL); `static allowed(): list<AllowedFilter>` (no-op callbacks for
  `preset,date_from,date_to,channel,branch,category,interval,by`); `toProps(): array` for the filter bar.
- `ReportMoney::format(string|int|float|null): string` (`"1,234.50 ₪"`), `::plain(...)` (`"1234.50"`).
- `ReportRow` (Eloquent, table `report`, no timestamps): only for `fromSub` tables.
- `CsvExport::stream(string $filename, list<string> $headers, iterable $rows): StreamedResponse`.
- `SaleSql`: `STATUSES` (ints), `returnsByItem(): Query\Builder` (order_item_id, returned, exchanged
  of completed requests), `lines(ReportFilters $f, bool $withCategory = true): Query\Builder` (one row
  per sale line: order_id, created_at, channel, user_id, product_id, item_id (parent or self),
  bundle_product_id, bundle_group, bundle_qty, quantity, gross, discount, returned_units,
  exchanged_units, returns, options, product_name), `orders(ReportFilters $f): Query\Builder` (one row
  per sale order: id, created_at, channel, user_id, total, gross, discount, delivery, units, returns),
  `bucket(string $column, string $interval): string` (SQL expression, tz-converted).
- Routes (under `auth, verified`, `{locale}`, `admin/reports`, `staff`): `reports.<name>.index` GET
  and `reports.<name>.export` GET for name in `sales, channels, products, sellers, returns, merchants,
  carts, conversion`, each `->can('<X> Report View')` (Decision 14).
- Indexes: `items_orders (created_at)`, `store_carts (created_at)`, `wholesale_carts (created_at)`,
  `store_cart_reminders (sent_at)`, `items_return_requests (status, type)`.

- [ ] **Step 1: tests.** `ReportPeriodTest`: `test_presets` (today, 7d = today and the 6 days
  before, this_month, last_month across a year boundary from 2026-01-15), `test_explicit_dates_win_and_match_a_preset`,
  `test_invalid_or_reversed_dates_fall_back_to_this_month`, `test_bounds_are_local_midnights_in_the_app_timezone`
  (reports tz `Asia/Hebron`, 2026-11-13 → `2026-11-12 22:00:00` … `2026-11-13 22:00:00`), `test_previous_period`,
  `test_buckets_fill_the_range`. `ReportMoneyTest`: `test_formats_numeric_strings_without_floats`
  (`"1234567.5"` → `1,234,567.50 ₪`, `"-0.10"` → `-0.10 ₪`, null → `0.00 ₪`).
  `ReportsModuleTest`: `test_permissions_are_seeded_and_given_to_super_admin`, `test_the_sidebar_group`,
  `test_every_report_route_needs_its_permission` (8 index + 8 export routes 403 without, a staff with
  only `Sales Report View` gets 200 on sales and 403 on the rest), `test_the_indexes_exist`.
- [ ] **Step 2:** run, FAIL. **Step 3:** implement. **Step 4:** PASS.

### Task 2: Sales report (daily/monthly) — the sale rules

**Files:** `Repositories/{SalesReportRepository,Contracts/SalesReportRepositoryInterface}.php`,
`Services/SalesReport.php`, `Http/Controllers/Admin/SalesReportController.php`,
`resources/js/Pages/Sales/Index.vue`, shared `resources/js/Components/{ReportFilterBar,ReportKpis,ReportExportButton}.vue`,
`resources/js/Components/reportQuery.ts`, `resources/js/Components/types.ts`.
Tests: `tests/Feature/Reports/SalesReportTest.php` (+ `tests/Feature/Reports/Concerns/MakesReportData.php`:
`sale(array $order, list<array{product?: Product, quantity, price, options?, bundle?}>)` writes an
order and its lines directly, any status/channel/created_at; `returnRequest(Order, type, reason, lines)`
writes a completed request), Vitest `Modules/Reports/resources/js/Components/__tests__/ReportFilterBar.spec.ts`.

**Interfaces:**
- Repository: `summary(ReportFilters): array{orders, units, gross, discounts, delivery, returns, net, total}`
  (strings for money), `pending(ReportFilters): array{orders, value}` (PENDING + AWAITING in range),
  `timeline(ReportFilters, bool $byChannel = false): Collection` (bucket[, channel], same keys),
  `timelineTable(ReportFilters): Query\Builder` (bucket rows for `fromSub`).
- `SalesReport::page(ReportFilters): array{summary, previous, pending, chart, filters}`;
  `SalesReport::table(ReportFilters): array` (AdminTable payload, sorts `bucket, orders, units, gross, net`,
  default `-bucket`); `SalesReport::export(ReportFilters): StreamedResponse`.
- Page props: `filters` (ReportFilters::toProps + `options.branches`), `kpis`, `chart`
  (`{categories, net, orders}`), `table`, `pending`, `export_url`.
- `ReportFilterBar.vue` props `{filters: ReportFilterProps, channels?: boolean, branches?: Option[], categories?: Option[], groupings?: Option[], intervals?: boolean}`;
  it navigates with `router.get(location.pathname, {filter: next}, {preserveScroll: true})` where
  `next = applyFilters(current, changes)` from `reportQuery.ts` (`null` removes a key; choosing a
  preset removes `date_from`/`date_to`; a custom range sets both and `preset=custom`).

- [ ] Tests: `test_only_confirmed_orders_count`, `test_order_money_is_counted_once_whatever_the_lines`,
  `test_net_subtracts_discounts_and_returns_at_the_discounted_line_price`, `test_a_returned_order_nets_to_zero`,
  `test_exchanges_do_not_reduce_sales`, `test_money_is_exact` (lines 0.10 + 0.20 … and a 10.00
  discount over three lines), `test_days_follow_the_reports_timezone`, `test_the_channel_and_branch_filters`,
  `test_the_page_props_and_table` (component `Reports/Sales/Index`, KPIs formatted, chart buckets filled with zeros,
  table sorted by bucket desc, previous-period comparison), `test_monthly_interval`,
  `test_the_csv_export` (BOM, Arabic header, every bucket, same filters).
- [ ] Vitest: `applies a preset and drops explicit dates`, `applies a custom range`, `keeps other filters when the channel changes`,
  `marks the active preset`.

### Task 3: B2C vs B2B

**Files:** `Services/ChannelReport.php`, `Http/Controllers/Admin/ChannelReportController.php`,
`resources/js/Pages/Channels/Index.vue`. Repository: `summaryByChannel(ReportFilters): array<'b2c'|'b2b', summary>`,
`channelTable(ReportFilters): Query\Builder` (bucket, b2c_orders, b2c_net, b2b_orders, b2b_net).
Props: `channels` (per channel: summary, aov, share of net and orders in %, change vs previous
period), `chart` (`{categories, b2c, b2b}`), `table`, `export_url`.

- [ ] `tests/Feature/Reports/ChannelReportTest.php`: `test_metrics_side_by_side_with_shares`, `test_change_against_the_previous_period`,
  `test_the_table_and_chart_per_bucket`, `test_the_csv_export`.

### Task 4: Sales by product, category, colour, size, bundle

**Files:** `Services/ProductReport.php`, `Http/Controllers/Admin/ProductReportController.php`,
`resources/js/Pages/Products/Index.vue`. Repository: `productTable(ReportFilters, string $by, string $locale): Query\Builder`
(columns `key, name, sku?, orders, units, gross, discounts, returns, net, returned_units`; `by` ∈
`product|category|color|size|bundle`, bundle adds `bundles`), `categoryIds(int $root): list<int>` (subtree).
Props: `table`, `chart` (top 10 by net of the same grouping), `groupings`, `filters`, `export_url`.
Table filters: shared + `search` (name), `units` number range.

- [ ] `tests/Feature/Reports/ProductReportTest.php`: `test_variants_roll_up_into_their_product`, `test_bundle_pieces_count_under_their_own_product_colour_and_size`,
  `test_bundles_have_their_own_rows`, `test_colour_and_size_come_from_the_snapshot`, `test_lines_without_the_option_are_not_set`,
  `test_a_product_in_two_categories_counts_in_each_but_once_in_totals`, `test_the_category_filter_includes_subcategories`,
  `test_search_sort_and_units_range`, `test_the_csv_export`.

### Task 5: Best and slow sellers

**Files:** `Services/SellerReport.php`, `Http/Controllers/Admin/SellerReportController.php`,
`resources/js/Pages/Sellers/Index.vue`. Repository: `slowSellersTable(ReportFilters, string $locale): Query\Builder`
(`key, name, sku, stock, items_in_stock, units, net`), `lastSold(list<int> $productIds): array<int, string>`.
Best sellers = `productTable(..., 'product')` top 10 by units. Table default sort `units` then `-stock`;
filters shared (channel, category, date) + `search`, `units` range.

- [ ] `tests/Feature/Reports/SellerReportTest.php`: `test_best_sellers_by_units`, `test_slow_sellers_are_in_stock_items_with_few_sales`,
  `test_stock_on_hand_sums_the_variants`, `test_bundles_unmanaged_inactive_and_out_of_stock_are_left_out`,
  `test_last_sold_is_all_time`, `test_the_csv_export`.

### Task 6: Return rate

**Files:** `Services/ReturnReport.php`, `Http/Controllers/Admin/ReturnReportController.php`,
`resources/js/Pages/Returns/Index.vue`. Repository: `returnSummary(ReportFilters): array{sold, returned, exchanged, returns, requests}`,
`reasons(ReportFilters): Collection<{reason, type, units}>` (+ the reason `order_returned` for RETURNED
orders' units no request covers), `returnTable(ReportFilters, string $by, string $locale): Query\Builder`
(`key, name, sold, returned, exchanged, returns, rate`); `by` ∈ `product|category`.
Props: `kpis` (rates in %, one decimal), `reasons` (donut), `table`, `export_url`. Filter `sold` number range.

- [ ] `tests/Feature/Reports/ReturnReportTest.php`: `test_rate_is_returned_over_sold_units`, `test_exchanges_are_their_own_rate`,
  `test_reasons_include_whole_order_returns`, `test_rejected_and_open_requests_do_not_count`, `test_by_category`, `test_the_csv_export`.

### Task 7: Merchant performance

**Files:** `Repositories/{MerchantReportRepository,Contracts/...}.php`, `Services/MerchantReport.php`,
`Http/Controllers/Admin/MerchantReportController.php`, `resources/js/Pages/Merchants/Index.vue`.
Repository: `table(ReportFilters, string $locale): Query\Builder` (`id, shop_name, status, tier, sales_rep,
orders, units, net, aov, last_order_at, balance, credit_limit`; B2B sale orders by `orders.user_id = merchants.user_id`
in the period; last order all-time; balance = Σ ledger `amount`), `totals(ReportFilters): array{merchants, active, orders, net, balance}`.
Filters: date + `search` (shop name), `status`, `tier`, `sales_rep`. Chart: top 10 by net.

- [ ] `tests/Feature/Reports/MerchantReportTest.php`: `test_orders_revenue_aov_last_order_and_tier`, `test_balance_comes_from_the_ledger`,
  `test_only_b2b_sales_of_that_merchant_count`, `test_filters`, `test_the_csv_export`.

### Task 8: Abandoned carts

**Files:** `Repositories/{CartReportRepository,Contracts/...}.php`, `Services/CartReport.php`,
`Http/Controllers/Admin/CartReportController.php`, `resources/js/Pages/Carts/Index.vue`.
Repository: `summary(ReportFilters, int $abandonedAfterMinutes, CarbonInterface $now): array{started, converted, abandoned, reachable, value, reminded, recovered, recovered_value}`,
`reminders(ReportFilters): Collection<{channel, sequence, sent}>`, `table(ReportFilters, int, CarbonInterface): Query\Builder`
(per bucket), `priceSql(): string` (the PricingService mirror). Filters: date, interval.

- [ ] `tests/Feature/Reports/CartReportTest.php`: `test_started_converted_abandoned_and_reachable`, `test_value_at_todays_prices_matches_the_pricing_service`
  (sale price, percentage and fixed discounts in and out of their window, a midnight end date),
  `test_recovered_needs_a_sent_reminder_and_a_live_order`, `test_reminders_by_channel_in_the_period`, `test_the_csv_export`.

### Task 9: Conversion and AOV

**Files:** `Services/ConversionReport.php`, `Http/Controllers/Admin/ConversionReportController.php`,
`resources/js/Pages/Conversion/Index.vue`. Cart repository: `conversion(ReportFilters): array<'b2c'|'b2b', {carts, converted}>`,
`conversionTable(ReportFilters): Query\Builder` (bucket, per channel carts/converted). AOV per channel
from `summaryByChannel`. The page states: "No visits are tracked: conversion is carts that became an order ÷ carts started."

- [ ] `tests/Feature/Reports/ConversionReportTest.php`: `test_cart_conversion_per_channel`, `test_aov_per_channel_uses_confirmed_orders`,
  `test_the_table_per_bucket`, `test_the_csv_export`.

### Task 10: Finish

- [ ] Architecture tests (`LayeringTest`, `TableFiltersTest`, `ArabicTextsTest`, `SolarIconsTest`, `InertiaPagesExistTest`).
- [ ] Whole PHP suite once on `malbo_phase9_testing`; `npx vue-tsc --noEmit`; `npm run test:js`.
- [ ] `php artisan migrate`, `php artisan db:seed` on `malbo_app`.
- [ ] "As built" below.

---

## As built

- **Module** `Modules/Reports` (priority -1), registered in `modules_statuses.json`, `resources/js/lib/pages.ts`
  and `InertiaPagesExistTest`. Routes `/{locale}/admin/reports/{sales|channels|products|sellers|returns|merchants|carts|conversion}`
  (+ `/export`), names `reports.<name>.index|export`, each `->can('<X> Report View')`. Sidebar group "Reports"
  (section Sales, sort 5, icon `reports` = Solar `chart-2-bold-duotone`). `PermissionLabels` knows the eight.
- **Support**: `ReportPeriod` (presets, explicit dates win, a malformed date spoils the range → this month,
  5-year cap, `bounds()` as app-timezone instants, `buckets()`, `interval()`), `ReportFilters` (`filter[...]`,
  `allowed()` no-ops, `search()`, `exportQuery()` = the table's own filters through spatie for the CSV),
  `SaleSql` (statuses, `lines()`, `orders()`, `bucket()`, aggregates, `localized()`), `ReportMoney` (bcmath,
  no floats), `ReportFormat` (labels, change, rate, average), `ReportRow` (fromSub tables), `CsvExport`
  (BOM, Excel formula-safe text cells).
- **Repositories**: `SalesReportRepository` (summary, pending, timeline, channels, line totals, product /
  category / colour / size / bundle grouping, slow sellers, last sold, return summary, reasons, return table,
  category subtree), `MerchantReportRepository`, `CartReportRepository` (summary, buckets, reminders,
  conversion; `priceSql()` mirrors `PricingService`).
- **Services** (one per report) + `ReportOptions` (branches, categories, export URL). Thin controllers.
- **Pages** `Reports/{Sales,Channels,Products,Sellers,Returns,Merchants,Carts,Conversion}/Index`; shared
  `Components/{ReportFilterBar,ReportKpis,ReportCard,ReportEmpty,ChannelCard}.vue`, `reportQuery.ts`, `types.ts`.
  Every table is a DataTable whose `only` reloads the whole report.
- **Differences from the plan**: the category filter is also on the returns and slow-sellers reports; the
  slow-sellers list requires `status = published`; the merchant name links to its ledger page; "Exchange
  rate" was already a currency text, so the returns page says "Rate of exchanges".
- **Measured**: 60,000 orders / 90,000 lines over 10 months: each report query 210–320 ms on the dev machine.
- **Indexes** (`2026_11_13_000001_add_report_indexes.php`): orders (created_at, id), return requests
  (status, type), store carts (created_at), cart reminders (status, sent_at), wholesale carts (created_at).

### Review fix pass (2026-11-13)

1. **Chart XSS** (all charts): `resources/js/lib/chartOptions.ts` builds every ApexCharts option set; labels,
   categories and series names pass through `escapeHtml` where ApexCharts writes HTML (tooltip title,
   x-axis tooltip, series name in the tooltip, legend). Axis labels are SVG text and stay raw.
   `Components/dashboard/__tests__/ApexChart.spec.ts`.
2. **Stable paging**: `AdminTable::tieBreaker()` (a unique column after the chosen sort) and comma-separated
   default sorts; every report table has one (`bucket` or `row_key`); slow sellers default `units,-stock`,
   as their CSV. `ProductReportTest::test_pages_never_share_rows_when_sorted_by_ties`,
   `SellerReportTest::test_ties_are_ordered_by_stock_on_the_page_and_in_the_csv`.
3. **Search with a comma** (project-wide): `AdminFilter::likeTerm()`/`whole()` join a value the query builder
   split; `AdminTable::search`, `AdminFilter::text/translated` and `ReportFilters::search` use it.
   `tests/Feature/Core/TableSearchWithCommaTest.php`, `ProductReportTest::test_a_search_with_a_comma`.
4. **Empty values last**: merchant `aov` and `last_order_at` sort `NULLS LAST` both ways; the return rate is
   0 (not null) without units. `MerchantReportTest::test_descending_sorts_put_empty_values_last`.
5. **Merchant figures follow the table filters** (`MerchantReportRepository::totals(Builder)`; the chart too).
   `MerchantReportTest::test_totals_follow_the_table_filters`.
6. **Delivery fee of an order returned without being delivered** (no DELIVERED in its status history) is
   left out of delivery fees and total sales; the order still counts. The KPI hint says so.
   `SalesReportTest::test_a_returned_order_nets_to_zero`, `test_a_delivered_then_returned_order_keeps_its_delivery_fee`.
7. **Reasons = KPIs**: on a RETURNED order, request lines count as returns (their reason kept), as the units
   do. `ReturnReportTest::test_reasons_add_up_to_the_returned_and_exchanged_units`.
8. **Last sold**: product ids expanded to their variants in PHP, lines found by `oi.product_id IN (…)`
   (indexed), 1,000 ids a query.
9. **CSV**: every export runs its aggregate once (`cursor()`), still streamed.
10. **Sets by category**: `by=bundle` filters on the set's own categories, so a set is listed whole.
    `ProductReportTest::test_bundles_filtered_by_category_are_whole`.
11. **Comparison period**: this month ↔ the same days of the month before (up to its end), last month ↔ the
    month before, days ↔ the same number of days before; the pages show the compared dates (`compare`).
    `ReportPeriodTest::test_month_presets_compare_with_the_previous_month`, `SalesReportTest::test_the_comparison_period_is_shown`.

### Deploy notes

- `php artisan migrate` (indexes only), `php artisan db:seed` (the eight report permissions; Super Admin gets
  them). Give other roles the report permissions they need on the Roles page.
- Set `REPORTS_TIMEZONE=Asia/Jerusalem` (see `.env.example`); without it days end at midnight UTC.
- `npm run build` (new pages).
