<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
class CXMPP_Ejabberd_Command_DestroyRoom extends CXMPP_Ejabberd_CommandAbstract {
    private $name;

    private $service;

    public function __construct($name, $service) {
        $this->name = $name;
        $this->service = $service;
    }

    public function getCommandName() {
        return 'destroy_room';
    }

    public function getCommandData() {
        /*
          {
          "name": "room1",
          "service": "muc.example.com"
          }
         */

        $data = [
            'name' => $this->name,
            'service' => $this->service,
        ];

        return $data;
    }
}
