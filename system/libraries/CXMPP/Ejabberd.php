<?php

/**
 * Description of Ejabberd
 *
 * @author Hery Kurniawan <hery@itton.co.id>
 * @license Ittron Global Teknologi
 *
 * @since May 30, 2020
 */
class CXMPP_Ejabberd {
    /**
     * @var CXMPP_Ejabberd_Client
     */
    protected $client;

    protected $config;

    /**
     * Ejabberd constructor.
     *
     * @param $config
     *
     * @throws Exception
     */
    public function __construct($config) {
        $this->config = $config;
        $this->client = new CXMPP_Ejabberd_Client($config);
    }

    /**
     * @param string $user
     * @param string $password
     * @param string $host
     *
     * @return CXMPP_Ejabberd_Response
     */
    public function register($user, $password, $host = null) {
        if ($host == null) {
            $host = carr::get($this->config, 'domain');
        }
        $command = new CXMPP_Ejabberd_Command_Register($user, $password, $host);
        return $this->client->execute($command);
    }

    public function registeredUser($host = null) {
        if ($host == null) {
            $host = carr::get($this->config, 'domain');
        }
        $command = new CXMPP_Ejabberd_Command_RegisteredUser($host);
        return $this->client->execute($command);
    }

    public function connectedUsers($fullInfo = false) {
        $command = new CXMPP_Ejabberd_Command_ConnectedUsers($fullInfo);
        return $this->client->execute($command);
    }

    public function createRoom($name, $service, $host = null, $options = []) {
        if ($host == null) {
            $host = carr::get($this->config, 'domain');
        }
        $command = new CXMPP_Ejabberd_Command_CreateRoom($name, $service, $host, $options);
        return $this->client->execute($command);
    }

    public function mucOnlineRooms($service) {
        $command = new CXMPP_Ejabberd_Command_MucOnlineRooms($service);
        return $this->client->execute($command);
    }

    public function subscribeRoom($user, $nick, $room, $nodes = []) {
        $command = new CXMPP_Ejabberd_Command_SubscribeRoom($user, $nick, $room, $nodes);
        return $this->client->execute($command);
    }

    public function unsubscribeRoom($user, $room) {
        $command = new CXMPP_Ejabberd_Command_UnsubscribeRoom($user, $room);
        return $this->client->execute($command);
    }

    public function sendDirectInvitation($name, $service, $users, $reason = '', $password = '') {
        $command = new CXMPP_Ejabberd_Command_SendDirectInvitation($name, $service, $users, $reason, $password);
        return $this->client->execute($command);
    }

    public function autoJoinRoom($user, $host, $room, $service, $nick = '') {
        $command = new CXMPP_Ejabberd_Command_AutoJoinRoom($user, $host, $room, $service, $nick);
        return $this->client->execute($command);
    }

    public function removeAutoJoinRoom($user, $host, $room, $service, $nick = '') {
        $command = new CXMPP_Ejabberd_Command_RemoveAutoJoinRoom($user, $host, $room, $service, $nick);
        return $this->client->execute($command);
    }

    public function destroyRoom($room, $service) {
        $command = new CXMPP_Ejabberd_Command_DestroyRoom($room, $service);
        return $this->client->execute($command);
    }

    public function setRoomAffiliation($user, $name, $service, $affiliation = 'member') {
        $command = new CXMPP_Ejabberd_Command_SetRoomAffiliation($user, $name, $service, $affiliation);
        return $this->client->execute($command);
    }

    public function getRoomAffiliations($name, $service) {
        $command = new CXMPP_Ejabberd_Command_GetRoomAffiliations($name, $service);
        return $this->client->execute($command);
    }

    public function getRoomAffiliation($user, $name, $service) {
        $command = new CXMPP_Ejabberd_Command_GetRoomAffiliation($user, $name, $service);
        return $this->client->execute($command);
    }

    public function getLastResponse() {
        return $this->client->getLastResponse();
    }
}
