<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 19, 2018, 3:37:54 AM
 */
class CApp_Model_Users extends CApp_Model implements CAuth_AuthenticatableInterface {
    use CApp_Model_Trait_Users;

    use CAuth_Concern_AuthenticatableTrait,
        CAuth_Concern_AuthorizableTrait;
}
