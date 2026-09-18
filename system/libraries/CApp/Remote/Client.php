<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 14, 2018, 9:16:56 PM
 */
class CApp_Remote_Client {
    /**
     * @var string
     */
    protected $domain = '';

    /**
     * @var CApp_Remote
     */
    protected $remote;

    /**
     * @param CApp_Remote $remote
     */
    public function __construct(CApp_Remote $remote) {
        $this->remote = $remote;
        $this->domain = $remote->getDomain();
    }

    /**
     * @param string $engineName
     *
     * @return CApp_Remote_Client_Engine
     */
    private function createEngine($engineName) {
        $className = 'CApp_Remote_Client_Engine_' . $engineName;

        return new $className($this->engineOptions());
    }

    /**
     * @return array
     */
    private function engineOptions() {
        $remoteOptions = $this->remote->getOptions();
        $domain = $this->remote->getDomain();
        $options = array_merge($remoteOptions, ['domain' => $domain]);

        return $options;
    }

    /**
     * @return CApp_Remote_Client_Engine_Server
     */
    public function server() {
        return $this->createEngine('Server');
    }

    /**
     * @return CApp_Remote_Client_Engine_App
     */
    public function app() {
        return $this->createEngine('App');
    }
}
