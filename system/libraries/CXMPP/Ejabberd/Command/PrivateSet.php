<?php

/**
 * @deprecated 1.8
 */
class CXMPP_Ejabberd_Command_PrivateSet extends CXMPP_Ejabberd_CommandAbstract {
    /**
     * @var string
     */
    private $user;

    /**
     * @var string
     */
    private $element;

    /**
     * @var string
     */
    private $host;

    public function __construct($user, $host, $element) {
        $this->user = $user;
        $this->element = $element;
        $this->host = $host;
    }

    public function getCommandName() {
        return 'private_set';
    }

    public function getCommandData() {
        $data = [
            'user' => $this->user,
            'host' => $this->host,
            'element' => $this->element
        ];

        return $data;
    }
}
