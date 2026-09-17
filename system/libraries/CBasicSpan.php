<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.2
 */
class CBasicSpan extends CElement_Element_Span {
    public static function factory($id = '') {
        return new CBasicSpan($id);
    }
}
