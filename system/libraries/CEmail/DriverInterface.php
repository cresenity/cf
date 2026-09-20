<?php

/**
 * @deprecated 1.9 kontrak driver lama; implementasi baru adalah transport Symfony (lihat CEmail_Transport_*)
 */
interface CEmail_DriverInterface {
    public function send(array $to, $subject, $body, $options = []);
}
