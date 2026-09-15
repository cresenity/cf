<?php

defined('SYSPATH') or die('No direct access allowed.');

// Uji coba APM di app kedua (setelah devcloud sendiri) - mengirim trace ke
// endpoint OTLP devcloud, lihat application/devcloud/default/controllers/v1/traces.php.
// Mati secara default; dinyalakan eksplisit lewat env.php per server.
return [
    'enabled' => c::env('APM_ENABLED', false),
    'service_name' => c::env('APM_SERVICE_NAME', 'cresenity-framework-docs'),
    'environment' => c::env('APM_ENVIRONMENT', c::env('ENVIRONMENT', 'development')),
    'otlp_endpoint' => c::env('APM_OTLP_ENDPOINT', 'https://devcloud.cresenity.com/v1/traces'),
    'api_key' => c::env('APM_API_KEY'),
];
