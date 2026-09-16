# Configuration

This page covers the essential configuration required to run a Cresenity Framework application.

---

### Server Requirements

#### PHP

- PHP >= 7.4

#### PHP Extensions

The following PHP extensions must be enabled:

- BCMath
- Ctype
- Fileinfo
- JSON (included by default since PHP 8.0)
- Mbstring
- OpenSSL
- PDO
- Tokenizer
- XML

> Most of these extensions are enabled by default in modern PHP installations. You can verify with `php -m`.

#### Web Server

- **Apache** with `mod_rewrite` enabled — the framework includes an `.htaccess` file that handles URL rewriting automatically.
- **Nginx** — requires manual rewrite configuration to route all requests to `index.php`.

---

### Directory Permissions

The following directories must be writable by the web server:

- `temp/` — session files, cache, and temporary data
- `logs/` — application log files
- `resources/` — compiled assets and generated files

```bash
chmod -R 775 temp logs resources
```

---

### Environment Variables

Each application has an `env.php` file located at `application/{app_code}/env.php`. This file returns an array of environment-specific values such as database credentials, API keys, and other secrets.

```php
<?php
return [
    'MYSQL_USER'     => 'root',
    'MYSQL_PASSWORD' => 'secret',
    'MYSQL_HOST'     => '127.0.0.1',
    'MYSQL_DATABASE' => 'myproject',
    'ENVIRONMENT'    => 'development',
];
```

These values are accessed in config files and application code using `c::env()`:

```php
c::env('MYSQL_HOST', '127.0.0.1');  // returns value, or default if not set
```

> **Important:** The `env.php` file contains sensitive credentials and should never be committed to version control. Add it to your `.gitignore`.

---

### Config Files

Configuration files are PHP files that return an array. The framework uses a layered config system — application config files override the framework defaults.

#### System Config (defaults)

Located in `system/config/`. These are the framework defaults and should not be modified directly. Key files include:

| File | Purpose |
|------|---------|
| `app.php` | Application name, locale, timezone |
| `database.php` | Database connections |
| `session.php` | Session driver and storage |
| `cache.php` | Cache driver configuration |
| `routes.php` | Default route (`_default`) |
| `core.php` | Debug mode, URL suffix |

#### Application Config (overrides)

Located in `application/{app_code}/default/config/`. Only include the keys you want to override — the framework will merge them with the system defaults.

For example, to set the application title and database, create:

**`application/myproject/default/config/app.php`**
```php
<?php
return [
    'title' => 'My Project',
];
```

**`application/myproject/default/config/database.php`**
```php
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => c::env('MYSQL_HOST', '127.0.0.1'),
            'database' => c::env('MYSQL_DATABASE'),
            'username' => c::env('MYSQL_USER'),
            'password' => c::env('MYSQL_PASSWORD'),
            'charset'  => 'utf8mb4',
        ],
    ],
];
```

#### Accessing Config Values

Use `CF::config()` or `c::config()` to read configuration values using dot notation:

```php
$appName  = c::config('app.title');               // 'My Project'
$dbDriver = c::config('database.default');         // 'mysql'
$debug    = c::config('core.debug');               // true or false
$default  = c::config('app.missing_key', 'foo');   // 'foo' (fallback)
```

---

### What Belongs in a Config File

**A config file must return static data — values that are identical for every request in the same deployment.**

Configuration is cached. On the first request the merged result is written to `temp/cache/{app_code}/`, and reused until a config file, a config directory, or the app's `env.php` changes — there is no command to run and nothing to add to a deploy, because the cache checks those modification times on every request and rebuilds itself when they move.

Safe in a config file:

- literal values
- `c::env('KEY')` — `env.php` is watched, so editing it rebuilds the cache

**Not safe. Do not put these in a config file:**

- anything derived from the current request — `$_SERVER`, `c::request()`, the visitor's IP, the session
- anything that changes each time it is evaluated — `uniqid()`, `time()`, `rand()`

Caching freezes the value, and both cases break quietly rather than loudly. A cache-buster written as `'app.js?v=' . uniqid()` stops busting anything, and editing that javascript will not change it back, because the file is not a config file and so does not invalidate the cache. A request-derived value gets computed once and then served to every other visitor.

Two guards exist, and it is worth knowing where they stop:

| Guard | Covers |
|---|---|
| Keys that differ between two consecutive loads are excluded from the cache and re-read per request | `uniqid()`, `rand()`, and anything else regenerated on each evaluation |
| The cache is keyed by domain as well as by resolved paths | a value holding `CF::domain()` — `system/config/api.php` and `websocket.php` both do this, and are never reused across domains |

**Neither guard catches a value that comes from the request but stays the same throughout it** — `c::request()->ip()`, a header, the logged-in user. Two loads inside one request agree on it, so it looks static, and it will be cached and then wrong for everyone else. Compute that in your code, not in config.

> If `temp/` is not writable the cache is simply never written — configuration still loads correctly, just without the speed-up. See Directory Permissions above.

---

### Bootstrap File

Each application can have a `bootstrap.php` file at `application/{app_code}/bootstrap.php`. This file is executed after the framework is initialized and before the request is dispatched. Use it to register service providers, set up middleware, configure pagination, or run any application-level setup.

```php
<?php
// application/myproject/bootstrap.php

CApp::instance()->setLoginRequired(true);
CPagination_Paginator::useBootstrap();
```

---

### Next Steps

- [Routing](/docs/basic/routing) — learn how URLs map to controllers
- [Controllers](/docs/basic/controller) — handle requests and build responses
