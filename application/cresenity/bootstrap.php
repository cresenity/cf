<?php

CF::setLocale('id_ID');
c::router()->get('robots.txt', function () {
    $robots = CHTTP::robotsTxt();
    $robots->addDisallow('/');

    return $robots->toResponse();
});
