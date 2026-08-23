# Cresenity Framework (CF)

Custom PHP framework, not Laravel. PHP >= 7.4 — no PHP 8.1+ syntax (`str_contains`, `match`, `??=`, enums, readonly, union types, named args, first-class callables).

Branches: `master` (main), `development`. Docs: `application/cresenity/default/data/docs/`

## Commands

```bash
phpcf test                               # Tests (from root=framework, from app dir=app tests)
phpcf tinker --execute='...'             # REPL against real DB/models (run from the app dir)
phpcf phpstan <path>                     # PHPStan level 4 (one path per run)
php-cs-fixer fix                         # Code format
cd media/js/cres && npm run build        # JS build
```

Framework tests in `tests/`, app tests in `application/{app}/default/tests/`.

The CLI is **`phpcf`**. The `cf` file sitting in the docroot is a **0-byte legacy stub** — that
is normal and expected, not a broken install. It runs, prints nothing, and exits 0, which reads
exactly like a working command that found nothing to do; don't diagnose a dead CLI from it.

`phpcf tinker`: run from the app dir (e.g. `application/ohayomart/`). **No manual bootstrap call is needed** — `CF::appCode()` derives the app from the working directory, so `CF::loadBootstrapFiles()` includes that app's `bootstrap.php` (and whatever it calls, e.g. `DBootstrap::boot()`) during boot. Verified 2026-08-02 from three different app dirs, each resolving to its own app. Fake org context with `OH::setOrgIdResolver(fn() => $orgId)` (per-app helper name varies). Wrap anything that writes in `$db = c::db(); $db->begin(); ...; $db->rollback();` to explore/verify against live data without leaving traces — invaluable for validating a bug fix or test fixture against real framework behavior before writing it into a test file.

## Code Style

**PHP**: same-line braces, 4 spaces, single quotes, camelCase methods, C-prefixed StudlyCaps classes (CApp, CModel). PHPDoc `@var`/`@param`/`@return` required on all properties/methods.

**JS**: follow `.eslintrc`, `const`/`let` only (no `var`). **CSS**: follow `.stylelintrc`.

**Alpine.js is bundled inside `cres.js`** — `media/js/cres/src/Cresenity.js` does
`import Alpine from 'alpinejs'` (^3.9.5) and the build exposes `window.Alpine`. So Alpine
directives (`x-data`, `x-show`, …) work on **any** page that loads cres.js; do **not**
register an `alpine` asset module for them. `SupportAlpine()` in cres.js is the
Cresenity↔Alpine bridge (re-scans after each cres request, morphdom integration) — it
guards on `window.Alpine` because it runs alongside the bundle, not because Alpine is
optional. A standalone `media/js/libs/alpine.js` module also exists; it is legacy and
would double-load Alpine.

## Conventions

- **`status` is CF's soft-delete column, applied as a global scope.**
  `CModel_SoftDelete_Scope::apply()` adds `where(status, '>', 0)` to *every*
  query on a model using `CModel_SoftDelete_SoftDeleteTrait`, and `runSoftDelete()`
  sets `status = 0` — `deleted`/`deletedby` are audit stamps written alongside,
  not the thing the scope filters on. Two consequences worth knowing before
  debugging: a `status = 0` row is invisible to the model no matter what the
  code says, and **querying the same table via the `mysql` client bypasses the
  scope entirely**, so raw SQL shows rows the application can never reach. That
  gap produced a confidently-reported bug that did not exist (wapro `server`
  table, 2026-08-16: 15 rows in SQL, 3 visible to the model). Verify through
  `phpcf tinker`, not `mysql`, whenever a row's reachability is the question.
- `system/vendor/` manually managed — no composer install
- `env.php` has secrets — never commit
- `application/` folders are separate repos (gitignored except `cresenity`)
- Controller files lowercase; `return $app` from controllers (not `echo $app->render()`)
- `modules/` DEPRECATED — use `system/libraries`
- **`setDataFromArray()` and `setAjax()` are mutually exclusive on a table.** `CElement_Component_DataTable::requery()` wipes `$this->data` the moment ajax mode is on, and the ajax request then falls through to the `Query` processor with an empty query, producing invalid SQL — `select * from () as a`. It fails as a 500 on the ajax call, not on the page render, so it is easy to ship. Array-backed tables need no ajax at all: the default client-side DataTable still paginates, sorts, and searches. Confirmed 2026-08-15 from two production exceptions (#12109 smartfield, #12110 landmap). Several apps still pair the two — that combination is latent breakage, not a working pattern
- For bulk repetitive edits (e.g. converting 100+ entries), write a bash script instead of editing one-by-one to save quota/tokens
- `docs/` holds local working notes and is **gitignored**, and blocked in `.htaccess` like `CLAUDE.md` — it exists only on machines that create it

## CElement recipes

Builder methods (`addWidget`, `addForm`, `addTable`, `addTabList`, `addView`, `addField`,
`addActionList`) live on `CObservable` and its traits in `system/libraries/CObservable/Trait/`.
Grep there before guessing a name — several plausible ones do not exist, and the failure is a
fatal at render time, not at edit time.

**Widget and view**

```php
$widget = $app->addWidget()->setTitle('Judul')->setIcon('ti ti-server');
$app->addView('partials.stat-cards', ['stats' => $stats]);
```

**Form** — two names that do not exist are noted inline, both easy to reach for:

```php
$form = $widget->addForm()->setMethod('post');
$form->addField()->setLabel('Nama')->addControl('name', 'text')->setValue($value);
$form->addControl('submit_action', 'hidden')->setValue('save');   // NOT addHidden()
$form->addField()->setLabel('Secret')->addControl('secret', 'password')
    ->setValue($secret)->setReadonly()->setToggleVisibility();
$form->addActionList()->setStyle('form-action')->addAction()
    ->setLabel('Simpan')->setSubmit(true);                        // NOT $form->addAction()

$post = c::request()->post();
```

**Ajax tabs** — each tab method returns its own `CApp`:

```php
const TAB_LIST_ID = 'myTab';

$tabs = $app->addTabList(static::TAB_LIST_ID)->setTabPositionTop();
$tabs->addTab()->setLabel('Ringkasan')->setAjaxUrl($base . 'tabSummary/' . $id)->setNoPadding();
```

A form **inside** an ajax tab needs both an explicit action and a target. Without them it posts
to the parent page's URL, which after the content moved into tabs no longer handles the
submission — the button appears to work and does nothing:

```php
$form->setAjaxSubmit(true)
    ->setAction($base . 'tabRelay/' . $id)
    ->setAjaxSubmitTarget(CElement_List_TabList::ajaxContentId(static::TAB_LIST_ID));
```

The form's `action` attribute is what the ajax POST uses, and the response HTML replaces the
target container.

**Table**

```php
$table = $widget->addTable();
$table->setDataFromArray($rows);        // never together with setAjax(), see Conventions
$table->addColumn('port')->setLabel('Porta');
$table->addColumn('state')->setLabel('Jangkauan')->setCallback(function ($row, $value) {
    return $value == 'public' ? '<span class="badge badge-success">publik</span>' : $value;
});
```

**Slow data does not belong in the page render.** A controller that reads a remote server before
returning makes the whole page wait for it, and nothing on screen explains the wait. Render the
shell first and fetch the slow part over ajax with a spinner. If more than one tab needs the same
expensive read, cache it — otherwise splitting a page into tabs makes it slower than it was,
since each tab now pays for its own round trip.

## Operational notes

Anything naming a real host, vhost shape, or where protection is weak lives outside this
repo — this file is published at `github.com/cresenity/CF`. The import below is optional:
a missing one changes nothing.

@docs/CLAUDE.operasional.md
