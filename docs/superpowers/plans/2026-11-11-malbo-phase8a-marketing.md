# MALBO Phase 8A: Marketing (tracking, feed, abandoned carts, back in stock, reviews)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Strict TDD: each named
> test is run and seen failing before the code that makes it pass.

**Goal:** spec sections 5, 9, 18, 21 (phase 8, first half):
- Meta Pixel in the browser and the Conversions API on the server, the same events with the same
  `event_id` (deduplicated by Meta), only after the visitor accepts marketing cookies;
- a product catalog feed for Facebook and Instagram (RSS 2.0 `g:` and CSV);
- abandoned carts with their live value, reminders by email or SMS at set delays, a signed link that
  restores the cart, opt-out;
- "notify me when available" on the product page, sent once the variant can be ordered again;
- reviews from verified buyers with photos, moderated in the admin, on the product page and in its
  JSON-LD.

**Architecture:** additive, in the Store module, except one event in Items:
- `Items\Events\ProductsBecameOrderable` (product ids), dispatched by `InventoryService::apply` after
  the commit when a row goes from not orderable to orderable (`InventoryService::orderable()`, the
  same rule as `ProductConfigurator::available`). InventoryService stays the only stock writer.
- Store owns the tables (`store_cart_reminders`, `store_marketing_opt_outs`, `store_stock_alerts`,
  `store_reviews`, four columns on `store_carts`), the settings (`MarketingSettings`, group
  `store_marketing`), the services, the admin pages and the storefront Vue.
- Delivery never blocks the buyer: CAPI posts and staff alerts run after the response (`defer`),
  stock alerts after the commit and the response, cart reminders from a scheduled command. Every
  outbound call catches and logs its own failure.

**Rules this phase fixes (document, test):**
- *Consent* is the `store_consent` cookie (encrypted, set only by `POST /{locale}/consent`), values
  `granted` | `denied`. No cookie = undecided = no tracking (the banner shows). The Pixel loads and
  CAPI runs only with `granted`, only on storefront pages (`HandleInertiaRequests::section() ===
  'storefront'`), never admin or wholesale.
- *Events*: PageView (every storefront page visit), ViewContent (product page: the default variant's
  SKU, else the parent's; its displayed price), AddToCart (the line added: SKU, quantity, price),
  InitiateCheckout (checkout page: SKUs, pieces, subtotal), Purchase (done page: the order's SKUs,
  pieces, total). Currency ILS. Values are `PricingService` prices or the order's own totals, never
  `wholesale_price` or `cost_price`. A page reached by a redirect right after a form post (the session
  carries the post's flash) does not count a new visit: its PageView/ViewContent/InitiateCheckout are
  not built again; AddToCart travels to that page in the flash. Purchase's id is stable per order
  (`order.<number>`) and its CAPI post is sent once (cache `add`).
- *Hashing* (Meta's rules): email trimmed + lowercased; phone = digits with country code, no `+` or
  leading zero (`Phone::normalize`, then `970` for national numbers starting 59/56, else `972`);
  external_id = the account id. SHA-256 hex. IP, user agent, `_fbp`, `_fbc` sent as they are.
- *Abandoned cart*: has items, not converted, last activity ≥ `abandoned_after_minutes` ago, and a
  contact the buyer agreed to be reminded at (`contact_consent_at`): the email/phone typed at checkout,
  else the account's. Value = the lines priced now (`CartService::lines`).
- *Reminders*: reminder *n* (1-based, at most 2) is due at `last_activity_at + reminder_delays[n-1]`
  minutes and not later than 48 hours after that (stale carts are not mailed when the feature is
  switched on or the scheduler was down). Email when the cart has an email, else SMS. One row per
  (cart, sequence) in `store_cart_reminders` (unique): the row is claimed before sending, so an
  overlapping run never sends twice; the run also holds a cache lock. A converted cart, an opted-out
  contact or a cart without consent gets nothing. *Recovered* = converted and had a reminder sent.
- *Restore link*: temporary signed URL (14 days) with the cart's uuid; opens only an open cart; a
  cart of an account opens only for that account (others are sent to sign in); sets the cart cookie.
- *Opt-out*: `store_marketing_opt_outs` (channel `email` | `phone`, normalised value, unique); a signed
  link in every reminder and stock alert; GET shows a page, POST records it. Opt-outs stop cart
  reminders and stock alerts.
- *Stock alert*: one waiting alert per (variant, email) and per (variant, phone) (partial unique
  indexes); only for a sellable item that cannot be ordered now. On `ProductsBecameOrderable`, waiting
  alerts of those products are claimed one by one (`notified_at` set where null) and sent in chunks of
  100, after the response, only while the item is still orderable and visible.
- *Review eligibility*: a signed-in account with a **B2C** order in status DELIVERED holding the
  product or one of its variants. B2B (wholesale) orders do not count. One review per (product, user):
  posting again replaces it and sends it back to pending. Plain text only, escaped by Vue.

## Global Constraints

- `CLAUDE.md`: services write (`DB::transaction` across tables), repositories read, thin controllers
  (`LayeringTest`), DataTable + `AdminFilter`, `->can()` on every admin route, PermissionSeeder,
  sidebar `->section('Store')`, Solar icons, logical utilities, dark mode in admin, PostgreSQL rules,
  `Lang/ar.json` (+ `he.json` for storefront texts).
- Storefront components touch no browser global outside `onMounted` (`StorefrontFrontendTest`): the
  Pixel and consent code that needs `window` lives in `resources/js/lib/metaPixel.ts`.
- No wholesale or cost price in a tracking payload, the feed or a review/alert page.
- No real HTTP, mail or SMS in tests (`Http::fake`, `Mail::fake`, `FakeSmsGateway`).
- Additive migrations only. Tests on `malbo_phase8_testing`. No commits.

## Review Focus

1. **Consent and channel gating.** Nothing loads or posts without `granted`, and never on wholesale.
   Pinned in Task 2 (`test_nothing_is_sent_without_consent`, `test_nothing_is_sent_from_wholesale_pages`).
2. **Dedupe ids.** The id in the page prop is the id posted to CAPI. Pinned in Task 2.
3. **Double reminders.** Unique (cart, sequence) claimed before sending + lock. Pinned in Task 5
   (`test_an_overlapping_run_sends_nothing_twice`).
4. **Restore link security.** Pinned in Task 5.
5. **Stock alert transition.** Exactly once per transition, nothing while unavailable. Pinned in Task 7.
6. **Review XSS / eligibility.** Pinned in Task 8.

---

### Task 1: Schema and settings

**Files:** `Modules/Store/database/migrations/2026_11_11_300001_create_marketing_tables.php`
(`store_carts` + `contact_email`, `contact_phone`, `contact_consent_at`, `locale`; `store_cart_reminders`;
`store_marketing_opt_outs`; `store_stock_alerts` + partial unique indexes; `store_reviews`);
`database/settings/2026_11_11_000001_create_store_marketing_settings.php`;
`Store/Settings/MarketingSettings.php` (`meta_capi_token` encrypted); `config/settings.php`;
`Store/config/config.php` (`meta.graph_version`, `sms.driver`).

- [x] `tests/Feature/Store/MarketingSchemaTest.php`: `test_the_new_tables_and_columns`,
  `test_the_capi_token_is_encrypted_at_rest`.

### Task 2: Meta Pixel and Conversions API

**Files:** `Store/Services/Marketing/{MetaEvents,MetaConversionsApi,MetaUserData}.php`,
`Store/Support/MarketingConsent.php`; `StorefrontLayout::props(array $meta, ?Closure $events)` adds
the `marketing` prop; `ConsentController` (`POST /{locale}/consent`, `store.consent`);
`ProductController`, `CartController::add`, `CheckoutController::{show,done}`.

**Interfaces:**
- `marketing` prop: `null` (nothing configured or not a storefront page) or
  `{consent: 'granted'|'denied'|null, pixel_id: ?string, events: list<{name, id, data}>}`;
  `pixel_id` and `events` only with consent and the Pixel on.
- CAPI: `POST https://graph.facebook.com/v{config store.meta.graph_version}/{pixel_id}/events`,
  body `{data: [{event_name, event_time, event_id, event_source_url, action_source: 'website',
  user_data: {em?, ph?, external_id?, client_ip_address, client_user_agent, fbp?, fbc?}, custom_data:
  {currency, value, content_ids, content_type, contents?, num_items?}}], test_event_code?, access_token}`,
  `Http::timeout(5)->retry(2, 200)`, after the response, failures logged.

- [x] `tests/Feature/Store/MetaTrackingTest.php`: `test_the_capi_payload_shape`, `test_user_data_is_hashed`,
  `test_page_and_capi_share_the_event_ids`, `test_add_to_cart_and_checkout_events`,
  `test_purchase_is_sent_once_per_order`, `test_nothing_is_sent_without_consent`,
  `test_nothing_is_sent_when_disabled`, `test_nothing_is_sent_from_wholesale_pages`,
  `test_no_wholesale_or_cost_price_in_any_payload`, `test_a_failed_post_is_logged_not_thrown`,
  `test_the_consent_choice_is_a_cookie`.
- [x] `resources/js/lib/__tests__/metaPixel.spec.ts` and `resources/js/Components/storefront/__tests__/ConsentBanner.spec.ts`.

### Task 3: Marketing settings page

**Files:** `Store/Http/Controllers/Admin/MarketingSettingsController.php`, `Requests/Admin/MarketingSettingsRequest.php`,
`Services/MarketingSettingsService.php`, `Pages/Marketing/Index.vue`, routes `marketing.index|update`
(`Marketing Settings Update`), sidebar group "Marketing" (section Store), PermissionSeeder.

- [x] `tests/Feature/Store/MarketingSettingsPageTest.php`: `test_staff_need_the_permission`,
  `test_the_token_never_reaches_the_browser`, `test_saving_keeps_or_clears_the_token`, `test_delays_are_validated`.

### Task 4: Catalog feed

**Files:** `Store/Services/Marketing/CatalogFeed.php`, `Http/Controllers/CatalogFeedController.php`,
`resources/views/feeds/meta-catalog.blade.php`, routes `GET /feeds/meta-catalog.xml|.csv`;
`ProductRepository::forCatalogFeed()` (+ contract); `StorefrontCatalogCache::remember(..., ttl)`.

- [x] `tests/Feature/Store/CatalogFeedTest.php`: `test_one_item_per_sellable_variant_or_single_product`,
  `test_item_fields`, `test_sale_price_and_its_dates`, `test_hidden_unpublished_and_inactive_are_left_out`,
  `test_the_csv_variant`, `test_the_token_is_required_when_set`, `test_it_is_cached_until_the_catalog_changes`,
  `test_no_wholesale_or_cost_price`.

### Task 5: Abandoned carts and reminders

**Files:** `Store/Contracts/SmsGateway.php`, `Support/LogSmsGateway.php`, `Entities/{CartReminder,MarketingOptOut}.php`,
`Repositories/{AbandonedCartRepository,OptOutRepository}.php` (+ contracts), `Services/{AbandonedCartService,CartReminderSender,OptOutService}.php`,
`Mail/CartReminderMail.php`, `resources/views/mail/cart-reminder.blade.php`, `Console/SendCartReminders.php`
(`store:send-cart-reminders`, every 10 minutes, `withoutOverlapping`), controllers `CartContactController`
(`POST /{locale}/cart/contact`, throttled), `CartRestoreController` (signed), `UnsubscribeController` (signed GET/POST).

- [x] `tests/Feature/Store/AbandonedCartTest.php`: `test_contact_is_saved_only_with_consent`,
  `test_unchecking_withdraws_consent`, `test_a_cart_is_abandoned_after_the_set_minutes`,
  `test_reminders_follow_the_delays`, `test_a_late_reminder_is_skipped`, `test_no_reminder_without_consent`,
  `test_a_converted_cart_gets_no_reminder_and_counts_as_recovered`, `test_an_overlapping_run_sends_nothing_twice`,
  `test_sms_when_there_is_no_email`, `test_the_mail_is_in_the_carts_language`, `test_unsubscribe_stops_reminders`,
  `test_the_restore_link_opens_this_cart_only`, `test_a_restore_link_never_opens_another_accounts_cart`,
  `test_a_tampered_restore_link_is_refused`.

### Task 6: Abandoned carts in the admin

**Files:** `Http/Controllers/Admin/AbandonedCartController.php` (`index`, `show`), `Pages/AbandonedCart/{Index,Show}.vue`,
permission `Abandoned Cart View List`.

- [x] `tests/Feature/Store/AbandonedCartPagesTest.php`: `test_the_list_shows_value_contact_and_reminders`,
  `test_filters`, `test_the_detail_page`, `test_staff_need_the_permission`.

### Task 7: Back in stock

**Files:** `Items/Events/ProductsBecameOrderable.php`, `InventoryService` (`orderable()`, dispatch);
`Store/Entities/StockAlert.php`, `Repositories/StockAlertRepository.php`, `Services/StockAlertService.php`,
`Listeners/SendStockAlerts.php`, `Mail/BackInStockMail.php`, `Http/Controllers/Storefront/StockAlertController.php`
(`POST /{locale}/stock-alerts`, throttle + honeypot), `Admin/StockAlertController.php`, `Pages/StockAlert/Index.vue`,
`Components/storefront/NotifyMeForm.vue`, permission `Stock Alert View List`.

- [x] `tests/Feature/Store/StockAlertTest.php`: `test_a_buyer_subscribes_for_an_unavailable_variant`,
  `test_a_duplicate_keeps_one_waiting_alert`, `test_an_orderable_item_takes_no_alert`, `test_the_honeypot`,
  `test_the_transition_notifies_exactly_once`, `test_nothing_is_sent_while_still_unavailable`,
  `test_an_opted_out_contact_gets_nothing`, `test_sms_alerts`, `test_the_admin_list_groups_by_product`.
- [x] `tests/Feature/Items/OrderableEventTest.php`: `test_the_event_fires_on_the_transition_only`,
  `test_the_rule_matches_the_storefront`.
- [x] `resources/js/Components/storefront/__tests__/NotifyMeForm.spec.ts`.

### Task 8: Reviews

**Files:** `Store/Entities/Review.php` (medialibrary `photos`), `Enums/ReviewStatus.php`, `Repositories/ReviewRepository.php`,
`Services/{ReviewService,ProductReviews}.php`, `Notifications/NewReviewAlert.php`, `Core/Services/ImageService::reencode()`,
`Http/Requests/ReviewRequest.php`, `Http/Controllers/Storefront/ReviewController.php` (store, account index),
`Admin/ReviewController.php` (index, edit, status, reply, destroyPhoto), `Pages/Review/{Index,Edit}.vue`,
`Pages/Storefront/Account/Reviews.vue`, `Components/storefront/{ProductReviews,ReviewForm,StarRating}.vue`,
`ProductPage::structuredData` (`aggregateRating`), permissions `Review View List | Update`.

- [x] `tests/Feature/Store/ReviewTest.php`: `test_only_a_buyer_with_a_delivered_order_can_review`,
  `test_wholesale_orders_do_not_count`, `test_one_review_per_product_and_user`, `test_only_approved_reviews_show`,
  `test_the_summary_is_cached_and_refreshed_on_moderation`, `test_json_ld_has_the_aggregate_rating`,
  `test_text_is_plain_and_escaped`, `test_photos_are_checked_by_content`, `test_photos_are_reencoded_without_exif`,
  `test_staff_are_told_after_the_response`, `test_my_reviews`.
- [x] `tests/Feature/Store/ReviewPagesTest.php`: `test_the_moderation_list_and_filters`, `test_approve_reject_reply_and_delete_a_photo`,
  `test_staff_need_the_permissions`.
- [x] `resources/js/Components/storefront/__tests__/ReviewForm.spec.ts`.

### Task 9: Finish

- [x] Whole suite once, `npx vue-tsc --noEmit`, `npm run test:js`; migrate `malbo_app`; run the Store
  PermissionSeeder there.

### Deploy notes

- The scheduler must run (`php artisan schedule:run` every minute): cart reminders every 10 minutes.
- Admin → Marketing → settings: Pixel id, CAPI token (Events Manager → Settings → Conversions API),
  optional test event code while testing, the feed token, reminder delays.
- Feed: `https://<host>/feeds/meta-catalog.xml?token=<token>` (CSV: `.csv`) in Commerce Manager → Data sources.
- SMS: only the `log` driver exists (`STORE_SMS_DRIVER=log`); a provider binds `SmsGateway`.

---

## As built (where it differs from or adds to the tasks above)

- **Opt-out table** is `store_marketing_opt_outs` (the module's table prefix), not `marketing_opt_outs`.
  An opt-out of either contact of a cart (email or phone) stops all its reminders.
- **Cart reminders are off by default** (`cart_reminders_enabled`), like the Pixel and CAPI: staff
  switch them on after checking the message. Consent is asked of everyone, signed-in buyers too
  (their account's email/phone are used only once they tick the box).
- **Restore link** lasts 14 days; a cart restored counts as activity (its reminder clock restarts).
  A guest with another account's link is sent to sign in; another signed-in account gets a 403.
- **Stock alerts** go by email when the subscriber left one, else SMS. A failed send puts the alert
  back to waiting. Only `InventoryService` moves raise the event: an admin switching an unmanaged
  product's status in the product form does not (no stock write).
- **Reviews**: new photos replace the old ones; a resubmission without photos keeps them. The reply
  is cleared when the buyer edits. `verified` is always true on the storefront (only verified
  buyers can write). Reviews of B2B (wholesale) orders are refused.
- **Phone hashing for Meta**: national numbers starting 59/56 get 970, the rest 972; a number typed
  with 970/972 keeps it.
- **Feed** item ids fall back to `P<id>` when a SKU is empty (same in the Pixel events); variant
  links carry `?selected[...]` so the page opens on that variant; `product_type` is the first category.
- **Browser globals**: the Pixel's `window`/`document` code is `resources/js/lib/metaPixel.ts`,
  called from `useMetaPixel` inside `onMounted`; storefront components touch none.
- **Vitest**: `resources/js/test/setup.ts` gained a recording `useForm` stub, `router.post/reload`
  and the new routes.

## Review fix pass (supersedes the rules above where they differ)

- **SMS**: `SmsGateway::enabled()`; the log driver is off. Without a provider a phone-only cart gets
  no reminder (no row, never "recovered") and a phone-only stock alert stays waiting (not claimed).
  The log line shows the number's last 3 digits and the text's length only, never its links. Every
  SMS (reminder or stock alert) ends with the signed opt-out link.
- **Abuse caps**: one cart reminder a day per recipient (normalised email or phone) across all carts,
  checked at claim time under the run's lock (a second reminder due at 24 h waits until a day has
  passed since the first). At most 10 waiting stock alerts per email or phone; the per-IP throttle
  stays. No double opt-in.
- **Sign-in round trips**: `back` is a path (`getRequestUri()`, `route(..., false).'#reviews'`), the
  only kind the sign-in page keeps.
- **Signed links** are signed as paths (`absolute: false`, routes `signed:relative`), sent as `url()`
  of that path: they hold behind another host or a proxy. Restore links still expire after 14 days.
- **Back in stock** runs as a queued job (`Store\Jobs\SendStockAlerts`, database queue). The scheduler
  drains the queue every minute: `queue:work --stop-when-empty --max-time=50`, `withoutOverlapping`
  (`routes/console.php`; there was none before).
- **Pixel**: `fbq.disablePushState = true` before the script, `fbq('set', 'autoConfig', false, id)`
  before `init` (Meta requires it before init), so only server-made events fire.
- **Consent withdrawal**: footer "Cookie settings" reopens the banner; declining after consenting
  expires `_fbp`/`_fbc` (server, and the page on the host and each parent domain) and reloads in full.
- **CAPI**: one call per request with all its events (`MetaEvents` is scoped), after the response,
  connect 2 s, total 4 s, one retry on network errors or 5xx. `event_source_url` has no query.
  The unsubscribe and restore routes build no events at all.
- **Reviews**: photos above 6000×6000 are refused (`dimensions` rule, and `ImageService::reencode`
  checks `getimagesize()` before decoding); `reencode` no longer leaves its `tempnam()` file; a
  first review racing another is retried as an update; the rating summary is cleared when a review
  is deleted, and before an account is deleted for each product it reviewed.
- **Feed cache**: its own key (`store.feed.meta-catalog`), 1 hour, built under a lock; thrown away
  when a product is created, deleted, restored, or its status/active/slug/SKU/parent changes, never
  on a sale or a price edit.
- **Restore while signed in**: a guest cart joins the account as on sign-in (`CartService::adoptCart`,
  merged into the account's open cart or given to the account); never served unowned.

### Deploy notes (additions)

- The scheduler now also runs the queue worker each minute: `QUEUE_CONNECTION=database` (as in
  `.env.example`). No separate worker process is needed; one may still be run instead.
- SMS stays off until a provider binds `SmsGateway` with `enabled()` true.
