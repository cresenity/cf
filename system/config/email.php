<?php
defined('SYSPATH') or die('No direct access allowed.');

return [
    'default' => c::env('MAIL_MAILER', 'mail'),
    // true: CEmail::sender()->send() (jalur lama) dikirim lewat mailer/transport di bawah,
    // bukan CEmail_Driver_* lama. Tanda tangan dan nilai baliknya tidak berubah.
    'legacy_sender_via_mailer' => c::env('MAIL_LEGACY_SENDER_VIA_MAILER', false),
    'mailers' => [
        'mail' => [
            'transport' => 'mail',
        ],
        'smtp' => [
            'transport' => 'smtp',
            'url' => c::env('MAIL_URL'),
            'host' => c::env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => c::env('MAIL_PORT', 587),
            'encryption' => c::env('MAIL_ENCRYPTION', 'tls'),
            'username' => c::env('MAIL_USERNAME'),
            'password' => c::env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => c::env('MAIL_EHLO_DOMAIN'),
        ],
        'ses' => [
            'transport' => 'ses',
            'key' => c::env('AWS_ACCESS_KEY_ID'),
            'secret' => c::env('AWS_SECRET_ACCESS_KEY'),
            'region' => c::env('AWS_DEFAULT_REGION', 'us-east-1'),
            'token' => c::env('AWS_SESSION_TOKEN'),
            // 'options' => [
            //     'ConfigurationSetName' => 'MyConfigurationSet',
            //     'EmailTags' => [
            //         ['Name' => 'foo', 'Value' => 'bar'],
            //     ],
            // ],
        ],
        'sendgrid' => [
            'transport' => 'sendgrid',
            // 'key' => c::env('SENDGRID_API_KEY'),
        ],
        'brevo' => [
            'transport' => 'brevo',
            //Kunci API, bukan SMTP key. Brevo memakai dua kredensial berbeda
            //dan yang untuk SMTP ditolak REST API-nya dengan 401.
            'key' => c::env('BREVO_API_KEY'),
        ],
        'mailgun' => [
            'transport' => 'mailgun',
            'domain' => c::env('MAILGUN_DOMAIN'),
            'secret' => c::env('MAILGUN_SECRET'),
            // 'endpoint' => c::env('MAILGUN_ENDPOINT', 'api.eu.mailgun.net'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'postmark' => [
            'transport' => 'postmark',
            'token' => c::env('POSTMARK_TOKEN'),
            'message_stream_id' => c::env('POSTMARK_MESSAGE_STREAM_ID', null),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => c::env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => c::env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
    ],
    'from' => [
        'address' => c::env('MAIL_FROM_ADDRESS', 'noreply@capp.core'),
        'name' => c::env('MAIL_FROM_NAME', 'CF App'),
    ],
    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            DOCROOT . 'system/views/cresenity/email',
        ],
    ],
];
