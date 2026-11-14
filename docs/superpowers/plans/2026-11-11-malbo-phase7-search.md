# MALBO Phase 7: Search and filters

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Strict TDD: each named
> test is run and seen failing before the code that makes it pass.

**Goal:** spec section 4 (phase 7):
- search by name (any locale), SKU (parent or variant), tag and category names, ranked, with
  suggestions while typing in the header;
- filters on the shop and category pages: filterable attributes (color, size, fit...) with facet
  counts, price range, the existing category filter, all in the URL;
- sorting: newest, best selling, price both ways, featured;
- available sizes on product cards; related products that share a category and a filterable value.

**Architecture:** additive. Items owns the columns (`items_attributes.is_filterable`,
`items_products.sales_count`) and the SQL (`ProductRepository`); Store owns the shop's props
(`StorefrontShop`), the cache (`StorefrontCatalog`) and the Vue. `sales_count` is written only by
`Items\Services\SalesCounter`, called from `OrderService` wherever an order's lines or status move.

**Rules this phase fixes (document, test):**
- *A product matches attribute filters* when one of its variants is active, not deleted, visible
  (no inactive value: `ProductConfigurator::visible`) and orderable (`ProductConfigurator::available`:
  not `out_of_stock`, and unmanaged, backorder or stock > 0), and has, for every filtered attribute,
  one of the chosen values. OR inside an attribute, AND across attributes, on the same variant.
- *Displayed price in SQL* (`ProductRepository::PRICE_SQL`): exactly PricingService on the parent's
  columns: sale price when > 0 and below base; then an open percentage (capped at 100) or fixed
  discount, a midnight end date meaning the whole day; floored at 0, rounded to 2. "Now" is bound
  from PHP (app timezone). Used by the price filter, its bounds and both price sorts (dated discounts
  now count in the sort, they did not before).
- *sales_count* (parents only) = units on lines (variant lines count to their parent) of orders not
  deleted whose status is not cancelled, rejected or returned. B2C and B2B. A partial return request
  that leaves the order delivered does not lower it; a fully returned order (RETURNED) does; an
  exchanged order keeps counting. `items:recount-sales` rebuilds exactly this.
- *Card sizes*: the size attribute is the filterable, non-color attribute with slug `size`, else the
  filterable non-color one with the lowest id. A card lists that attribute's values on the visible
  active variants in value order; one that no such variant can order is shown struck through.

## Global Constraints

- `CLAUDE.md`: services write, repositories read, thin controllers (`LayeringTest`), PostgreSQL rules
  (`whereLike(..., caseSensitive: false)`, indexed foreign keys, jsonb), Solar icons, logical
  utilities, `Lang/ar.json` (storefront texts in `Modules/Store/Lang`, also `he.json`), no browser
  globals in storefront components outside `onMounted` files (`StorefrontFrontendTest`).
- Every user value is cleaned before it reaches SQL: ids are integers of filterable attributes and
  their active values; prices are numbers ≥ 0; search text is valid UTF-8, no NUL, ≤ 100 chars.
- No query per value or per product: facets are one grouped query, the listing one paginated query
  plus its eager loads. No lazy loading.
- Additive migrations only. Tests on `malbo_phase7_testing`. No commits.

## Review Focus

1. **Same-variant matching.** Black-M + White-L must not match Black-L. Pinned in Task 4
   (`test_attributes_are_and_on_the_same_variant`).
2. **Price SQL vs PricingService drift.** Pinned in Task 4 (`test_the_sql_price_matches_pricing_service`).
3. **sales_count drift.** Every OrderService path that moves lines or status. Pinned in Task 5
   (`test_recount_matches_the_running_count`).
4. **Stale cards.** Stock and sales writes bump the catalog version. Pinned in Tasks 5 and 6.

---

### Task 1: Schema

**Files:** `Modules/Items/database/migrations/2026_11_11_000001_add_search_and_filter_columns.php`
(`items_attributes.is_filterable` boolean default false, true for existing COLOR attributes;
`items_products.sales_count` unsigned int default 0 + index; indexes pivot `(attribute_id, product_id)`,
products `(parent_id, status, is_active)`; trigram GIN on `items_products.sku` and
`items_tag_translations.name` (creates `pg_trgm` if missing)). `Attribute` fillable/cast.

- [x] `tests/Feature/Items/SearchFilterSchemaTest.php`: `test_the_new_columns_and_their_defaults`,
  `test_the_filter_and_search_indexes_exist`.

### Task 2: Filterable attributes in the admin

**Files:** `AttributeRequest` (`is_filterable` boolean), `AttributeService::create/update`
(default: COLOR), `AttributeController::{add,edit,index}`, `Pages/Attribute/{AttributeFields,Add,Edit}.vue`
(a Toggle), `Items/Lang/{ar,he}.json`. Catalog cache bumped on Attribute/AttributeValue (and their
translation) writes (`StoreServiceProvider::forgetCatalogOnWrites`).

- [x] In `AttributePagesTest`: `test_staff_mark_an_attribute_filterable`, `test_a_color_attribute_is_filterable_unless_said_otherwise`.

### Task 3: Search and suggestions

**Files:** `ProductRepository::storefrontListing` (search + rank), `searchSuggestions(string, int)`,
`CategoryRepository::searchActive(string, int)` (+ contracts); `Store/Services/StorefrontSearch.php`;
`Store/Http/Controllers/Storefront/SearchController.php`; route `GET /{locale}/search/suggest`
(`store.search.suggest`, `throttle:store-search` = 60/min per IP).

**Interfaces:**
- Search matches: a translation name (any locale), the parent's SKU, a non-deleted variant's SKU, a
  tag name, an active category name; all `ILIKE '%q%'` (trigram indexed).
- Rank: 0 a name equals q (case-insensitive), 1 a name starts with q, 2 the rest; then the sort.
- `StorefrontSearch::suggest(string $q): array{products: list<{name, image, price, url}>, categories: list<{name, url}>}`
  — ≥ 2 characters, ≤ 6 products, ≤ 4 categories, cached per locale and query in the catalog cache
  (prices computed on read).

- [x] `tests/Feature/Store/StorefrontSearchTest.php`: `test_search_matches_a_name_in_any_locale`,
  `test_search_matches_the_parent_or_a_variant_sku`, `test_search_matches_tag_and_category_names`,
  `test_exact_then_prefix_name_matches_come_first`.
- [x] `tests/Feature/Store/SearchSuggestTest.php`: `test_it_needs_two_characters`,
  `test_it_returns_up_to_six_products_and_four_categories`, `test_a_product_carries_name_image_price_and_url`,
  `test_hidden_products_and_inactive_categories_are_left_out`, `test_it_is_throttled`.

### Task 4: Attribute filters, price range, facets

**Files:** `ProductRepository::storefrontListing` (`attributes`, `price_min`, `price_max`),
`storefrontFacets(array $filters, array $attributeIds): array{values: array<int,int>, price: {min,max}}`;
`AttributeRepository::filterable()` (+ contract); `Store/Services/StorefrontShop.php` (parse, facets,
`filters` prop: `q, sort, attr, price_min, price_max`; `facets` prop); `StorefrontCatalog::listing`
(cached first page only without filters).

**Interfaces:** query string `attr[<attribute id>]=<value id>,<value id>`, `price_min`, `price_max`.
`facets: {attributes: list<{id, name, swatch, values: list<{id, name, color, count, selected}>}>, price: {min, max, selected_min, selected_max}}`.

- [x] `tests/Feature/Store/ShopFiltersTest.php`: `test_values_of_one_attribute_are_or`,
  `test_attributes_are_and_on_the_same_variant`, `test_hidden_inactive_or_unorderable_variants_do_not_match`,
  `test_backorder_and_unmanaged_variants_match`, `test_only_filterable_attributes_and_their_values_count`,
  `test_facet_counts_follow_the_other_filters`, `test_values_without_matches_are_hidden_unless_selected`,
  `test_price_range_uses_the_displayed_price`, `test_the_sql_price_matches_pricing_service`,
  `test_filters_survive_pagination_and_sort`, `test_malformed_filters_are_ignored`,
  `test_category_filter_still_applies_with_facets`.

### Task 5: Best selling

**Files:** `Items/Services/SalesCounter.php` (`move(array $before, array $after)`, `recount()`),
`Items/Events/ProductSalesChanged.php`, `OrderService` (create, update, place, checkout, changeStatus,
delete), `Items/Console/RecountSales.php` (`items:recount-sales`), `ItemsServiceProvider`;
`ProductRepository` sort `best_selling`; `StorefrontShop::SORTS`; Store listener bumps the cache.

- [x] `tests/Feature/Items/SalesCountTest.php`: `test_b2c_and_b2b_checkouts_count_units_on_the_parent`,
  `test_cancelling_or_rejecting_takes_the_units_back`, `test_a_returned_order_takes_the_units_back_an_exchanged_one_keeps_them`,
  `test_editing_lines_moves_the_count`, `test_deleting_a_counted_order_takes_its_units_back`,
  `test_recount_matches_the_running_count`, `test_the_best_selling_sort`, `test_a_sale_refreshes_the_cached_best_selling_page`.

### Task 6: Sizes on cards, related products

**Files:** `ProductRepository::cardRelations` (value `attribute_id`, `sort_order`, translations),
`AttributeRepository::sizeAttributeId()`, `Store/Support/CardSizes.php`, `ProductCard` (`sizes`),
`StorefrontCatalog` (rows, cached `related`), `ProductRepository::relatedForStorefront` (shared value first).

- [x] `tests/Feature/Store/CardSizesTest.php`: `test_cards_list_the_sizes_of_visible_variants_in_order`,
  `test_a_size_no_variant_can_order_is_marked_unavailable`, `test_no_sizes_without_a_size_attribute`,
  `test_the_size_attribute_prefers_the_size_slug`, `test_a_stock_change_refreshes_the_cached_sizes`.
- [x] In `StorefrontReadsTest`: `test_related_prefers_a_shared_filterable_value`; in `CardSizesTest`: `test_related_cards_are_cached`.

### Task 7: No N+1

- [x] `tests/Feature/Store/ShopPageQueriesTest.php`: `test_the_filtered_shop_page_reads_a_fixed_number_of_queries`,
  `test_the_filtered_shop_page_lazy_loads_nothing`.

### Task 8: Storefront UI

**Files:** `resources/js/Components/storefront/{SearchCombobox,ShopFilters,FilterFacet,PriceRangeFilter,FilterDrawer,CardSizes}.vue`,
`ShopListing.vue` (filters sidebar, drawer on phones, clear all, best selling), `ProductCard.vue` (sizes),
`StorefrontHeader.vue` + `MobileMenu.vue` (combobox), `types.ts`, `Pages/Storefront/{Shop,Category}.vue`,
`Modules/Store/Lang/{ar,he}.json`.

- Combobox: `role=combobox`, `aria-expanded`, `aria-controls`, `aria-activedescendant`,
  `aria-autocomplete=list`; 250 ms debounce, aborts the previous request; ↑/↓ move, Enter opens the
  active option or the shop's results, Esc closes; RTL by logical utilities.
- [x] `npx vue-tsc --noEmit`; `StorefrontFrontendTest`, `ArabicTextsTest`, `SolarIconsTest` green.

### Task 9: Finish

- [x] Whole suite once; migrate `malbo_app`; `php artisan items:recount-sales` there.

---

## Review fix pass (supersedes the rules above where they differ)

- **Best selling is B2C only**: wholesale lines never count (`SalesCounter::counts(Order, OrderStatus)`);
  `recount()` locks `items_orders` in SHARE mode; `OrderService::deleteMany` moves the counts once,
  after every order's stock; migration `2026_11_11_000004_fill_sales_counts` fills existing orders.
- **The size attribute is chosen, not guessed**: `items_attributes.is_size` (at most one: partial
  unique index `items_attributes_one_size`), a toggle in the attribute form; it also makes the
  attribute filterable. Cards list its values and the product page puts the size guide by it.
  None marked: cards show no sizes, and the guide sits by the quantity.
- **Search**: `items_search_norm(text)` (lower; أ إ آ ٱ → ا, ة → ه, ى → ي; tashkeel and tatweel
  removed) on both sides, trigram-indexed on product, category and tag names. Every word (at most 6)
  must match a name, the SKU (product or active variant), a tag, an active category, or an active
  value of a visible variant. With a search the default sort is `relevance` (exact SKU, exact name,
  name prefix, then newest); a sort the shopper picks ignores the rank.
- **Suggestions** are read fresh (no cache); `store-search` is 120/min per address + session and
  300/min per address. `cache:prune-expired` deletes expired cache rows, daily (`routes/console.php`).
- **Related**: by the number of shared filterable values, then the product's deepest category, then newest.
- **Frontend tests**: Vitest (`npm run test:js`), specs in `__tests__/` next to the components.

### Deploy notes

- In the admin, open the Size attribute and turn on "This is the size": until one attribute is marked,
  product cards show no sizes and the size guide sits by the quantity.
- After migrating, `php artisan items:recount-sales` is not needed (the migration fills the counts);
  it stays the repair tool.
- The scheduler must run (`php artisan schedule:run` every minute) for the daily cache prune.
