# core-view — notes for Claude

Multi-tenant store platform (stancl/tenancy, one PostgreSQL database per store, store = subdomain)
on Laravel 13, nwidart modules, Inertia 3, Vue 3 + TypeScript. Rebuilt from `C:\laragon\www\core`,
which is read only. User-facing replies are in Arabic.
Design: `docs/superpowers/specs/2026-11-04-core-view-design.md`.

## Module structure

- Admin pages: `Modules/<M>/Http/Controllers/Admin/<Entity>Controller.php` (`index, add, store, edit,
  update, destroy`), Vue `Modules/<M>/resources/js/Pages/<Entity>/{Index,Add,Edit}.vue` (separate Add
  and Edit). Validation in `Http/Requests/Admin`. Props are explicit arrays or resources.
- Storefront (Store only): `Http/Controllers/Storefront/<Name>Controller.php`, route names `store.*`.
- `routes/web.php`: `auth,verified` then `{locale}` then `admin` + `staff`; names
  `<plural>.index/add/store/edit/update/destroy`; `->can('<Entity> View List|Create|Update|Delete')`
  on every route. Public routes stay outside `auth`.
- Models: `newFactory()`, traits, constructor `setTable(config('<module>.table_prefix').'<table>')`,
  `$fillable`, `$casts`, `$translatedAttributes`, `getActivitylogOptions()`, `createdBy()`, `booted()`.
- Permissions: module `database/seeders/PermissionSeeder.php`. Sidebar: `Providers/SidebarServiceProvider.php`.
  Texts: `Lang/ar.json`, English keys, read in Vue with `t('Key')`.

## Layering

- Writes go through the Service, in one `DB::transaction` when more than one table changes.
- Reads go through named repository methods; pages may also use `query()` for tables and lookups.
- Translations are explicit (`Modules\Core\Support\Translations`, a locale per call).
- Controllers stay thin. `tests/Unit/Architecture/LayeringTest.php` enforces it.
- HTML from the editor goes through `Modules\Core\Support\RichText` before it is saved or shown.

## PostgreSQL

- No named index on a column modifier: names are unique per schema. Foreign keys get an index
  (`ForeignKeysAreIndexedTest`). Use `jsonb`, not `json`.
- Search with `whereLike(..., caseSensitive: false)`, never `where(..., 'like', ...)`.
- Booleans are compared with `true/false`. A failed query aborts the open transaction.

## Tenancy

- Never call `Cache::flush()`: Redis flushes every store. Each store has its own cache prefix.
- Central migrations in `database/migrations`, store migrations in `database/migrations/tenant`,
  `database/settings` and `Modules/*/database/migrations`. `tenants:seed --class=<Module>DatabaseSeeder`
  for existing stores; never re-run Core's seeder there.

## Frontend

- The admin is built on TailAdmin Vue, free edition (MIT): `resources/js/tailadmin` is its component
  library, left as it is; keep its LICENSE and `THIRD_PARTY_NOTICES.md`. Never copy from the paid Pro edition.
- Pages use our components, which wrap it: `resources/js/Components/{ui,form,table,layout,dashboard}`.
  One component per file: when a part of a component has its own job, it moves to its own file.
- Every admin table is a `DataTable` with a non-empty `filters` list, and the controller allows those
  filters with `Modules\Core\Support\AdminFilter`. `TableFiltersTest` enforces it.
- Every `t('...')` text has an Arabic entry in a `Lang/ar.json` (`ArabicTextsTest`).
- RTL by default: logical Tailwind utilities only (`ms-`, `pe-`, `start-`, `text-start`), never `ml-`, `right-`.
- Dark mode is on in the admin: use `dark:` variants as TailAdmin does, or the `ink`, `muted`, `line`,
  `surface`, `card` tokens. Brand tints need a dark variant: `text-brand-600 dark:text-brand-400`, `bg-brand-50 dark:bg-brand-500/15`. Colors: admin `brand-*` (TailAdmin), storefront `store-*` (the color of each store).
- Icons: Solar only, through `resources/js/lib/icons.ts` (`SolarIconsTest`). Sidebar keys in `Components/layout/navIcons.ts`.
- Languages: add a line to `config/app.php` `locales` (native name, direction, flag) and a `Lang/<code>.json`.
- Heavy libraries (Tiptap, Leaflet, ApexCharts, Sortable) are loaded with `defineAsyncComponent`.

## Tests

- `Tests\TenantTestCase` (store `testing`) or `Tests\CentralTestCase`, on `core_view_central_testing`
  and `test_tenant_*` only. Page tests use `assertInertia`.
- The machine is RAM-constrained: run single files while working, full suites at the end.
