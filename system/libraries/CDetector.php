<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
class CDetector {
    /**
     * @return \CDetector_Mobile
     */
    public static function mobile() {
        return new CDetector_Mobile();
    }
}
