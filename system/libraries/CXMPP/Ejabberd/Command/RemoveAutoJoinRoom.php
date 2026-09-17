<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since Jul 3, 2020
 */
class CXMPP_Ejabberd_Command_RemoveAutoJoinRoom extends CXMPP_Ejabberd_Command_PrivateSet {
    public function __construct($user, $host, $room, $service, $nick = '') {
        $room = strtolower($room);
        $roomJid = $room . '@' . $service;
        $element = sprintf("<storage xmlns='storage:bookmarks'><conference autojoin='false' jid='%s' name='%s'><nick>%s</nick></conference></storage>", $roomJid, $room, $nick);
        parent::__construct($user, $host, $element);
    }
}
