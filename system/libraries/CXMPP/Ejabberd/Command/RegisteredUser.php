<?php

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since May 30, 2020
 */
class CXMPP_Ejabberd_Command_RegisteredUser extends CXMPP_Ejabberd_CommandAbstract {
    /**
     * @var string
     */
    private $host;

    public function __construct($host = null) {
        $this->host = $host;
    }

    public function getCommandName() {
        return 'registered_users';
    }

    public function getCommandData() {
        $data = [];
        if (strlen($this->host) > 0) {
            $data['host'] = $this->host;
        }
        return $data;
    }
}
