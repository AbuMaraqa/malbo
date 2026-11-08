# Porting a page from core to core-view

For whoever moves a Livewire + Filament page of `C:\laragon\www\core` (READ ONLY, never write there)
to `C:\laragon\www\core-view`. Read `CLAUDE.md` and `docs/superpowers/specs/2026-11-04-core-view-design.md` first.

## The reference: Currency

Copy the shape of these files, they are complete and tested:

- `Modules/Core/routes/web.php` (the currencies group)
- `Modules/Core/Http/Controllers/Admin/CurrencyController.php`
- `Modules/Core/Http/Requests/Admin/CurrencyRequest.php`
- `Modules/Core/Services/CurrencyService.php`
- `Modules/Core/Repositories/CurrencyRepository.php`, `Contracts/CurrencyRepositoryInterface.php`,
  `Providers/RepositoryServiceProvider.php`
- `Modules/Core/resources/js/Pages/Currency/{Index,Add,Edit,CurrencyFields}.vue`
- `tests/Feature/Core/CurrencyPagesTest.php`

## Steps for each entity

1. Read the Livewire page(s) and its Blade view in `core/Modules/<M>/Livewire/Pages/<Entity>` and
   `core/Modules/<M>/resources/views/livewire/pages/<entity>`. Every field, column, filter, action, tab,
   conditional and validation rule must exist in the new page. Keep behaviour; do not keep bugs.
2. Routes: names as in `core` plus `.store`, `.update`, `.destroy`, `.destroy-many`. Every route has `->can(...)`
   with the permission `core` uses; check the permission exists in the module `PermissionSeeder`. Numeric params use
   `->whereNumber()`, so a bad id is a 404, not a PostgreSQL error.
3. Repository + Service for the entity (writes in the service, reads by named methods). If the entity still
   has a `booted()` hook reading `request()` for translations, remove it and use `Translations::fill`.
4. Controller: thin. Inject `*Service` and `*Repository`. Props are explicit arrays, never a whole model.
5. Filters on every table, more than `core` had: every status, type, boolean and relation column, plus a
   created date range, with `AdminFilter` on the server and a matching `TableFilter` list on the page. Sortable
   columns get `sort`. Less important columns get `toggleable: true`.
6. Vue: `defineOptions({ layout: AdminLayout })`, `PageHeader`, `DataTable`, fields from `Components/form`.
   Add and Edit share a `<Entity>Fields.vue`. Split anything with its own job into its own file under the page
   folder (for example `Pages/Product/parts/VariationsTab.vue`). Generic parts go to `resources/js/Components`.
7. Every `t('...')` key goes to `Modules/<M>/Lang/ar.json`. Keep JSON valid, keep `core`'s existing translations.
8. Sidebar: the module `SidebarServiceProvider` already lists pages; items whose route is missing are hidden.
9. Tests in `tests/Feature/<M>/<Entity>PagesTest.php`: list, each filter, search in another letter case, add,
   validation error, edit updates the same row, delete, permission denied. Port the matching `core/tests` behaviour
   (Livewire assertions become HTTP + `assertInertia`). Copy any domain test the page relies on.

## Available components

- `ui/`: `Button` (variant primary|secondary|ghost|danger, size sm|md|icon, href), `Badge` (tone), `Modal`, `Tabs`,
  `PageHeader`, `Card`, and `confirm()` from `@/lib/confirm`.
- `form/`: `TextInput`, `TextareaInput`, `SelectInput`, `MultiSelect` (searchable; `single` for one value), `Toggle`,
  `ImageUpload` (`v-model` new files, `v-model:removed` ids of saved images), `RichEditor`, `MapPicker`, `FormActions`, `Field`.
- `table/DataTable`: slots `cell-<key>`, `actions`, `bulk`, `empty`. Filters: select, boolean, text, date-range, number-range.
- `dashboard/ApexChart`, `@/tailadmin/dashboard/MetricCard`, and `@/tailadmin/ui/*` for anything else (dialog, dropdown-menu, select, checkbox, radio-group, alert, avatar).
- Forms with files: `form.post(url, { forceFormData: true })`; for update use `_method: 'put'` in the data.
- Drag and drop: `sortablejs` directly, imported dynamically inside `onMounted`.

## Lessons from the first modules

- Spatie query builder 7 takes variadic arguments: `->allowedFields(...$fields)`, `->allowedSorts(...$sorts)`. Arrays are a TypeError.
- `AdminFilter::exact` on an integer column errors on `filter[x]=abc`: use `Modules\Items\Support\IntegerFilter::make('status')`.
- Sorting by a translated name: `Modules\Items\Support\TranslatedSort`.
- `validated()` builds nested lists in rule order, so call `ksort($rows)` before using positions.
- Check every foreign key `onDelete`: deleting a parent must never silently delete orders, branches or staff. Refuse with an error toast, or null the key.
- Lazy loading is logged in `storage/logs/laravel.log`: eager-load what a page reads, including `translations` on nested relations.
- Brand tints need a dark variant: `text-brand-600 dark:text-brand-400`, `bg-brand-50 dark:bg-brand-500/15`.
- Uploads: entity `addImage` methods swallow errors and return null. Assert the media exists in tests.
- `Components/form` also has `Repeater` (`<Repeater v-model="form.values" name="values" :errors="form.errors" :new-item="() => ({})">`, slot `#item="{ item, index, error }"`), `TreeSelect` and `ColorInput`. Read the comment at the top of each file.
- `Modules/Items/resources/js/Pages/parts/RecordActions.vue` is the row actions component to copy from.


## Icons

Solar only: `import { Pencil, Trash2 } from '@/lib/icons'`. A missing icon is added to that file as `~icons/solar/<name>-linear`.

## PostgreSQL traps

- `where('x', 'like', ...)` is case-sensitive: use `whereLike($col, $term)`, which is not.
- Compare booleans with true/false. `whereIn('id', [...])` with a non-numeric value is an error: cast or validate.
- `orderBy` on a translated column needs a join or subquery on the translations table.
- `groupBy` must list every selected column. Named indexes are forbidden on new migrations.

## Commands

The machine is shared with other workers and short on RAM. Use YOUR OWN test database from your brief:

```
DB_DATABASE=core_view_<x>_testing TENANCY_DB_PREFIX=test_<x>_ CACHE_PREFIX=test_<x>_ php -d memory_limit=512M vendor/bin/phpunit tests/Feature/<M>/<File>.php
npx vue-tsc --noEmit
```

- Run one test file at a time. Do not run `npm run build`, `vite build`, the dev server or `docker`: the coordinator does that.
- PHPUnit prints one JSON line: read `result` and `failures`.
- Bash heredocs break on apostrophes in this environment: write files with the Write tool.
- The git index is shared: commit with explicit paths, `git add <paths>` then `git commit -m "feat(<module>): ..." -- <paths>`, so you never sweep up another worker's staged files; ending the message with the line
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. If git reports a lock, wait a moment and retry. Never reset, stash or rebase: others share this tree.
- Do not edit files outside your scope. If a shared component needs a change, make the smallest additive change and say so in your report.
