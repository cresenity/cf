<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JS/browser error collector (cresjs collector)
    |--------------------------------------------------------------------------
    |
    | The browser (cresjs-error-collector, a public npm package - see
    | project/cresjs-error-collector) posts DIRECTLY to devcloud's
    | v1/jsexceptions endpoint (lowercase - CF routing is case-sensitive
    | against the controller filename, Controller_V1_Jsexceptions), cross-
    | origin - not through this app's own PHP at all. CApp's RendererTrait
    | (classic pages) and each SPA's own shell inject `url`/`key` below into
    | `window.__CF_JS_COLLECTOR_CONFIG__` for the package to read.
    |
    | `url` has no hardcoded default on purpose - the devcloud hostname must
    | never end up in a source file that ships publicly, and the npm package
    | itself never hardcodes it either (it only knows whatever endpoint it's
    | configured with at runtime).
    |
    | `key` is `app.js_ingest_key` (devcloud's Manager > Project > App page,
    | "JS Ingest Key") - a low-privilege, per-app token deliberately safe to
    | expose in browser page source (like a Sentry DSN's public key): it can
    | only be used to submit JS error reports through this one endpoint,
    | never api_key/secret_key (server-side only, used by v1/traces & co).
    */
    'jsCollector' => [
        'url' => c::env('DEVCLOUD_JS_EXCEPTION_URL'),
        'key' => c::env('DEVCLOUD_JS_INGEST_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PHP exception collector - key untuk push (URL-nya di collector.exceptionPush)
    |--------------------------------------------------------------------------
    |
    | CDebug_Collector_Exception::collect() mencoba push langsung ke URL di
    | collector.exceptionPush (system/config/collector.php - kosong/false berarti
    | push mati, langsung ke file+SSH-pull yang sudah ada) SEBELUM jatuh ke
    | penulisan file temp/collector/exception/ itu.
    |
    | `key` di sini adalah `app.php_exception_ingest_key` (devcloud's
    | Manager > Project > App page, "PHP Exception Ingest Key") - TERPISAH dari
    | `jsCollector.key` di atas: payload exception PHP bisa memuat data
    | request/session yang sensitif, jadi key ini harus tetap rahasia
    | server-side, tidak boleh disamakan dengan js_ingest_key yang memang
    | didesain aman terekspos ke browser.
    */
    'phpExceptionCollector' => [
        'key' => c::env('DEVCLOUD_PHP_EXCEPTION_INGEST_KEY'),
    ],
];
