<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @since Jul 6, 2020 
 * @license Ittron Global Teknologi
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
