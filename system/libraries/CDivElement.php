<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * CDivElement
 *
 * @deprecated dont use this anymore
 */
class CDivElement extends CElement_Element_Div {
    public static function factory($id = '') {
        return new CDivElement($id);
    }
}
