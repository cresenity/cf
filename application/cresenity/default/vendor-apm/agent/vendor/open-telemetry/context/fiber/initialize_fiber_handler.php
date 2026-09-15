<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\Context;

if (ZendObserverFiber::isEnabled()) {
    ZendObserverFiber::init();
}
