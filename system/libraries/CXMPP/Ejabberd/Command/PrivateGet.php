<?php

/**
 * Description of PrivateGe
 *
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since May 31, 2020
 */
class CXMPP_Ejabberd_Command_PrivateGet extends CXMPP_Ejabberd_CommandAbstract {
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
    private $ns;

    /**
     * @var string
     */
    private $host;

    public function __construct($user, $host, $element, $ns) {
        $this->user = $user;
        $this->element = $element;
        $this->host = $host;
        $this->ns = $ns;
    }

    public function getCommandName() {
        return 'private_get';
    }

    public function getCommandData() {
        return [
            'user' => $this->user,
            'host' => $this->host,
            'element' => $this->element,
            'ns' => $this->ns
        ];
    }
}
