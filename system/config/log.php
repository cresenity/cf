<?php
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => c::env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => c::env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, CF uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'level' => c::env('LOG_LEVEL', CF::isProduction() ? 'info' : 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'level' => c::env('LOG_LEVEL', CF::isProduction() ? 'info' : 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => c::env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'CF Log',
            'emoji' => ':boom:',
            'level' => c::env('LOG_LEVEL', 'critical'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => c::env('LOG_LEVEL', CF::isProduction() ? 'info' : 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => c::env('PAPERTRAIL_URL'),
                'port' => c::env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => c::env('LOG_LEVEL', CF::isProduction() ? 'info' : 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => c::env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],
        'stdout' => [
            'driver' => 'single',
            'path' => 'php://stdout',
            'level' => c::env('LOG_LEVEL', 'info'),
        ],
        'syslog' => [
            'driver' => 'syslog',
            'level' => c::env('LOG_LEVEL', CF::isProduction() ? 'info' : 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => c::env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => DOCROOT . 'logs/cf.log',
        ],
    ],
    'reader' => [
        /*
        |--------------------------------------------------------------------------
        | Include file patterns
        |--------------------------------------------------------------------------
        |
        */

        'include_files' => [
            '*.log',
            '**/*.log',
            // '/absolute/paths/supported',
        ],
        /*
        |--------------------------------------------------------------------------
        | Exclude file patterns.
        |--------------------------------------------------------------------------
        | This will take precedence over included files.
        |
        */

        'exclude_files' => [
            // 'my_secret.log'
        ],
        /*
        |--------------------------------------------------------------------------
        |  Shorter stack trace filters.
        |--------------------------------------------------------------------------
        | Lines containing any of these strings will be excluded from the full log.
        | This setting is only active when the function is enabled via the user interface.
        |
        */

        'shorter_stack_trace_excludes' => [
            '/system/vendor/Symfony/',
            '/system/vendor/Illuminate/',
            '/system/vendor/DebugBar/',
        ],

        'patterns' => [

            'log_matching_regex' => '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?(\d{6}([\+-]\d\d:\d\d)?)?)\].*/',

            /**
             * This pattern, used for processing Laravel logs, returns these results:
             * $matches[0] - the full log line being tested.
             * $matches[1] - full timestamp between the square brackets (includes microseconds and timezone offset)
             * $matches[2] - timestamp microseconds, if available
             * $matches[3] - timestamp timezone offset, if available
             * $matches[4] - contents between timestamp and the severity level
             * $matches[5] - environment (local, production, etc)
             * $matches[6] - log severity (info, debug, error, etc)
             * $matches[7] - the log text, the rest of the text.
             */
            'log_parsing_regex' => '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}\.?(\d{6}([\+-]\d\d:\d\d)?)?)\](.*?(\w+)\.|.*?)('
                . implode('|', array_filter(CLogger_Level::caseValues()))
                . ')?: (.*?)( in [\/].*?:[0-9]+)?$/is',

        ],
        /*
        |--------------------------------------------------------------------------
        | Chunk size when scanning log files lazily
        |--------------------------------------------------------------------------
        | The size in MB of files to scan before updating the progress bar when searching across all files.
        |
        */

        'lazy_scan_chunk_size_in_mb' => 50,
    ],
];
