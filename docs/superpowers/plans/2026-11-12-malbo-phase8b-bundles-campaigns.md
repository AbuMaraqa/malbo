# MALBO Phase 8B: Sets and bundles, complete the look, campaign pages, Instagram

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Strict TDD: each named
> test is run and seen failing before the code that makes it pass.

**Goal:** spec areas 3, 10 and 21 (phase 8, second half):
- *sets* (one product of several pieces at its own price) and *bundle offers* (separate products sold
  together at an offer price or a % off), with no stock of their own: what can be sold comes from the
  components, each fashion component bought in the size/colour the buyer picks;
- "complete the look": products staff link to a product, shown on its page with a quick add;
- campaign pages built from ready blocks, with slug, publish state and dates, SEO, ar/en/he, a staff
  preview;
- an admin-managed Instagram section (posts, tagged products) on the home page and as a campaign
  block, behind an `InstagramFeedSource` contract.

**Architecture:**
- A set or bundle is an `items_products` row with `bundle_type` (`set` | `bundle`) and components in
  `items_bundle_components`. It reuses the product page route, categories, search, sitemap, cards,
  wishlist, reviews, feed and Meta ids. Its own stock columns are inert (`manage_stock` true,
  `stock_quantity` 0, `stock_status` out_of_stock): any path that forgets it is a bundle sees "out of
  stock", and InventoryService refuses to sell the row itself.
- `Items\Support\BundleStock` (pure, over loaded models) decides availability and how many can be
  sold; `ProductConfigurator::available/maxQuantity` and `ProductBadges` delegate to it for bundles.
- Selling: the cart line holds the bundle and the chosen variant per component; `CheckoutService`
  passes `components` to `OrderService::checkout`, which expands the bundle into one order item per
  component (allocated price, bundle snapshot) and sells all component quantities in **one**
  `InventoryService::sell` call (rows locked in id order: no oversell, no deadlock). Cancel/return
  work unchanged on component lines.
- Campaign pages and Instagram live in the Store module; complete the look in Items (pivot) + Store
  (page).

**Tech Stack:** Laravel 13, nwidart modules, PostgreSQL, Inertia 3 + Vue 3 + TypeScript, PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-08-malbo-platform.md` (areas 3, 10, 21; phase 8).

## Global Constraints

- `CLAUDE.md` rules: services write (`DB::transaction` across tables), repositories read, thin
  controllers (`LayeringTest`), `->can()` on every admin route, PermissionSeeder, sidebar `->section()`,
  DataTable + non-empty filters via `AdminFilter`, separate Add/Edit pages, Solar icons via
  `resources/js/lib/icons.ts`, logical utilities, dark mode in the admin, `jsonb`, indexed foreign
  keys, `whereLike(..., caseSensitive: false)`, `RichText` for editor HTML, heavy libs lazy.
- InventoryService stays the only stock writer; OrderService the only order writer.
  `ProductsBecameOrderable` keeps firing only from InventoryService.
- Bundles are **B2C only**: never in the wholesale catalog, the wholesale price table, admin order
  lines, the stock pages, low-stock lists, or the mobile API's `place()`.
- No wholesale or cost price in any storefront output, feed or event.
- Storefront texts in `Modules/Store/Lang/ar.json` and `he.json`; admin texts in the module's `ar.json`.
- Storefront Vue: no `dark:`, no admin components, browser globals only in `onMounted`.
- Additive migrations only. Tests on `DB_DATABASE=malbo_phase8b_testing`. No commits.

## Decisions (cost if wrong)

1. **Bundle = product row** (`bundle_type`), not a new entity. Cost: every product query that must
   exclude bundles needs `whereNull('bundle_type')` (wholesale, stock, admin product list, order lines);
   pinned by tests in Task 2.
2. **One price per bundle**, the row's own price columns through `PricingService`, whatever variants
   are chosen. `percent` mode stores `base_price` = sum of the components' regular prices × quantity
   (a component with variants: its lowest active variant's regular price) and `discount_type`
   percentage; refreshed when the bundle is saved and when a component row's price columns change.
   Cost: a dated discount on a component does not move the bundle's base (by design: "% off the
   regular prices").
3. **Components** are a product (buyer picks among its active visible variants) or a pinned variant.
   Component products need not be published (a set's trousers may not be sold alone), only active and
   not deleted. A deleted component makes the bundle unavailable (FK `nullOnDelete`).
4. **Availability** = min over components of floor(units ÷ quantity); a component's units = the best
   of its choices; unmanaged/backorder = unlimited. A chosen configuration aggregates per variant.
5. **Price allocation**: per bundle unit, proportional to the chosen variants' current store prices ×
   quantity, in cents, largest remainder; any cent that cannot be spread (all quantities > the
   remainder) goes to the first line's `total_price`. Lines sum exactly to bundle price × quantity.
   Kept bundle lines keep their saved `total_price` on admin edits. Partial returns refund the
   allocated price; reports by product/colour/size read component lines.
6. **Order lines**: no header line; component lines carry `bundle_product_id`, `bundle_group`
   (uuid per bundle line of the order) and `bundle` (jsonb snapshot: type, sku, name per locale,
   quantity, unit_price). Admin edits may keep a set or remove it whole, never change its lines.
7. **Meta ids**: a bundle is one catalog item (its SKU, else `P<id>`) in the feed, ViewContent,
   AddToCart, InitiateCheckout and Purchase (bundle lines grouped back into one content).
8. **Sales count**: components count as sold; the bundle row's `sales_count` stays 0 (best-selling
   sort puts bundles last). Phase 9 reports read lines directly.
9. **Coupons** treat a bundle line as its own product (bundle id and categories), at checkout and on
   admin re-evaluation (`bundle_product_id`).
10. **Complete the look**: no automatic fallback (the "You may also like" section already is one);
    bundles cannot be in a look; at most 8 per product.
11. **Campaign pages**: block texts in a `content` jsonb per locale (edited in the admin's current
    locale like every other form); preview = the storefront URL opened by staff with
    `Campaign Page View List` (drafts and out-of-window pages render with a preview bar, noindex, no
    Meta events). Links in blocks: `https://`, `http://` or a site path starting with `/`.
12. **Instagram**: posts entered by hand (`source = manual`); `InstagramFeedSource::posts(int)` is what
    the storefront reads; `ManualInstagramFeed` is bound by `config('store.instagram.source')`.
    Home section switched by `StoreAppearanceSettings::instagram_enabled`.

## Review Focus

1. **Oversell across lines**: a bundle and the same shirt alone in one cart, together more than the
   stock: checkout refuses, nothing saved. Pinned in Task 4 (`test_a_bundle_and_its_component_alone_cannot_oversell`).
2. **Selling the bundle row directly** (admin order, API): refused. Pinned in Task 4
   (`test_a_bundle_row_is_never_an_order_line`).
3. **Allocation rounding**: 100.01 over three components, quantities 2 and 3. Pinned in Task 3.
4. **Variant choice from the browser**: a variant of another product, an inactive one, or a missing
   choice is refused. Pinned in Task 3/4 (`test_a_choice_must_be_a_variant_of_its_component`).
5. **Block links and HTML**: `javascript:` links refused, rich text sanitized. Pinned in Task 8.

---

### Task 1: Schema

**Files:** `Modules/Items/database/migrations/2026_11_12_000001_add_bundles_and_looks.php`
(`items_products.bundle_type` string(16) nullable + index, `bundle_discount_percent` decimal(5,2)
nullable; `items_bundle_components` id, bundle_id → products cascade (index), product_id → products
nullable nullOnDelete (index), quantity smallint default 1, sort_order int default 0, timestamps;
`items_product_looks` product_id → cascade, look_product_id → cascade (index), sort_order, primary
(product_id, look_product_id); `items_order_items` + `bundle_product_id` → products nullOnDelete (index),
`bundle_group` string(36) nullable index, `bundle` jsonb nullable);
`Modules/Store/database/migrations/2026_11_12_100001_add_bundles_campaigns_instagram.php`
(`store_cart_items` + `choice_key` string(64) default '', `components` jsonb nullable, unique
(cart_id, product_id) → (cart_id, product_id, choice_key); `store_campaign_pages`,
`store_campaign_page_translations`, `store_campaign_blocks`, `store_instagram_posts`,
`store_instagram_post_product`); `database/settings/2026_11_12_000001_add_store_instagram_settings.php`
(`store_appearance.instagram_enabled` false, `instagram_title` []).

- [ ] `tests/Feature/Store/BundleSchemaTest.php`: `test_the_new_tables_and_columns`.

### Task 2: Bundle entity, stock rules and admin pages

**Files:** `Items/Entities/BundleComponent.php`, `Product` (`bundleComponents()`, `looks()`, `isBundle()`,
fillable `bundle_type`, `bundle_discount_percent`), `Items/Enums/BundleType.php`, `Items/Support/BundleStock.php`,
`Items/Repositories/{BundleRepository,Contracts/BundleRepositoryInterface}.php`, `Items/Services/BundleService.php`,
`Http/Requests/Admin/BundleRequest.php`, `Http/Controllers/Admin/BundleController.php`,
`Pages/Bundle/{Index,Add,Edit,BundleFields}.vue`, `parts/BundleComponentsField.vue`; routes `bundles.*`;
permissions `Bundle View List|Create|Update|Delete`; sidebar item under Products; `ProductRepository`
(`sellable()`, `tableQuery()` exclude bundles), `WholesaleProductRepository` (`trees()`, `adminTableQuery()`).

**Interfaces:**
- `BundleStock::choices(BundleComponent): Collection<Product>`; `units(Product): ?int` (null = unlimited);
  `available(Product $bundle): ?int` (bundles sellable now, null unlimited, 0 none);
  `orderable(Product): bool`; `maxFor(Product $bundle, array $choices): ?int` (component id => variant id);
  `relations(): array` (eager loads for a bundle).
- `BundleService::create(array $data, string $locale, int $userId): Product`, `update(Product, array, string)`,
  `delete(Product)`, `refreshPrices(list<int> $componentProductIds): void`.
- `BundleRepositoryInterface`: `tableQuery()`, `forEdit(int)`, `containing(list<int> $productIds): list<int>`,
  `componentOptions(string $term)`, `percentBundlesContaining(list<int>)`.

- [ ] `tests/Feature/Items/BundlePagesTest.php`: `test_the_list_filters_and_permissions`,
  `test_add_saves_the_bundle_its_components_and_inert_stock`, `test_percent_mode_prices_from_the_components`,
  `test_a_component_price_change_refreshes_percent_bundles`, `test_validation_errors`,
  `test_bundles_stay_out_of_products_stock_and_wholesale`.
- [ ] `tests/Unit/Items/BundleStockTest.php` (DB): `test_available_is_the_min_over_components`,
  `test_unmanaged_is_unlimited_and_a_deleted_component_blocks`, `test_max_for_aggregates_a_shared_variant`.

### Task 3: Storefront page, cart and quote

**Files:** `Store/Services/BundlePage.php` (options per component, quote), `ProductConfigurator`,
`ProductBadges`, `ProductRepository` (`cardRelations`, `findPublishedBySlug`, `forCart` load bundle
relations), `CartService` (`add(..., ?array $components)`, choice key, bundle lines), `CartItemRequest`
(`components`), `CartController`, `ProductController` (renders `Store/Storefront/BundlePage`),
`Pages/Storefront/BundlePage.vue`, `Components/storefront/{BundleComponentPicker,BundleOrderPanel}.vue`.

**Interfaces:**
- Cart line extra keys: `bundle: bool`, `components: list<{product_id, quantity}>` (per bundle).
- `BundlePage::options(Product): list<{id, name, quantity, image, attributes, variants, fixed_variant_id}>`,
  `quote(Product, ?array{components?, quantity?}): {components, quantity, max_quantity, price, in_stock, total_label, problems, whatsapp_url, ready}`.
- `Items\Support\BundleAllocation::split(float $unitPrice, list<array{key: int|string, quantity: int, weight: float}>): array<key, array{unit: float, extra: float}>`.

- [ ] `tests/Unit/Items/BundleAllocationTest.php`: `test_proportional_in_cents`, `test_rounding_sums_exactly`,
  `test_zero_weights_split_by_quantity`.
- [ ] `tests/Feature/Store/BundleStorefrontTest.php`: `test_the_bundle_page_lists_components_and_their_choices`,
  `test_the_quote_needs_every_choice_and_caps_by_stock`, `test_a_choice_must_be_a_variant_of_its_component`,
  `test_the_same_bundle_with_other_choices_is_another_line`, `test_cards_and_badges_follow_the_components`,
  `test_search_finds_a_bundle`, `test_notify_me_for_a_bundle`.
- [ ] Vitest `BundleComponentPicker.spec.ts`.

### Task 4: Checkout and order lines

**Files:** `OrderService` (`checkout` expands `components`; `pricedLines`/`place` refuse bundle rows;
`update` keeps or removes sets whole; coupon facts use `bundle_product_id`), `OrderItem` (fillable,
casts), `CheckoutService::place`, `StockAlertService::notifyAvailable` (bundles containing the ids),
`ReviewRepository::eligibleOrderId` (bundle lines).

- [ ] `tests/Feature/Store/BundleCheckoutTest.php`: `test_a_bundle_order_writes_component_lines_and_stock`,
  `test_a_bundle_and_its_component_alone_cannot_oversell`, `test_cancel_puts_the_components_back`,
  `test_a_partial_return_refunds_the_allocated_price`, `test_a_bundle_row_is_never_an_order_line`,
  `test_admin_edit_keeps_or_removes_a_set_whole`, `test_back_in_stock_reaches_bundle_subscribers`.

### Task 5: Order views, documents, Meta

**Files:** `OrderController@edit` (items `bundle`), `Pages/Order/{Edit,OrderFields}.vue`,
`parts/{OrderItemsCard,OrderBundleLines}.vue`, `OrderDocument` (`lines.*.bundle`), `invoice.blade.php`,
`OrderTracking::result`, `AccountOrders`, storefront order views, `MetaEvents` (viewContent for a
bundle, purchase groups), `CatalogFeed` (bundle availability).

- [ ] `tests/Feature/Items/BundleOrderViewsTest.php`: `test_the_edit_page_groups_set_lines`,
  `test_the_invoice_prints_the_set_and_its_pieces`, `test_tracking_names_the_set`.
- [ ] `tests/Feature/Store/BundleMarketingTest.php`: `test_the_feed_has_one_item_per_bundle_with_its_availability`,
  `test_purchase_sends_the_bundle_id_once`.

### Task 6: Complete the look

**Files:** `Items/Services/LookService.php`, `ProductRepository::{lookFor,lookOptions}`,
`ProductController@{edit,look}` (route `products.look.update`, `Product Update`),
`Pages/Product/parts/CompleteTheLookCard.vue`, `Store/Services/ProductPage::look()`,
`Components/storefront/CompleteTheLook.vue`.

- [ ] `tests/Feature/Store/CompleteTheLookTest.php`: `test_staff_link_products_in_order`,
  `test_only_other_non_bundle_parents_up_to_eight`, `test_the_page_shows_the_look_with_its_sizes`,
  `test_none_set_shows_nothing`.
- [ ] Vitest `CompleteTheLook.spec.ts`.

### Task 7: Instagram

**Files:** `Store/Entities/InstagramPost.php`, `Contracts/InstagramFeedSource.php`,
`Support/ManualInstagramFeed.php`, `Repositories/{InstagramPostRepository,Contracts/...}.php`,
`Services/InstagramService.php`, `Http/Requests/Admin/{InstagramPostRequest,InstagramSettingsRequest}.php`,
`Http/Controllers/Admin/InstagramPostController.php`, `Pages/InstagramPost/{Index,Add,Edit,InstagramPostFields}.vue`,
`Components/storefront/HomeInstagram.vue`, `HomeController`, `Home.vue`; permissions `Instagram Post …`.

- [ ] `tests/Feature/Store/InstagramTest.php`: `test_crud_filters_and_permissions`,
  `test_the_home_shows_active_posts_only_when_enabled`, `test_tagged_products_are_shop_links`,
  `test_the_source_is_a_contract`.

### Task 8: Campaign pages

**Files:** `Store/Entities/{CampaignPage,CampaignPageTranslation,CampaignBlock}.php`,
`Enums/CampaignBlockType.php`, `Repositories/{CampaignPageRepository,Contracts/...}.php`,
`Services/{CampaignPageService,CampaignPageView}.php`, `Http/Requests/Admin/CampaignPageRequest.php`,
`Http/Controllers/Admin/CampaignPageController.php`, `Http/Controllers/Storefront/CampaignController.php`
(`store.campaign`, `/{locale}/campaign/{slug}`), `Pages/CampaignPage/{Index,Add,Edit,CampaignPageFields}.vue`,
`parts/{CampaignBlockEditor,blocks/*}.vue`, `parts/campaignBlocks.ts`, `Pages/Storefront/Campaign.vue`,
`Components/storefront/campaign/*.vue`; sitemap; permissions `Campaign Page …`.

- [ ] `tests/Feature/Store/CampaignPagesTest.php`: `test_crud_filters_and_permissions`,
  `test_blocks_are_saved_in_order_with_their_locale_texts_and_images`, `test_links_and_html_are_safe`,
  `test_a_published_page_in_its_window_is_public`, `test_drafts_and_closed_windows_are_404_but_previewed_by_staff`,
  `test_product_grid_from_picked_products_or_a_category`, `test_the_sitemap_lists_live_campaigns`,
  `test_page_view_is_built_but_not_in_preview`.
- [ ] Vitest `campaignBlocks.spec.ts` and `CampaignBlockEditor.spec.ts`.

### Task 9: Finish

- [ ] Whole PHP suite once; `npx vue-tsc --noEmit`; `npm run test:js`; `php artisan migrate`,
  `php artisan db:seed` on `malbo_app`.

---

## As built

- **Schema** as Task 1. `store_cart_items` unique is now (cart_id, product_id, choice_key); `choice_key`
  is '' for ordinary products, an md5 of the component choices for a bundle.
- **Bundle rows**: `BundleService` writes `manage_stock` true, `stock_status` out_of_stock, never
  stock; `ProductRepository::sellable()` (stock pages, low stock, admin order lines, inventory),
  `tableQuery()` (products list), `searchParents()` (look picker) and Wholesale `trees()` /
  `adminTableQuery()` leave bundles out. Opening `/products/{bundle}/edit` redirects to the bundle form.
  `OrderService::create/update/place` refuse a bundle row; `checkout()` refuses one without
  `components` and any components on a B2B order.
- **BundleStock** (`Items/Support`): `relations(full)`, `choices`, `choosable`, `units`, `available`,
  `orderable`, `resolve(bundle, choices)`, `maxFor(bundle, chosen)`, `regularPrice`. Every storefront
  read eager-loads it (`cardRelations`, `findPublishedBySlug`, `forCart`, the feed).
- **Percent pricing** refresh: `Product::updated` (base_price, sale_price or is_active of a non-bundle)
  → `BundleService::refreshPrices()` (ItemsServiceProvider).
- **Storefront**: bundles render `Store/Storefront/BundlePage` (`BundlePage::components/quote`;
  quote reloads with `components[id]=variant&quantity=`). No preselected sizes. Cart lines of a bundle:
  `bundle: true`, `options` = pieces with the size chosen, `components` = what checkout sells.
- **Order lines**: `OrderService::bundleLines()` (BundleAllocation weights = each chosen variant's
  current store price × quantity). Snapshot `bundle` = {type, sku, name{locale}, quantity, unit_price}.
  `Items\Support\OrderBundles` reads it for the admin edit page (`OrderBundleLines.vue`: a set is kept
  or removed whole), the invoice (set row with its price, pieces under it without prices), tracking
  and account order pages ("Part of …"), and the Purchase event (one content per set).
  The waybill keeps its piece count (pieces in the parcel).
- **Stock alerts**: `notifyAvailable()` adds the bundles containing the items (or their parents);
  BundleStock decides. Review eligibility counts `bundle_product_id`.
- **Complete the look**: `items_product_looks`, `LookService::sync`, routes `products.look.update`,
  `products.look.lookup` (`Product Update`), `CompleteTheLookCard` on the product edit page (its own
  form), `ProductPage::look()` + `CompleteTheLook.vue` (a select of sizes, out-of-stock ones disabled).
  Not on bundle pages.
- **Instagram**: routes under `admin/store/instagram` (`instagram-posts.*`), settings on the same page
  (`instagram_enabled`, `instagram_title` per locale), `InstagramFeedSource` bound to
  `ManualInstagramFeed` (`config('store.instagram.source')`), `InstagramService::import()` for a sync,
  `InstagramGrid.vue` on the home page (`instagram` prop, 8 posts) and in campaigns. Pictures through
  `Store\Support\StoreImages` (`ImageService::reencode`, default media disk).
- **Campaign pages**: routes under `admin/store/campaigns` (`campaign-pages.*`), storefront
  `store.campaign` `/{locale}/campaign/{slug}`, sitemap entries for live pages. Block texts are in
  `content` per locale; a saved block keeps the other locales' texts and its picture; a block whose
  type changed is replaced. Preview = the storefront URL for staff with `Campaign Page View List`
  (preview bar, noindex, `marketing` prop null). Category grids use the shop listing's cached first page.
- **Vitest under Modules/**: specs also live next to module pages
  (`Modules/Store/resources/js/Pages/CampaignPage/parts/__tests__`). Run Vitest from `C:\…` (an
  upper-case drive letter): from a lower-case `c:` working directory every spec fails with
  "Cannot read properties of undefined (reading 'config')".

## Review fix pass (supersedes the notes above where they differ)

1. **Bundle edits keep carts**: `BundleService::syncComponents` updates components in place by
   the `id` the form sends back (`components.*.id`), adds new rows, deletes the ones left out. A cart
   line's saved choices must match the bundle's current components exactly (same component ids, and
   each piece the same product: `CartService::bundleChosen`), else the line says the choice is no
   longer available. (`BundleReviewFixesTest::test_a_cart_line_survives_a_bundle_edit_and_a_removed_piece_invalidates_it`)
2. **Shared stock**: `BundleStock::available()` judges components of one product family (the same
   product, its variants or a pinned variant of it) together: the best assignment of their choices,
   found exactly over at most 4096 combinations, else a greedy assignment (binary search up to the
   pooled bound, pieces largest first, each taking the choice with most stock left), which a buyer
   can always reach: never above what `maxFor` sells for some choice, possibly below on huge families.
   (`test_two_pieces_of_one_product_share_its_stock`, `test_a_large_family_gets_a_bound_a_buyer_can_reach`)
3. **Product admin refuses bundles**: `ProductRepository::findProductOrFail()` (404 for a bundle) in
   update, status, active, destroy and look; `withVariations()` (barcodes, branch prices) leaves
   bundles out; bulk delete skips them; `ProductRequest` answers 404 for a bundle before validating;
   Wholesale `forPriceEdit()` leaves them out. (`test_the_product_admin_refuses_bundles`)
4. **Exchanges of set pieces**: lines with a `bundle_group` use `BundleStock::pieceProblem()` (active,
   not deleted, parent active, no hidden value, stock; not "published"), for buyer and staff, in
   `exchangeOptions()` and the request check. (`test_a_set_piece_not_sold_alone_can_be_exchanged`)
5. **App API**: products index and sync (`changedSince`) leave bundles out; show answers 404.
   (`test_the_app_api_lists_no_bundles`)
6. **Percent prices**: kept stored (the shop sorts and filters by price in SQL); refreshed on a
   product's update (price, activity), creation, deletion, restore and force deletion (its own id and
   its parent's), and when an attribute value is hidden or shown (`BundleService::refreshForValue`).
   (`test_percent_bundles_follow_deleted_and_new_variants`, `test_percent_bundles_follow_a_hidden_value`)
7. **Campaign links**: a site path is one "/" followed by neither "/" nor "\", with no backslash
   anywhere; `CampaignLink.vue` treats anything else as another site.
   (`CampaignPagesTest::test_a_link_is_a_site_path_or_a_web_address_only`, `CampaignLink.spec.ts`)
8. **Instagram import** keeps only https:// links (skipped: logged, null).
   (`InstagramTest::test_an_import_keeps_only_https_links`)
9. **Campaign block pictures**: a saved picture counts only for a saved block of the same type.
   (`CampaignPagesTest::test_a_block_whose_type_changed_needs_its_own_picture`)

### Deploy notes

- `php artisan migrate` (Items and Store migrations, one settings migration), then
  `php artisan db:seed` (new permissions: Bundle, Campaign Page, Instagram Post; Super Admin gets them).
- Admin → Products → Sets and bundles; Marketing → Campaign pages, Instagram (switch the home section
  on there; the follow button needs the Instagram link in Store appearance).
- Optional env: `STORE_INSTAGRAM_SOURCE` (only `manual` exists).
