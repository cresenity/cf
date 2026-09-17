<?php

/**
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since May 30, 2020
 */
class CXMPP_Ejabberd_Command_Register extends CXMPP_Ejabberd_CommandAbstract {
    private $user;

    private $password;

    /**
     * @var int
     */
    private $host;

    public function __construct($user, $password, $host = -1) {
        $this->user = $user;
        $this->password = $password;
        $this->host = $host;
    }

    public function getCommandName() {
        return 'register';
    }

    public function getCommandData() {
        return [
            'user' => $this->user,
            'host' => $this->host,
            'password' => $this->password
        ];
    }
}
