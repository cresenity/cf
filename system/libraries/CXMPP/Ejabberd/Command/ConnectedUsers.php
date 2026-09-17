<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
class CXMPP_Ejabberd_Command_ConnectedUsers extends CXMPP_Ejabberd_CommandAbstract {
    /**
     * @var bool
     */
    private $full_info;

    public function __construct($full_info = false) {
        $this->full_info = $full_info;
    }

    public function getCommandName() {
        return $this->full_info ? 'connected_users_info' : 'connected_users';
    }

    public function getCommandData() {
        return [];
    }
}
