# core-view — design

Date: 2026-11-04. Source project: `C:\laragon\www\core` (read only, never modified).

## 1. Intent

Rebuild `core`, a multi-tenant store platform, on Vue + Inertia + PostgreSQL as a separate
project. The owner wants it faster, with stronger UI options, and a solid base to build on. There
are no real stores or data to migrate. The owner delegated the technical choices; every decision
below is Claude's call.

## 2. Goals

1. Feature parity with `core` as it stands: platform (landing, sign-up, platform admin), store
   admin (Core, Items, GeoLocation, Branch, Store modules), storefront, REST API.
2. Keep the owner's layout (nwidart modules, repositories + services, separate Add and Edit pages)
   so the code can still be continued by hand.
3. Storefront keeps all its SEO: server-rendered HTML, canonical, hreflang, OpenGraph, JSON-LD,
   sitemap, robots.
4. Measured speed: a budget per page type, not an assumption (§8).

## 3. Non-goals

- Cart, checkout, buyer accounts, payment gateways: they do not exist in `core` today. The
  storefront keeps "Order on WhatsApp"; the structure leaves room for them.
- Data migration from MySQL (no real data).
- `stores:import-existing` (only existed to import the old `core` database).
- Octane: tenancy on long-lived workers is not proven here. php-fpm + OPcache + JIT, cached
  config/routes/events. Code stays Octane-friendly (no static request state).

## 4. Anti-goals

- Any data leak between stores (database, cache, session, files, permissions).
- Changing a single file in `core`.
- A UI that looks template/AI-made: no gradients, emoji, decorative icons, huge radii or
  shadows on everything (visual direction carried over from `core`'s specs).
- Prices, discounts or totals trusted from the browser.
- Porting `core`'s known holes (§7).

## 5. Stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.4, Laravel 13, stancl/tenancy 3 (one PostgreSQL database per store), nwidart modules 13 |
| Frontend | Vue 3.5 `<script setup>` + TypeScript, Inertia 3, Vite 8, Tailwind 4, Reka UI primitives |
| SSR | Inertia SSR for storefront and platform landing only; admin renders client-side |
| Data | PostgreSQL 18, Redis 8 (cache, sessions, queue) via predis; local services in `docker-compose.yml` on ports 5433 and 6380 |
| Rich text / map / sort | Tiptap, Leaflet, SortableJS, all lazy-loaded |
| Tests | PHPUnit on PostgreSQL, vue-tsc, Vite build; browser checks on key flows |

## 6. Architecture

### 6.1 Backend layout

Same module shape as `core`, with Livewire pages replaced by controllers:

```
Modules/<M>/Http/Controllers/Admin/<Entity>Controller.php    index/add/store/edit/update/destroy
Modules/<M>/Http/Controllers/Storefront/<Name>Controller.php Store only, route names store.*
Modules/<M>/Http/Requests/Admin/<Entity>Request.php          validation (Filament's rules move here)
Modules/<M>/Http/Resources/Admin/<Entity>…Resource.php       props sent to Vue, explicit fields only
Modules/<M>/Repositories, Services, Entities, Enums          ported from core
Modules/<M>/resources/js/Pages/<Entity>/{Index,Add,Edit}.vue
```

Route names and `{locale}` URLs stay as in `core` (`products.index`, `/ar/admin/products`).
`->can('<Entity> View List|Create|Update')` on every admin route; delete and bulk routes get theirs too.

### 6.2 Frontend layout

- `resources/js/app.ts` (single entry) and `resources/js/ssr.ts`. Pages resolved with
  `import.meta.glob` over `resources/js/Pages` and `Modules/*/resources/js/Pages`, one chunk per
  page, so the storefront never downloads admin code.
- `resources/js/Components/ui` design system; `resources/js/Components/form` fields bound to
  Inertia's `useForm`; `resources/js/Components/table` the server-driven `DataTable`.
- `resources/js/Layouts`: `AdminLayout`, `StorefrontLayout`, `PlatformLayout`, `GuestLayout`.
- Translations: module `Lang/*.json` merged and cached server-side, sent once with Inertia `once`
  props, read with a `t()` helper. English keys as in `core`.
- RTL: `<html dir>` per locale; Tailwind logical properties only (`ms-`, `pe-`, `start-`).

### 6.3 Tables

`DataTable` is driven by the URL: `?filter[..]=&sort=&page=&perPage=`, handled by
`spatie/laravel-query-builder` on the repository's `tableQuery()`. A `Modules\Core\Support\AdminTable`
helper gives every Index the same paginated shape. Inline edits (status, active toggle) and bulk
actions are separate routes that go through the service.

### 6.4 Tenancy

Copied from `core` and adapted: `InitializeTenancyIfTenantHost`, `EnsureStoreIsActive`,
`ScopeSessionToStore`, `EnsureStaff`, provisioning pipeline with rollback, filesystem suffix per
store, impersonation tokens.

Changes:

- Connections `central` and `tenant` become pgsql; manager `PostgreSQLDatabaseManager`.
- Cache is Redis with one key prefix per store (`RedisCacheTenancyBootstrapper`): no tag round trips.
  Sessions in Redis under one fixed prefix, bound to their store by `ScopeSessionToStore`. Queue in Redis.
- spatie permission cache key and settings reset per store stay exactly as in `core`.

### 6.5 PostgreSQL

- Migrations copied, then: explicit index names dropped (they must be unique per schema in
  PostgreSQL), `json` becomes `jsonb`, wirechat and telescope migrations dropped.
- PostgreSQL does not index foreign keys on its own, unlike MySQL. A final tenant migration indexes
  every unindexed foreign key from the catalog; a test keeps the count at zero.
- Search is case-insensitive through `whereLike(..., caseSensitive: false)`, backed by `pg_trgm`
  GIN indexes on translated names.
- Booleans compared as booleans, never `1`/`0`.

### 6.6 Storefront rendering and SEO

SSR through `<Head>`: title, description, canonical, hreflang, OpenGraph, JSON-LD. The store's theme
CSS (`ThemeService`) and Google Fonts link go in the root Blade view, so there is no flash of
unstyled content and no rebuild per store. Catalog reads are cached per store in Redis and
invalidated on write.

## 6.7 Admin look (owner decision, 2026-11-04)

The owner asked for the look of TailAdmin Vue (https://tailadmin.com/vue). The free edition is MIT, so its
component library is used as it is, with its license kept. The paid Pro edition is not copied. Owner
requirements: every component in its own file, filters on every table. Admin colors are the TailAdmin
palette with light and dark mode; the storefront keeps the color and font of each store. This replaces
the visual direction in §4 for the admin only.

## 7. Dropped on purpose

| From core | Why |
|---|---|
| Wirechat, Filament, Livewire, Jetstream, Alpine, jQuery | Replaced by the new stack; Wirechat has no Vue version and no sidebar link in `core` |
| Keycloak, Socialite, FFmpeg, spatie/backup, laravel-data, eloquent-spatial, geographical, Excel service | Unused in `core`'s code |
| mcamara/laravel-localization | A small `SetRouteLocale` middleware does what `core` used it for |
| l5-swagger, `api.json`, `.scribe` | `api.json` holds another project's paths; API docs regenerated separately |
| `/test-realtime`, `app/Livewire/Test.php`, `test_translation*.php`, PPU privacy text, `ppu_keycloak_token`, unreachable sidebar links, `TransactionLoggerService`, `email_logs` | Leftovers |
| Module selector / packages page | Disables modules process-wide from a web request: unsafe on a multi-store platform |

### API fixes (not ported as they were)

- Every staff endpoint gets the same permission as its admin page: today any logged-in token can
  list, edit or delete users, read `sync/users`, list all orders and change order status.
- `RegisterRequest` references `UserRole::STUDENT` and `COMPANY_SUPERVISOR`, which do not exist;
  `UserController` calls a missing `studentProfile()`.
- Pricing, order and coupon totals computed on the server only.

## 8. Performance budget

Measured at the end on a production build with seeded data (500 products, one store):

- Storefront home and product page: server response under 200 ms, under 25 queries, JS entry under
  90 KB gzip for the first page.
- Admin table page: JSON response under 150 ms, no N+1 (`Model::preventLazyLoading` outside production).
- Reported as measured numbers against `core` on the same machine. Failing a budget is reported,
  not hidden.

## 9. Testing

`core`'s test suite is the behaviour contract. It is copied to `tests/` and adapted: Livewire
assertions become HTTP + `assertInertia`. Coverage kept: tenant isolation, routing, sign-up,
provisioning and rollback, theme, pricing, repositories, layering guard. New: API authorization for
every staff endpoint, unindexed-FK guard, SSR output contains SEO tags.

## 10. Left to the builder

Component breakdown, copy, exact icon set, test fixtures.
