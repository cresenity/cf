<?php

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since May 31, 2020
 */
class CXMPP_Ejabberd_Command_GetRoomOccupants extends CXMPP_Ejabberd_CommandAbstract {
    private $name;

    private $service;

    public function __construct($name, $service) {
        $this->name = $name;
        $this->service = $service;
    }

    public function getCommandName() {
        return 'get_room_occupants';
    }

    public function getCommandData() {
        return [
            'name' => $this->name,
            'service' => $this->service
        ];
    }
}
