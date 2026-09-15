<?php

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
