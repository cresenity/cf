<?php

class CVendor_Wago_Device {
    protected $token;

    protected $isSandbox;

    /**
     * @var CVendor_Wago_Client
     */
    protected $client;

    public function __construct($token, $options = []) {
        $this->token = $token;
        $this->isSandbox = (bool) carr::get($options, 'sandbox');
        $this->client = new CVendor_Wago_Client(new CVendor_Wago_Adapter_GuzzleAdapter(['token' => $this->token]), $this->getBaseUri());
    }

    protected function getBaseUri() {
        return $this->isSandbox ? 'https://wapro.dev.ittron.co.id/api/device/' : 'https://wa-go.id/api/device/';
    }

    /**
     * @param string $phone
     * @param string $message
     * @param array  $options
     *
     * @throws CVendor_Wago_Exception_ApiException
     *
     * @return array
     */
    public function sendMessage($phone, $message, array $options = []) {
        $request = [
            'phone' => $phone,
            'message' => $message,
        ];
        $imageUrl = carr::get($options, 'imageUrl');
        $documentUrl = carr::get($options, 'documentUrl');
        $mimeType = carr::get($options, 'mimeType');
        $fileName = carr::get($options, 'fileName');
        $scheduleAt = carr::get($options, 'scheduleAt');
        if ($imageUrl) {
            $request['imageUrl'] = $imageUrl;
        }
        if ($documentUrl) {
            $request['documentUrl'] = $documentUrl;
        }
        if ($mimeType) {
            $request['mimeType'] = $mimeType;
        }
        if ($fileName) {
            $request['fileName'] = $fileName;
        }
        if ($scheduleAt) {
            $request['scheduleAt'] = $scheduleAt;
        }

        return $this->handleResponse($this->client->post('message/send', $request));
    }

    /**
     * Presence "sedang mengetik" berdiri sendiri, tanpa mengirim pesan apa
     * pun - berguna untuk menunjukkan ke pelanggan bahwa balasan sedang
     * disiapkan (mis. oleh AI agent) sebelum pesan sesungguhnya siap.
     *
     * @param string $phone
     *
     * @throws CVendor_Wago_Exception_ApiException
     *
     * @return array
     */
    public function sendTyping($phone) {
        $request = [
            'phone' => $phone,
        ];

        return $this->handleResponse($this->client->post('message/typing', $request));
    }

    public function getInfo() {
        return $this->handleResponse($this->client->get('info'));
    }

    /**
     * @return array
     */
    public function getStatus() {
        return $this->handleResponse($this->client->get('status'));
    }

    public function getWebhook() {
        return $this->handleResponse($this->client->get('webhook/get'));
    }

    public function unsetWebhook() {
        return $this->handleResponse($this->client->post('webhook/unset'));
    }

    public function setWebhook($url) {
        $options = [
            'endpoint' => $url
        ];

        return $this->handleResponse($this->client->post('webhook/set', $options));
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
        $errCode = (int) carr::get($response, 'errCode');
        if ($errCode != 0) {
            $errMessage = carr::get($response, 'errMessage');

            throw new CVendor_Wago_Exception_ApiException($errMessage);
        }

        return carr::get($response, 'data', []);
    }

    public function getClient() {
        return $this->client;
    }
}
