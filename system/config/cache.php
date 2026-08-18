<?php

defined('SYSPATH') or die('No direct access allowed.');

return [
    'default' => 'file',
    'stores' => [
        'apc' => [
            'driver' => 'apc',
        ],
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
        ],
        'file' => [
            'driver' => 'file',
            'engine' => 'temp',
            'options' => [
                'directory' => CF::appCode()
            ],
        ],
        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => c::env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                c::env('MEMCACHED_USERNAME'),
                c::env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => c::env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => c::env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
        ],
        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => c::env('AWS_ACCESS_KEY_ID'),
            'secret' => c::env('AWS_SECRET_ACCESS_KEY'),
            'region' => c::env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => c::env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => c::env('DYNAMODB_ENDPOINT'),
        ],
    ],
    'prefix' => c::env('CACHE_PREFIX', cstr::slug(CF::appCode(), '_') . '_cache'),
    /**
     * Cache store should be used on rate limiter, leave null to use default store.
     * Behind a load balancer this must point at a shared store, otherwise each
     * server counts attempts on its own and the effective limit multiplies.
     */
    'limiter' => c::env('CACHE_LIMITER_DRIVER'),
];
