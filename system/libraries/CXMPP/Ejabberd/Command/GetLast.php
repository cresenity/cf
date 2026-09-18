<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jul 6, 2020
 */
class CXMPP_Ejabberd_Command_GetLast extends CXMPP_Ejabberd_CommandAbstract {
    private $user;

    private $host;

    public function __construct($user, $host) {
        $this->user = $user;
        $this->host = $host;
    }

    public function getCommandName() {
        return 'get_last';
    }

    public function getCommandData() {
        return [
            'user' => $this->user,
            'host' => $this->host
        ];
    }
}
