<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @since Jul 6, 2020 
 * @license Ittron Global Teknologi
 */
class CXMPP_Ejabberd_Command_GetRoomAffiliation extends CXMPP_Ejabberd_CommandAbstract {

    private $name;
    private $service;
    private $user;

    public function __construct($user, $name, $service) {
        $this->name = $name;
        $this->service = $service;
        $this->user = $user;
    }

    public function getCommandName() {
        return 'get_room_affiliation';
    }

    public function getCommandData() {
        return [
            'name' => $this->name,
            'service' => $this->service,
            'jid' => $this->user
        ];
    }

}
