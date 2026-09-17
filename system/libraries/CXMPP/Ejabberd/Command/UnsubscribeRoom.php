<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since Jul 3, 2020
 */
class CXMPP_Ejabberd_Command_UnsubscribeRoom extends CXMPP_Ejabberd_CommandAbstract {
    private $user;

    private $room;

    public function __construct($user, $room) {
        $this->user = $user;
        $this->room = $room;
    }

    public function getCommandName() {
        return 'unsubscribe_room';
    }

    public function getCommandData() {
        return [
            'user' => $this->user,
            'room' => $this->room,
        ];
    }
}
