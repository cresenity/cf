<?php

/**
 * Description of SendDirectInvitation
 *
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since May 31, 2020
 */
class CXMPP_Ejabberd_Command_SendDirectInvitation extends CXMPP_Ejabberd_CommandAbstract {
    private $name;

    private $service;

    private $password;

    private $reason;

    private $users;

    /**
     * @var array
     */
    private $options;

    public function __construct($name, $service, $users, $reason = '', $password = '') {
        $this->name = $name;
        $this->service = $service;
        $this->users = $users;
        $this->reason = $reason;
        $this->password = $password;
    }

    public function getCommandName() {
        return 'send_direct_invitation';
    }

    public function getCommandData() {
        $data = [
            'name' => $this->name,
            'service' => $this->service,
            'users' => implode(':', carr::wrap($this->users)),
            'reason' => $this->reason,
            'password' => $this->password
        ];

        return $data;
    }
}
