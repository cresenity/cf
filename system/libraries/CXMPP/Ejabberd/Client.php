<?php

/**
 * @deprecated 1.8
 */
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class CXMPP_Ejabberd_Client {
    private $api = '';

    private $user = '';

    private $verify;

    private $password = '';

    private $domain = '';

    private $conference_domain = '';

    private $debug = '';

    private $client = '';

    private $lastResponse = null;

    /**
     * Ejabberd constructor.
     *
     * @param $config
     *
     * @throws Exception
     */
    public function __construct($config) {
        $this->api = carr::get($config, 'api');
        $this->user = carr::get($config, 'user');
        $this->user = carr::get($config, 'verify', false);
        $this->password = carr::get($config, 'password');
        $this->domain = carr::get($config, 'domain');
        $this->conference_domain = carr::get($config, 'conference_domain');

        $this->debug = carr::get($config, 'debug');

        $this->client = new Client([
            'base_uri' => $this->api,
            'verify' => $this->verify,
            'headers' => [
                'X-Admin' => true
            ]
        ]);
    }

    /**
     * @param CXMPP_Ejabberd_CommandAbstract
     *
     * @throws EjabberdException
     *
     * @return null|\Psr\Http\Message\StreamInterface
     */
    public function execute(CXMPP_Ejabberd_CommandAbstract $command) {
        $result = null;
        $commandName = $command->getCommandName();

        try {
            $request = [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'json' => $command->getCommandData()
            ];

            if (strlen($this->user) > 0) {
                $request['auth'] = [
                    $this->user, $this->password
                ];
            }

            $result = $this->client->request('POST', $commandName, $request)->getBody()->getContents();
        } catch (GuzzleException $e) {
            if ($e->getResponse() == null) {
                throw $e;
            } else {
                $result = $e->getResponse()->getBody()->getContents();
            }
        } catch (\Exception $e) {
            $result = $e;
        }

        $response = (new CXMPP_Ejabberd_Response($command, $result));
        $this->lastResponse = $response;
        if ($response->hasError()) {
            throw $response->throwException();
        }

        return $response->data();
    }

    public function getLastResponse() {
        return $this->lastResponse;
    }
}
