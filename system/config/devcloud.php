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

    'inspector' => [
        /*
        |--------------------------------------------------------------------------
        | Enabling
        |--------------------------------------------------------------------------
        |
        | Setting "false" the package stop sending data to Inspector.
        |
        */

        'enable' => c::env('DEVCLOUD_INSPECTOR_ENABLE', false),

        /*
        |--------------------------------------------------------------------------
        | Remote URL
        |--------------------------------------------------------------------------
        |
        | You can set the url of the remote endpoint to send data to.
        |
        */

        'url' => c::env('DEVCLOUD_INSPECTOR_URL', 'https://devcloud.cresenity.com/inspector'),

        /*
        |--------------------------------------------------------------------------
        | Transport method
        |--------------------------------------------------------------------------
        |
        | This is where you can set the data transport method.
        | Supported options: "sync", "async"
        |
        */

        'transport' => 'async',

        /*
        |--------------------------------------------------------------------------
        | Max number of items.
        |--------------------------------------------------------------------------
        |
        | Max number of items to record in a single execution cycle.
        |
        */

        'max_items' => 100,

        /*
        |--------------------------------------------------------------------------
        | Proxy
        |--------------------------------------------------------------------------
        |
        | This is where you can set the transport option settings you'd like us to use when
        | communicating with Inspector.
        |
        */

        'options' => [
            // 'proxy' => 'https://55.88.22.11:3128',
            // 'curlPath' => '/usr/bin/curl',
        ],

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to automatically add all queries executed in the timeline.
        |
        */

        'query' => true,

        /*
        |--------------------------------------------------------------------------
        | Bindings
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to include the query bindings.
        |
        */

        'bindings' => true,

        /*
        |--------------------------------------------------------------------------
        | User
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to set the current user logged in via
        | Laravel's authentication system.
        |
        */

        'user' => true,

        /*
        |--------------------------------------------------------------------------
        | Email
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to monitor email sending.
        |
        */

        'email' => true,

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to monitor notifications.
        |
        */

        'notifications' => true,

        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to monitor background job processing.
        |
        */

        'views' => true,

        /*
        |--------------------------------------------------------------------------
        | Job
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to monitor background job processing.
        |
        */

        'job' => true,

        /*
        |--------------------------------------------------------------------------
        | Job
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to monitor background job processing.
        |
        */

        'redis' => true,

        /*
        |--------------------------------------------------------------------------
        | Exceptions
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to report unhandled exceptions.
        |
        */

        'unhandled_exceptions' => true,

        /*
        |--------------------------------------------------------------------------
        | Http Client monitoring
        |--------------------------------------------------------------------------
        |
        | Enable this if you'd like us to report the http requests done using the CF HTTP Client.
        |
        */

        'http_client' => true,
        'http_client_body' => true,

        /*
        |--------------------------------------------------------------------------
        | Hide sensible data from http requests
        |--------------------------------------------------------------------------
        |
        | List request fields that you want mask from the http payload.
        | You can specify nested fields using the dot notation: "user.password"
        */

        'hidden_parameters' => [
            'password',
            'password_confirmation'
        ],

        /*
        |--------------------------------------------------------------------------
        | Artisan command to ignore
        |--------------------------------------------------------------------------
        |
        | Add at this list all command signature that you don't want monitoring
        | in your Inspector dashboard.
        |
        */

        'ignore_commands' => [
            'storage:link',
            'optimize',
            'optimize:clear',
            'schedule:run',
            'schedule:finish',
            'package:discover',
            'vendor:publish',
            'list',
            'test',
            'migrate',
            'migrate:rollback',
            'migrate:refresh',
            'migrate:fresh',
            'migrate:reset',
            'migrate:install',
            'cache:clear',
            'config:cache',
            'config:clear',
            'route:cache',
            'route:clear',
            'view:cache',
            'view:clear',
            'queue:listen',
            'queue:work',
            'queue:restart',
            'vapor:work',
            'horizon',
            'horizon:work',
            'horizon:supervisor',
            'horizon:terminate',
            'horizon:snapshot',
            'nova:publish',
        ],

        /*
        |--------------------------------------------------------------------------
        | Web request url to ignore
        |--------------------------------------------------------------------------
        |
        | Add at this list the url schemes that you don't want monitoring
        | in your Inspector dashboard. You can also use wildcard expression (*).
        |
        */

        'ignore_url' => [
            'telescope*',
            'vendor/telescope*',
            'horizon*',
            'vendor/horizon*',
            'nova*'
        ],

        /*
        |--------------------------------------------------------------------------
        | Job classes to ignore
        |--------------------------------------------------------------------------
        |
        | Add at this list the job classes that you don't want monitoring
        | in your Inspector dashboard.
        |
        */

        'ignore_jobs' => [
            //\App\Jobs\MyJob::class
        ],
    ]
];
