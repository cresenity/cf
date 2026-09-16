<?php

return [
    'default' => 'api',
    'groups' => [
        'api' => [
            'driver' => 'native',
            'session' => [
                'driver' => 'file',
                'expiration' => null,
            ],
            'error_format' => [
                'errCode' => ':code',
                'errMessage' => ':message',
                'data' => [
                    'errors' => ':errors',
                    'debug' => ':debug',
                ]
            ],
            'debug' => !CF::isProduction(),
            // Sengaja tidak diisi: CApi_Manager::router() sudah memakai
            // CF::domain() sebagai cadangan, dan config harus statis.
            'name' => 'api',
            'prefix' => null,
            'version' => 'v1',
            'subtype' => '',
            /**
             * By default the Unregistered tree (x) is used, however, should you wish
             * to you can register your type with the IANA. For more details:.
             */
            'standards_tree' => 'x',
            'default_format' => 'json',
            /**
             * The authentication providers that should be used when attempting to
             *authenticate an incoming API request.
             */
            'auth' => [
                'guard' => 'apiapp',
            ],
            'oauth' => [
                'private_key' => c::env('OAUTH_PRIVATE_KEY'),
                'public_key' => c::env('OAUTH_PUBLIC_KEY'),
                'personal_access_client' => [
                    'id' => c::env('OAUTH_PERSONAL_ACCESS_TOKEN_ID'),
                    'secret' => c::env('OAUTH_PERSONAL_ACCESS_TOKEN_SECRET'),
                ]

            ],
            /**
             * Consumers of your API can be limited to the amount of requests they can
             * make. You can create your own throttles or simply change the default
             * throttles.
             */
            'throttling' => [

            ],
            'formats' => [
                'json' => CApi_HTTP_Response_Format_JsonFormat::class,
                'jsonp' => CApi_HTTP_Response_Format_JsonFormat::class,
                'default' => CApi_HTTP_Response_Format_DefaultFormat::class,
            ],
            'formats_options' => [

                'json' => [
                    'pretty_print' => false,
                    'indent_style' => 'space',
                    'indent_size' => 2,
                ],

            ],
            'transformer' => CApi_Transformer_Adapter_FractalAdapter::class,

        ],

    ],
    'oauth' => [
        'client_uuids' => false,
        'storage' => [
            'database' => [
                'connection' => 'default',
            ],
        ],
    ],
];
