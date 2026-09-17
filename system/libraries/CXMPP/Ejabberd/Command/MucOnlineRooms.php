<?php

/**
 * @deprecated 1.8
 */
class CXMPP_Ejabberd_Command_MucOnlineRooms extends CXMPP_Ejabberd_CommandAbstract {
    private $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function getCommandName() {
        return 'muc_online_rooms';
    }

    public function getCommandData() {
        return [
            'service' => $this->service,
        ];
    }
}
