<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jul 6, 2020
 */
class CXMPP_Ejabberd_Command_SetRoomAffiliation extends CXMPP_Ejabberd_CommandAbstract {
    private $name;

    private $service;

    private $user;

    private $affiliation;

    public function __construct($user, $name, $service, $affiliation = 'member') {
        $this->name = $name;
        $this->service = $service;
        $this->user = $user;
        $this->affiliation = $affiliation;
    }

    public function getCommandName() {
        return 'set_room_affiliation';
    }

    public function getCommandData() {
        return [
            'name' => $this->name,
            'service' => $this->service,
            'jid' => $this->user,
            'affiliation' => $this->affiliation,
        ];
    }
}
