# Bootstrap

The `bootstrap.php` file is the main entry point for application-level setup. It is executed after the framework core is initialized but before the request is dispatched. Use it to configure application behavior, register routes, set themes, schedule cron jobs, and more.

Each application has its own bootstrap file at `application/{app_code}/bootstrap.php`.

---

### Execution Order

The framework loads bootstrap files in the following order:

1. `system/bootstrap.php` — framework-level defaults (pagination, exception handling, Blade registration, etc.)
2. `modules/{module}/bootstrap.php` — module-level setup (if any modules are loaded)
3. `application/{app_code}/bootstrap.php` — your application setup
4. `application/{app_code}/{org_code}/bootstrap.php` — organization-specific overrides (if org_code is set)

Your application bootstrap is loaded last, so it can override any framework or module defaults.

---

### Application Settings

Configure common application behavior using `c::app()`:

```php
<?php
// Require login for all pages (default: false)
c::app()->setLoginRequired(true);

// Set the default view layout name
c::app()->setViewName('page');

// Get the currently authenticated user
$user = c::app()->user();
```

---

### Theme

Set the application theme. Themes control the HTML layout, CSS, and JavaScript assets:

```php
<?php
// Set theme directly
c::manager()->theme()->setTheme('my-theme');

// Or using CApp shorthand
CApp::setTheme('my-theme');

// Set theme dynamically based on conditions
CManager::theme()->setThemeCallback(function ($theme) {
    return CF::isProduction() ? 'production-theme' : 'dev-theme';
});
```

---

### Locale

Set the application locale for translations and formatting:

```php
<?php
CF::setLocale('id_ID');
```

---

### Custom Routes

Register explicit routes that take priority over the auto-discovery system. See [Routing](/docs/basic/routing) for full details.

```php
<?php
// Serve robots.txt dynamically
c::router()->get('robots.txt', function () {
    return CHTTP::robotsTxt()
        ->addUserAgent('*')
        ->addDisallow(CF::isProduction() ? '/admin' : '/')
        ->toResponse();
});

// Serve sitemap.xml
c::router()->get('sitemap.xml', function () {
    $sitemap = CHTTP::sitemap();
    $sitemap->addUrl('/')
        ->setChangeFrequency(CHTTP_Sitemap_Tag_UrlTag::CHANGE_FREQUENCY_WEEKLY);
    $sitemap->addUrl('/about')
        ->setChangeFrequency(CHTTP_Sitemap_Tag_UrlTag::CHANGE_FREQUENCY_YEARLY);

    return $sitemap->toResponse();
});
```

---

### Cron Jobs

Schedule background tasks using the cron manager:

```php
<?php
c::cron()->job(MyTaskQueue_DailyReport::class)
    ->cron('0 8 * * *')
    ->name('DailyReport');

c::cron()->job(MyTaskQueue_DataSync::class)
    ->cron('* * * * *')
    ->name('DataSync');
```

---

### PWA and Notifications

Enable Progressive Web App support and push notifications:

```php
<?php
// Enable PWA for a specific section
c::app()->enablePWA('admin');

// Enable push notifications for a section
$user = c::app()->user();
if ($user) {
    if (cstr::startsWith(c::request()->path(), 'admin')) {
        c::app()->notification()->enable('admin');
    }
}
```

---

### HTTPS Redirect

Force HTTPS in production:

```php
<?php
if (!CF::isCli()) {
    if (CF::isProduction() && c::request()->getScheme() !== 'https') {
        curl::redirect('https://' . CF::domain() . '/' . curl::current(true));
    }
}
```

---

### Exception Handling

Customize how exceptions are reported and rendered:

```php
<?php
// Send exception emails in production
CException::exceptionHandler()->reportable(function (Exception $e) {
    CApp::sendExceptionEmail($e);
});

// Collect exceptions for the debug bar
CException::exceptionHandler()->reportable(function (Exception $e) {
    CDebug::collector()->collectException($e);
});
```

---

### Full Example

A typical `bootstrap.php` combining several features:

```php
<?php
// Application settings
c::app()->setLoginRequired(false);
c::app()->setViewName('page');
c::manager()->theme()->setTheme('my-theme');

// Locale
CF::setLocale('id_ID');

// HTTPS redirect in production
if (!CF::isCli() && CF::isProduction()) {
    if (c::request()->getScheme() !== 'https') {
        curl::redirect('https://' . CF::domain() . '/' . curl::current(true));
    }
}

// robots.txt
c::router()->get('robots.txt', function () {
    return CHTTP::robotsTxt()
        ->addUserAgent('*')
        ->addDisallow(CF::isProduction() ? '/admin' : '/')
        ->toResponse();
});

// PWA
c::app()->enablePWA('app');

// Notifications
$user = c::app()->user();
if ($user) {
    c::app()->notification()->enable('app');
}
```
