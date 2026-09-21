<?php

class CVendor_WhatsApp_Api {
    protected $token;

    protected $businessAccountId;

    protected $phoneNumberId;

    protected $version;

    protected $url;

    /**
     * @var CVendor_WhatsApp_Client
     */
    protected $client;

    public function __construct($token, $businessAccountId, $phoneNumberId, $options = []) {
        $this->token = $token;
        $this->businessAccountId = $businessAccountId;
        $this->phoneNumberId = $phoneNumberId;
        $this->version = carr::get($options, 'version', 'v15.0');
        $this->url = carr::get($options, 'url', 'https://graph.facebook.com');

        $this->client = new CVendor_WhatsApp_Client(new CVendor_WhatsApp_Adapter_GuzzleAdapter(['token' => $this->token]), $this->getBaseUri());
    }

    protected function getBaseUri() {
        return $this->url . '/' . $this->version . '/';
    }

    /**
     * @param mixed $response
     *
     * @return array
     */
    public function handleResponse($response) {
        if (is_string($response)) {
            $response = json_decode($response, true);
        }

        return $response;
    }

    public function getClient() {
        return $this->client;
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function getTemplates($params = []) {
        $method = $this->businessAccountId . '/message_templates';

        return $this->handleResponse($this->client->get($method, $params));
    }

    public function sendMessage($params = []) {
        $method = $this->phoneNumberId . '/messages';

        return $this->handleResponse($this->client->post($method, $params));
    }

    /**
     * Marks an incoming message as read and shows the "typing…" indicator
     * on the customer's device (Meta Graph API feature, same endpoint as
     * sendMessage()) until either ~25 seconds pass or a real reply is sent.
     *
     * @param string $messageId the WhatsApp-native id of the incoming message
     *
     * @return array
     */
    public function markMessageAsReadWithTyping($messageId) {
        return $this->sendMessage([
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
            'typing_indicator' => ['type' => 'text'],
        ]);
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public function getTemplate($name) {
        $params = ['name' => $name];
        $result = $this->getTemplates($params);
        $data = carr::get($result, 'data');
        if (is_array($data) && count($data) > 0) {
            return carr::first($data);
        }

        return null;
    }
}
