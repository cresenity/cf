<?php

return [
    'enable' => false,
    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    |
    | You can configure the dashboard settings from here.
    |
    */

    'dashboard' => [

        'port' => 6001,

        'domain' => null,

        'path' => 'cwebsocket',

        'middleware' => [
            'web',
            //\BeyondCode\LaravelWebSockets\Dashboard\Http\Middleware\Authorize::class,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Applications Repository
    |--------------------------------------------------------------------------
    |
    | By default, the only allowed app is the one you define with
    | your PUSHER_* variables from .env.
    | You can configure to use multiple apps if you need to, or use
    | a custom App Manager that will handle the apps from a database, per se.
    |
    | You can apply multiple settings, like the maximum capacity, enable
    | client-to-client messages or statistics.
    |
    */

    'apps' => [
        // 'default' => [
        //     'id' => c::env('PUSHER_APP_ID'),
        //     'name' => c::env('APP_NAME'),
        //     'host' => c::env('PUSHER_APP_HOST'),
        //     'key' => c::env('PUSHER_APP_KEY'),
        //     'secret' => c::env('PUSHER_APP_SECRET'),
        //     'path' => c::env('PUSHER_APP_PATH'),
        //     'capacity' => null,
        //     'enable_client_messages' => false,
        //     'enable_statistics' => true,
        //     'allowed_origins' => [
        //         CF::domain()
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Replication PubSub
    |--------------------------------------------------------------------------
    |
    | You can enable replication to publish and subscribe to
    | messages across the driver.
    |
    | By default, it is set to 'local', but you can configure it to use drivers
    | like Redis to ensure connection between multiple instances of
    | WebSocket servers. Just set the driver to 'redis' to enable the PubSub using Redis.
    |
    */

    'replication' => [

        'mode' => 'local',

        'modes' => [

            /*
            |--------------------------------------------------------------------------
            | Local Replication
            |--------------------------------------------------------------------------
            |
            | Local replication is actually a null replicator, meaning that it
            | is the default behaviour of storing the connections into an array.
            |
            */

            'local' => [

                /*
                |--------------------------------------------------------------------------
                | Channel Manager
                |--------------------------------------------------------------------------
                |
                | The channel manager is responsible for storing, tracking and retrieving
                | the channels as long as their members and connections.
                |
                */

                'channel_manager' => \CWebSocket_ChannelManager_LocalChannelManager::class,

                /*
                |--------------------------------------------------------------------------
                | Statistics Collector
                |--------------------------------------------------------------------------
                |
                | The Statistics Collector will, by default, handle the incoming statistics,
                | storing them until they will become dumped into another database, usually
                | a MySQL database or a time-series database.
                |
                */

                'collector' => \CWebSocket_Statistic_Collector_MemoryCollector::class,

            ],

            'redis' => [

                'connection' => c::env('WEBSOCKETS_REDIS_REPLICATION_CONNECTION', 'default'),

                /*
                |--------------------------------------------------------------------------
                | Channel Manager
                |--------------------------------------------------------------------------
                |
                | The channel manager is responsible for storing, tracking and retrieving
                | the channels as long as their members and connections.
                |
                */

                'channel_manager' => \CWebSocket_ChannelManager_RedisChannelManager::class,

                /*
                |--------------------------------------------------------------------------
                | Statistics Collector
                |--------------------------------------------------------------------------
                |
                | The Statistics Collector will, by default, handle the incoming statistics,
                | storing them until they will become dumped into another database, usually
                | a MySQL database or a time-series database.
                |
                */

                'collector' => \CWebSocket_Statistic_Collector_RedisCollector::class,

            ],

        ],

    ],

    'statistics' => [

        /*
        |--------------------------------------------------------------------------
        | Statistics Store
        |--------------------------------------------------------------------------
        |
        | The Statistics Store is the place where all the temporary stats will
        | be dumped. This is a much reliable store and will be used to display
        | graphs or handle it later on your app.
        |
        */

        'store' => \CWebSocket_Statistic_Store_DatabaseStore::class,

        /*
        |--------------------------------------------------------------------------
        | Statistics Interval Period
        |--------------------------------------------------------------------------
        |
        | Here you can specify the interval in seconds at which
        | statistics should be logged.
        |
        */

        'interval_in_seconds' => 60,

        /*
        |--------------------------------------------------------------------------
        | Statistics Deletion Period
        |--------------------------------------------------------------------------
        |
        | When the clean-command is executed, all recorded statistics older than
        | the number of days specified here will be deleted.
        |
        */

        'delete_statistics_older_than_days' => 60,

    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Request Size
    |--------------------------------------------------------------------------
    |
    | The maximum request size in kilobytes that is allowed for
    | an incoming WebSocket request.
    |
    */

    'max_request_size_in_kb' => 250,

    /*
    |--------------------------------------------------------------------------
    | SSL Configuration
    |--------------------------------------------------------------------------
    |
    | By default, the configuration allows only on HTTP. For SSL, you need
    | to set up the the certificate, the key, and optionally, the passphrase
    | for the private key.
    | You will need to restart the server for the settings to take place.
    |
    */

    'ssl' => [

        'local_cert' => c::env('CF_WEBSOCKETS_SSL_LOCAL_CERT', null),

        'capath' => c::env('CF_WEBSOCKETS_SSL_CA', null),

        'local_pk' => c::env('CF_WEBSOCKETS_SSL_LOCAL_PK', null),

        'passphrase' => c::env('CF_WEBSOCKETS_SSL_PASSPHRASE', null),

        'verify_peer' => CF::isProduction(),

        'allow_self_signed' => !CF::isProduction(),

    ],

    /*
    |--------------------------------------------------------------------------
    | Route Handlers
    |--------------------------------------------------------------------------
    |
    | Here you can specify the route handlers that will take over
    | the incoming/outgoing websocket connections. You can extend the
    | original class and implement your own logic, alongside
    | with the existing logic.
    |
    */

    'handlers' => [

        'websocket' => \CWebSocket_Handler_WebSocketHandler::class,

        'health' => \CWebSocket_Handler_HealthHandler::class,

        'trigger_event' => \CWebSocket_Handler_ApiHandler_TriggerEvent::class,

        'fetch_channels' => \CWebSocket_Handler_ApiHandler_FetchChannels::class,

        'fetch_channel' => \CWebSocket_Handler_ApiHandler_FetchChannel::class,

        'fetch_users' => \CWebSocket_Handler_ApiHandler_FetchUsers::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Promise Resolver
    |--------------------------------------------------------------------------
    |
    | The promise resolver is a class that takes a input value and is
    | able to make sure the PHP code runs async by using ->then(). You can
    | use your own Promise Resolver. This is usually changed when you want to
    | intercept values by the promises throughout the app, like in testing
    | to switch from async to sync.
    |
    */

    'promise_resolver' => \React\Promise\FulfilledPromise::class,

];
