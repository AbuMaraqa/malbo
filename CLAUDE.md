# core-view — notes for Claude

A single store: admin panel (`/{locale}/admin`) and storefront on one site and one PostgreSQL
database, on Laravel 13, nwidart modules, Inertia 3, Vue 3 + TypeScript. Rebuilt from
`C:\laragon\www\core`, which is read only. User-facing replies are in Arabic.
Design: `docs/superpowers/specs/2026-11-04-core-view-design.md` (written for the earlier
multi-tenant version: multi-tenancy has since been removed).

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
- Permissions: module `database/seeders/PermissionSeeder.php`. Sidebar: `Providers/SidebarServiceProvider.php`;
  each top entry names its heading with `->section('Sales'|'Catalog'|'Store'|'Administration')`, and its sort keeps sections together.
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

## Database and seeding

- No multi-tenancy and no Redis: cache, sessions and queue are in the database.
- `php artisan migrate` runs `database/migrations`, `database/settings` and `Modules/*/database/migrations`.
- `php artisan db:seed` runs every enabled module's `<Module>DatabaseSeeder`, Core first. Core's runs
  `AdminSeeder` (the Super Admin from `ADMIN_*` in `.env`, `config/admin.php`). A new module only
  needs that class. Safe to run again.

## Frontend

- The admin has its own design system (no template): tokens in `resources/css/admin.css`, primitives on
  Reka UI. Shell: `Layouts/AdminLayout.vue` with `Components/layout/{AppSidebar,AppHeader,AppFooter}`;
  folding sidebar (`lib/useSidebar.ts`, Ctrl+B), search dialog (`CommandSearch.vue`, Ctrl+K), notifications
  sheet, user menu with language and dark mode (`lib/useTheme.ts`). Never copy code or assets from a paid template.
- Pages use our components: `resources/js/Components/{ui,form,table,layout,dashboard}`. Button looks come from
  `lib/buttonClasses.ts`, field looks from `Components/form/controlClasses.ts`, class merging from `lib/cn.ts`.
  One component per file: when a part of a component has its own job, it moves to its own file.
- Every admin table is a `DataTable` with a non-empty `filters` list, and the controller allows those
  filters with `Modules\Core\Support\AdminFilter`. `TableFiltersTest` enforces it.
- Every `t('...')` text has an Arabic entry in a `Lang/ar.json` (`ArabicTextsTest`).
- RTL by default: logical Tailwind utilities only (`ms-`, `pe-`, `start-`, `text-start`), never `ml-`, `right-`.
- Dark mode is on in the admin: use `dark:` variants, or the `ink`, `muted`, `line`, `surface`, `card`,
  `canvas` tokens. Brand tints need a dark variant: `text-brand-600 dark:text-brand-400`, `bg-brand-50 dark:bg-brand-500/15`.
  Colors: admin `brand-*` (the panel color from the settings, default `#1570EF`), storefront `store-*` (the store's color).
  Cards: `rounded-xl border border-line bg-card shadow-card`.
- Icons: Solar only, through `resources/js/lib/icons.ts` (`SolarIconsTest`). Sidebar keys in `Components/layout/navIcons.ts`.
- Languages: add a line to `config/app.php` `locales` (native name, direction, flag) and a `Lang/<code>.json`.
- Heavy libraries (Tiptap, Leaflet, ApexCharts, Sortable) are loaded with `defineAsyncComponent`.

## Tests

- `Tests\DatabaseTestCase` on `core_view_testing` (RefreshDatabase, seeded with `DatabaseSeeder`,
  `siteUrl()` for absolute URLs). Page tests use `assertInertia`.
- The machine is RAM-constrained: run single files while working, full suites at the end.
- Frontend: `npm run test:js` (Vitest) for components with logic.
