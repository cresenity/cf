<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since Nov 30, 2020
 */
class CComponent_Exception_PropertyNotFoundException extends \Exception {
    use CComponent_Exception_BypassViewHandlerTrait;

    public function __construct($property, $component) {
        parent::__construct(
            "Property [\${$property}] not found on component: [{$component}]"
        );
    }
}
