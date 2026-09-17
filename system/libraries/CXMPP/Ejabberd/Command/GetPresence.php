<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since Jul 6, 2020
 */
class CXMPP_Ejabberd_Command_GetPresence extends CXMPP_Ejabberd_CommandAbstract {
    private $user;

    private $server;

    public function __construct($user, $server) {
        $this->user = $user;
        $this->server = $server;
    }

    public function getCommandName() {
        return 'get_presence';
    }

    public function getCommandData() {
        return [
            'user' => $this->user,
            'server' => $this->server
        ];
    }
}
