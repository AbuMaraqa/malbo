# MALBO Phase 2: Catalog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give MALBO's products what a fashion catalog needs:
- fabric, composition, fit, care and model details;
- new / best seller flags and badges on cards and the product page;
- gallery images per color;
- collections, managed in the admin and listed on the storefront;
- a size guide per category, a product video, and a zoomable gallery.

**Architecture:**
- New columns on `items_products` and `items_product_translations`, and a `size_guide` on
  `items_category_translations`. They go through the existing product form (`ProductRequest`,
  `ProductService`, `ProductVariations`) and category form.
- A new `Collection` entity in the Items module with the usual repository, service, request,
  controller and Vue pages.
- Storefront reads stay in the Store module:
  - `ProductBadges` builds a card's and a page's badges from plain facts, so the catalog cache can
    keep holding facts only. The offer badge is decided at read time, with `PricingService`.
  - `ProductPage` adds the details, the size guide, the video and the color of each image.
- The lightbox is our own small component, loaded with `defineAsyncComponent`. No new dependency.

**Tech Stack:** Laravel 13, nwidart modules, PostgreSQL, Inertia 3 + Vue 3 + TypeScript, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-08-malbo-platform.md` (sections 2 and 5; reviews and
back-in-stock come in phase 8).

## Global Constraints

- Follow `CLAUDE.md`:
  - Writes go through services, in one `DB::transaction` when more than one table changes.
  - Reads go through repositories. Controllers stay thin (`LayeringTest`).
  - Translations are written with `Modules\Core\Support\Translations`.
  - Editor HTML goes through `Modules\Core\Support\RichText` before it is saved and when it is shown.
  - Use `jsonb`, never `json`; every foreign key is indexed; no named index on a column modifier.
  - Search with `whereLike(..., caseSensitive: false)`.
- Stock is never written here. `InventoryService` stays the only writer of `stock_quantity`.
- Items tables use the `items_` prefix. New migrations go in `Modules/Items/database/migrations`.
  They are additive only (the dev database `malbo_app` is migrated, never reset).
- Texts:
  - Admin `t()` / `__()` texts go in `Modules/Items/Lang/ar.json`.
  - Storefront texts, and the server texts the storefront shows, go in `Modules/Store/Lang/ar.json`
    (`StorefrontFrontendTest`).
- Storefront Vue:
  - It never touches browser globals in setup (`StorefrontFrontendTest`). The lightbox uses a
    native `<dialog>` and listens on its own element.
  - No `dark:` and no admin colors.
  - Logical utilities only, for RTL.
- Admin Vue: Solar icons only, through `resources/js/lib/icons.ts` and `navIcons.ts`.
- Tests extend `Tests\DatabaseTestCase` (storefront tests extend `StorefrontTestCase`). Run single
  files while working, and the whole suite once at the end.
- Do not commit.

## Review Focus

1. **A variation leaves a detail empty.** The storefront shows the parent's value; a filled
   variation value wins. Pinned in Task 2 (`test_a_variation_overrides_only_what_it_fills`).
2. **Limited quantity on a product with variants.** It uses the sum of the variants' stock. One
   unmanaged or backorder variant means the product is never "limited" nor "out of stock". Pinned in
   Task 3.
3. **Offer badge timing.** The catalog cache holds facts, never a decision: a discount or offer that
   opens or closes after caching changes the badge on the next read. Pinned in Task 3
   (`test_an_offer_badge_follows_the_clock_through_the_cache`).
4. **Gallery colors from the browser.** Media ids reach only this product's gallery; a value id must
   be a COLOR attribute's value. Pinned in Task 4.
5. **Video URLs.** Only YouTube, Vimeo or an https `.mp4`; the page embeds YouTube from
   `youtube-nocookie.com`. Pinned in Tasks 1 and 2.
6. **Size guide HTML.** Sanitized on save and on show. Pinned in Task 2.

---

### Task 1: Product details, flags and video in the admin

**Files:**
- Create: `Modules/Items/database/migrations/2026_11_08_000003_add_catalog_details_to_products.php`
- Create: `Modules/Items/Support/ProductVideo.php`
- Modify: `Modules/Items/Entities/Product.php`, `Modules/Items/Entities/ProductTranslation.php`
- Modify: `Modules/Items/Http/Requests/Admin/ProductRequest.php`
- Modify: `Modules/Items/Http/Controllers/Admin/ProductController.php` (`rowState`, index filters and rows)
- Modify: `Modules/Items/resources/js/Pages/Product/ProductFields.vue`, `parts/VariationRowFields.vue`,
  `parts/GeneralTab.vue`, `parts/fieldTabs.ts`, `parts/productForm.ts`, `parts/types.ts`, `Index.vue`
- Create: `Modules/Items/resources/js/Pages/Product/parts/DetailsTab.vue`
- Test: `tests/Feature/Items/ProductDetailsTest.php`

**Interfaces:**
- Produces:
  - `items_products`: `is_new` boolean default false, `is_best_seller` boolean default false,
    `model_height` smallint nullable, `model_weight` smallint nullable, `model_size` string(32)
    nullable, `video_url` string(500) nullable.
  - `items_product_translations`: `fabric`, `composition`, `fit`, `care`, text nullable.
  - `ProductVideo::embed(?string $url): ?array{type: 'youtube'|'vimeo'|'file', src: string}`; null
    for anything else. `ProductVideo::isValid(string $url): bool`.
  - Form fields:
    - Product and variation rows: `fabric`, `composition`, `fit`, `care`, `model_height`,
      `model_weight`, `model_size` (added to `VARIATION_KEYS`).
    - Product only: `is_new`, `is_best_seller`, `video_url`.
  - Product table filters `is_new` and `is_best_seller` (`AdminFilter::boolean`).

- [ ] **Step 1: Write the failing tests** in `ProductDetailsTest`:
  - `test_details_flags_and_video_are_saved_and_shown_on_edit`;
  - `test_a_variation_keeps_its_own_details`;
  - `test_details_are_saved_in_the_edited_locale_only`;
  - `test_video_url_must_be_youtube_vimeo_or_mp4`;
  - `test_model_numbers_are_validated`;
  - `test_the_table_filters_new_and_best_seller`.
- [ ] **Step 2: Run it.** `php artisan test tests/Feature/Items/ProductDetailsTest.php`. Expected: FAIL.
- [ ] **Step 3: Implement.**
  - The migration and the model's `$fillable`, `$casts` and `$translatedAttributes`.
  - The request rules and `BOOLEANS`.
  - `rowState` and the Vue tab "Details", with the flags in the General tab. Add the Arabic texts.
- [ ] **Step 4: Run** `ProductDetailsTest`, `ProductPagesTest` and `ProductServiceTest`. Expected: PASS.

### Task 2: Details, size guide and video on the product page

**Files:**
- Create: `Modules/Items/database/migrations/2026_11_08_000004_add_size_guide_to_categories.php`
- Modify: `Modules/Items/Entities/Category.php`, `CategoryTranslation.php`
- Modify: `Modules/Items/Http/Requests/Admin/CategoryRequest.php`, `Http/Controllers/Admin/CategoryController.php`,
  `Services/CategoryService.php`, `resources/js/Pages/Category/{CategoryFields,Add,Edit}.vue`
- Modify: `Modules/Items/Repositories/ProductRepository.php` (`findPublishedBySlug` loads what the page needs)
- Modify: `Modules/Store/Services/ProductPage.php`, `Modules/Store/Http/Controllers/Storefront/ProductController.php`
- Test: `tests/Feature/Store/ProductDetailsPageTest.php`, `tests/Feature/Items/CategorySizeGuideTest.php`

**Interfaces:**
- Produces:
  - `items_category_translations.size_guide` text nullable, translated, rich text.
  - `ProductPage::details(Product $product, ?Product $variation = null): list<array{key: string, label: string, value: string}>`.
    Keys: `fabric`, `composition`, `fit`, `care`, `model`. A variation's filled value wins over
    the parent's.
  - `ProductPage::sizeGuide(Product $product): ?array{title: string, html: string}`: the first of
    the product's categories (in their order) whose translation has one.
  - Product page props: `product.details`, `product.size_guide`, `product.video`
    (`ProductVideo::embed`), and `options.variants.*.details`.

- [ ] **Step 1: Write the failing tests:**
  - `ProductDetailsPageTest`:
    - `test_the_page_shows_the_details_section`;
    - `test_a_variation_overrides_only_what_it_fills`;
    - `test_no_details_means_an_empty_list`;
    - `test_the_video_is_sent_as_a_privacy_friendly_embed`;
    - `test_the_size_guide_comes_from_the_first_category_that_has_one`.
  - `CategorySizeGuideTest`:
    - `test_the_size_guide_is_saved_sanitized_and_shown_on_edit`.
- [ ] **Step 2: Run them.** Expected: FAIL.
- [ ] **Step 3: Implement.** Add the Arabic texts (Store module for what the storefront shows).
- [ ] **Step 4: Run** both, plus `ProductPageTest` and `CategoryPagesTest`. Expected: PASS.

### Task 3: Badges on cards and the product page

**Files:**
- Create: `Modules/Store/Support/ProductBadges.php`
- Modify: `Modules/Store/Services/PricingService.php` (`onOffer`)
- Modify: `Modules/Store/Support/ProductCard.php` (facts and `badges`)
- Modify: `Modules/Store/Services/StorefrontCatalog.php` (cards built with badges)
- Modify: `Modules/Items/Repositories/ProductRepository.php` (`cardRelations`: labels, active
  variations' stock columns, active offers' dates)
- Modify: `Modules/Store/Services/ProductPage.php`, `Http/Controllers/Storefront/ProductController.php`
- Test: `tests/Feature/Store/ProductBadgesTest.php`, `tests/Unit/Store/PricingServiceTest.php` (add)

**Interfaces:**
- Produces:
  - `ProductBadges::facts(Product $product): array`: plain values (labels, flags, stock summary,
    offer windows) that the catalog cache can hold.
  - `ProductBadges::from(array $facts, Price $price, ?CarbonInterface $at = null): list<array{key: string, label: string, tone: string, text_color?: ?string, background_color?: ?string}>`.
    - Order: labels (`label:<slug>`, tone `label`, with their colors), then `new`, `best_seller`,
      `offer`, `limited`, `out_of_stock`.
  - `PricingService::onOffer(Price $price, list<array{0: ?string, 1: ?string}> $offerWindows, ?CarbonInterface $at = null): bool`.
    It is true when the price is discounted, or an active offer on the product is open now.
  - Stock rules:
    - A product without variants is limited when its stock is managed, without backorders, and
      0 < stock ≤ `low_stock_threshold`. It is out of stock when `ProductConfigurator::inStock`
      would say so.
    - A product with variants uses its active variants:
      - out of stock when none is in stock;
      - limited when every in-stock variant is managed without backorders, and 0 < sum ≤ the
        parent's `low_stock_threshold`.
  - `ProductCard::toArray()` gains `badges`. The product page gets `badges`.

- [ ] **Step 1: Write the failing tests** in `ProductBadgesTest`:
  - `test_cards_carry_label_new_and_best_seller_badges`;
  - `test_offer_badge_follows_the_price_and_active_offers`;
  - `test_an_offer_badge_follows_the_clock_through_the_cache`;
  - `test_limited_quantity_uses_the_sum_of_the_variants`;
  - `test_out_of_stock_badges`;
  - `test_unmanaged_or_backorder_stock_has_no_stock_badge`;
  - `test_the_product_page_has_the_same_badges`;
  - `test_cards_load_no_relation_lazily`.

  In `PricingServiceTest`, add `test_on_offer`.
- [ ] **Step 2: Run them.** Expected: FAIL.
- [ ] **Step 3: Implement.** Render the badges in `ProductCard.vue` and on the product page.
- [ ] **Step 4: Run** both, plus `ShopPageTest`, `HomePageTest`, `HomeShowcaseTest`, `StorefrontReadsTest`
  and `ProductPageTest`. Expected: PASS.

### Task 4: Gallery images per color

**Files:**
- Modify: `Modules/Items/Entities/Product.php` (`addGalleryImage` returns the media it added)
- Modify: `Modules/Items/Http/Requests/Admin/ProductRequest.php`, `Services/ProductService.php` (`attachMedia`)
- Modify: `Modules/Items/Http/Controllers/Admin/ProductController.php` (gallery `color`, attribute `swatch`)
- Create: `Modules/Items/resources/js/Pages/Product/parts/GalleryColors.vue`
- Modify: `parts/MediaTab.vue`, `parts/types.ts`, `parts/productForm.ts`, `ProductFields.vue`, `Edit.vue`
- Modify: `Modules/Store/Services/ProductPage.php` (`images` with their color)
- Test: `tests/Feature/Items/GalleryColorsTest.php`

**Interfaces:**
- Produces:
  - The media custom property `attribute_value_id` (int) on `product_gallery` media.
  - Product form fields, parent only:
    - `gallery_colors`: `{media id: value id | null}`, for saved images;
    - `gallery_new_colors`: `list<value id | null>`, in the order of the new `gallery` files.
  - Edit props: `gallery_images.*.color`; `attributes.*.swatch`.
  - `ProductPage::images()` returns `list<array{url: string, color: ?int}>`.

- [ ] **Step 1: Write the failing tests** in `GalleryColorsTest`:
  - `test_a_new_gallery_image_can_be_tied_to_a_color`;
  - `test_a_saved_image_color_can_change_and_be_cleared`;
  - `test_only_a_color_attribute_value_is_accepted`;
  - `test_another_products_media_is_never_touched`;
  - `test_the_storefront_sends_each_image_with_its_color`.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Implement.** The product page shows the chosen color's images (and images without a
  color) when that color has any; otherwise every image.
- [ ] **Step 4: Run** it and `ProductPagesTest`. Expected: PASS.

### Task 5: Collections in the admin

**Files:**
- Create: `Modules/Items/database/migrations/2026_11_08_000005_create_collections_tables.php`
- Create: `Modules/Items/Entities/{Collection,CollectionTranslation}.php`, `database/factories/CollectionFactory.php`
- Create: `Modules/Items/Repositories/CollectionRepository.php`, `Repositories/Contracts/CollectionRepositoryInterface.php`
- Create: `Modules/Items/Services/CollectionService.php`, `Http/Requests/Admin/CollectionRequest.php`,
  `Http/Controllers/Admin/CollectionController.php`
- Create: `Modules/Items/resources/js/Pages/Collection/{Index,Add,Edit,CollectionFields}.vue`
- Create: `resources/js/Components/form/RemoteMultiSelect.vue`
- Modify: `Modules/Items/routes/web.php`, `Providers/RepositoryServiceProvider.php`,
  `Providers/SidebarServiceProvider.php`, `database/seeders/PermissionSeeder.php`,
  `resources/js/Components/layout/navIcons.ts`, `Modules/Items/Entities/Product.php` (`collections()`)
- Test: `tests/Feature/Items/CollectionPagesTest.php`

**Interfaces:**
- Produces:
  - Tables:
    - `items_collections`: id, slug unique, is_active bool default true, sort_order int default 0,
      created_by → users nullOnDelete indexed, timestamps.
    - `items_collection_translations`: id, collection_id → cascade indexed, locale, name,
      description text nullable, unique (collection_id, locale).
    - `items_collection_product`: collection_id → cascade, product_id → cascade, sort_order int,
      primary (collection_id, product_id), index product_id.
  - Routes `collections.index/add/store/edit/update/destroy/destroy-many/status/lookup/edit-lookup`.
    Every route has `->can('Collection View List|Create|Update|Delete')`.
  - Permissions `Collection View List|Create|Update|Delete|View|Info`, given to Super Admin.
  - `CollectionRepositoryInterface`: `tableQuery()`, `forEdit(int)`, `findActiveBySlug(string)`, `activeSlugs()`.
  - `ProductRepositoryInterface::parentOptions(string $term, list<int> $include = [], int $limit = 50)`:
    parent products by name or sku, in any letter case.
  - `CollectionService::create(array $data, string $locale, int $userId)`, `update`, `setActive`,
    `delete`, `deleteMany`. Products are synced in the given order.

- [ ] **Step 1: Write the failing tests** in `CollectionPagesTest`:
  - `test_the_list_is_shaped_for_the_table`;
  - `test_each_filter`;
  - `test_add_saves_the_translation_image_and_products_in_order`;
  - `test_edit_shows_and_saves_the_current_locale_only`;
  - `test_validation_errors`;
  - `test_the_product_lookup_finds_parents_in_any_letter_case`;
  - `test_delete_and_bulk_delete`;
  - `test_every_action_needs_its_permission`;
  - `test_super_admin_has_the_collection_permissions`.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Implement.** The sidebar item goes under Products, with key `collections` → Solar
  `layers-bold-duotone`. Add the Arabic texts.
- [ ] **Step 4: Run** it, `LayeringTest`, `TableFiltersTest`, `InertiaPagesExistTest`,
  `ArabicTextsTest` and `ForeignKeysAreIndexedTest`. Expected: PASS.

### Task 6: Collections on the storefront

**Files:**
- Create: `Modules/Store/Http/Controllers/Storefront/CollectionController.php`
- Create: `Modules/Store/resources/js/Pages/Storefront/Collection.vue`
- Modify: `Modules/Store/routes/web.php` (`store.collection`, `/{locale}/collections/{slug}`)
- Modify: `Modules/Store/Services/StorefrontShop.php`, `StorefrontCatalog.php`
- Modify: `Modules/Items/Repositories/ProductRepository.php` (`storefrontListing` filter `collection`)
- Modify: `Modules/Store/Http/Controllers/SitemapController.php`
- Modify: `resources/js/Components/storefront/ShopListing.vue` (`listUrl`, `description`)
- Test: `tests/Feature/Store/CollectionPageTest.php`

**Interfaces:**
- Consumes: `CollectionRepositoryInterface::findActiveBySlug`, `activeSlugs`.
- Produces:
  - `StorefrontShop::props(Request, ?Category = null, ?Collection = null)`.
  - The listing filter `collection` (id). Products come in the collection's order; `sort` still
    applies when chosen.
  - `ziggy.groups.storefront` already holds `store.*`.

- [ ] **Step 1: Write the failing tests** in `CollectionPageTest`:
  - `test_lists_the_collections_published_products_in_order`;
  - `test_an_unknown_or_inactive_collection_is_the_store_404`;
  - `test_search_and_sort_work_inside_a_collection`;
  - `test_the_sitemap_lists_active_collections`;
  - `test_the_route_is_in_the_storefront_route_list`.
- [ ] **Step 2: Run it.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** it, `ShopPageTest` and `StorefrontSeoTest`. Expected: PASS.

### Task 7: Gallery with video and zoom; details, size guide and badges in the page

**Files:**
- Create: `resources/js/Components/storefront/{ProductLightbox,ProductDetails,SizeGuideDialog,ProductBadges}.vue`
- Modify: `resources/js/Components/storefront/{ProductGallery,ProductCard,types}.vue|ts`
- Modify: `Modules/Store/resources/js/Pages/Storefront/ProductPage.vue`
- Modify: `Modules/Store/Lang/ar.json`

**Interfaces:**
- The lightbox:
  - It is loaded with `defineAsyncComponent`. It opens on the image that was clicked.
  - Esc closes it (native `<dialog>`). The arrow keys move the way they point; in RTL the left arrow
    is "next".
  - Double-click or double-tap toggles 2× zoom. Pinch zooms from 1× to 4×. Dragging pans while
    zoomed.
- The video is the last slide: an iframe for YouTube or Vimeo, `<video controls>` for a file.

- [ ] **Step 1: Implement.**
- [ ] **Step 2: Run** `npm run typecheck`, `StorefrontFrontendTest`, `ArabicTextsTest` and `SolarIconsTest`. Expected: PASS.

### Task 8: Whole suite, type check, dev database

- [ ] **Step 1:** `php artisan test`. Expected: all pass.
- [ ] **Step 2:** `npm run typecheck`. Expected: no errors.
- [ ] **Step 3:** `php artisan migrate --force` on the dev database (additive migrations only), then
  `php artisan db:seed --class="Modules\\Items\\database\\seeders\\PermissionSeeder" --force` so Super
  Admin gets the collection permissions.
