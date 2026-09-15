<?php

// Vendor Composer devcloud-apm-php-agent dipasang manual (tanpa composer
// install di server) - lihat default/config/apm.php. Mati secara default,
// tidak berpengaruh apa pun kalau APM_ENABLED tidak diset true di env.php.
if (CF::config('apm.enabled') && !CF::isCli()) {
    require_once __DIR__ . '/default/vendor-apm/agent/vendor/autoload.php';
    \Cresenity\DevCloud\APM\Agent::start([
        'enabled' => true,
        'serviceName' => CF::config('apm.service_name'),
        'environment' => CF::config('apm.environment'),
        'otlpEndpoint' => CF::config('apm.otlp_endpoint'),
        'apiKey' => CF::config('apm.api_key'),
    ]);
}

CF::setLocale('id_ID');
CApp::component()->registerComponent('counter', \Cresenity\Component\Counter::class);

CApp::component()->registerComponent('member-table', \Cresenity\Testing\MemberTableComponent::class);
CApp::component()->registerComponent('test-validate', \Cresenity\Testing\ValidateTestComponent::class);
CApp::component()->registerComponent('test-upload', \Cresenity\Testing\UploadTestComponent::class);
c::router()->get('robots.txt', function () {
    $robots = CHTTP::robotsTxt();
    $robots->addDisallow('/');

    return $robots->toResponse();
});
