# Deprecated helpers

The helper classes listed below are deprecated. They still exist and still work, since
existing code depends on them, but they should not be used in new code.

## Replacement map

| Deprecated | Since | Use instead |
|---|---|---|
| `ccfg` | 1.6 | `CF::config()` |
| `cdownload` | 1.6 | `c::response()->download()` |
| `cfs` | 1.6 | `CFile` |
| `clang` | 1.6 | `c::__()` |
| `clog` | 1.6 | `c::log()` |
| `cmail` | 1.6 | `CEmail` |
| `cmailapi` | 1.6 | `CEmail` |
| `cnav` | 1.8 | `CApp_Navigation_Helper` |
| `cobj` | 1.6 | `c::get()` |
| `cphp` | 1.2 | `CFile` |
| `crequest` | 1.6 | `c::request()` |
| `crole` | 1.2 | `c::app()->role()` |
| `crouter` | 1.8 | `CRouting_Router` / `c::router()` |
| `csess` | 1.2 | `c::session()` |
| `ctemp` | 1.2 | `CTemporary` |
| `ctransform` | 1.6 | `c::transform()` |
| `cuser` | 1.2 | `c::app()->user()` |
| `cvalid` | 1.6 | `CValidation` |
| `CManager::lang()` / `CManager_Lang` | 1.9 | `CTranslation` (pindahkan file `default/lang/` dulu; `c::__` tidak membacanya) |

## Common equivalents

```php
// configuration
ccfg::get('app.name');              c::config('app.name');

// HTTP request
crequest::method();                 c::request()->method();
crequest::remoteAddress();          c::request()->ip();
crequest::is_ajax();                c::request()->ajax();

// session
csess::get('user');                 c::session()->get('user');
csess::set('user', $user);          c::session()->put('user', $user);

// files
cfs::file_exists($path);            CFile::exists($path);
cfs::mkdir($dir);                   CFile::makeDirectory($dir);

// translation
clang::__('Save');                  c::__('Save');

// logging
clog::error('failed');              c::log()->error('failed');

// objects
cobj::get($obj, 'name');            c::get($obj, 'name');

// routing
crouter::controller();              c::router()->controller();
crouter::current_uri();             c::request()->path();
```

## Current helpers

The following helpers are not deprecated:

| Helper | Purpose |
|---|---|
| [`c`](/docs/helper/c) | main facade, the entry point to most of the framework |
| [`carr`](/docs/helper/carr) | array utilities with dot notation |
| [`cstr`](/docs/helper/cstr) | string utilities |
| [`curl`](/docs/helper/curl) | URL builder |
| [`cdbg`](/docs/helper/cdbg) | dumping, traces, and debugging output |
| [`cdbutils`](/docs/helper/cdbutils) | short queries and database schema reads |
| [`chtml`](/docs/helper/chtml) | HTML tag builder |
| [`cmsg`](/docs/helper/cmsg) | flash messages |
| [`cnum`](/docs/helper/cnum) | locale-aware number formatting |
| [`cstatic`](/docs/helper/cstatic) | fixed lists: months, countries, HTTP status codes |
| [`cupload`](/docs/helper/cupload) | uploaded file handling |
| [`cutils`](/docs/helper/cutils) | dates, time differences, and number formatting |

## Migrating

Replacing a deprecated call is a line-level change. When you modify code that uses one of
these classes, update the lines you touch rather than converting the entire file, so the
change under review stays readable.
