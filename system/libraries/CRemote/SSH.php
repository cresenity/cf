<?php

defined('SYSPATH') or die('No direct access allowed.');

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\BufferedOutput;

class CRemote_SSH {
    /**
     * @var string
     */
    protected $name;

    /**
     * @var CRemote_SSH_Config
     */
    protected $config;

    /**
     * @var CRemote_SSH_Connection
     */
    protected $connection;

    /**
     * @param array|CRemote_SSH_Config $config
     */
    public function __construct($config) {
        if (!$config instanceof CRemote_SSH_Config) {
            $config = new CRemote_SSH_Config($config);
        }
        $this->config = $config;

        $this->name = $config->getHost();
        $this->connection = $this->makeConnection($this->name, $config);
    }

    /**
     * Get a remote connection instance.
     *
     * @return CRemote_SSH_Connection
     */
    public function connection() {
        return $this->connection;
    }

    /**
     * @param string             $name
     * @param CRemote_SSH_Config $config
     *
     * @return CRemote_SSH_Connection
     */
    protected function makeConnection($name, CRemote_SSH_Config $config) {
        $this->setOutput($connection = new CRemote_SSH_Connection($name, $config));

        return $connection;
    }

    /**
     * Set the output implementation on the connection.
     *
     * @param CRemote_SSH_Connection $connection
     *
     * @return void
     */
    protected function setOutput(CRemote_SSH_Connection $connection) {
        $output = php_sapi_name() == 'cli' ? new ConsoleOutput() : new BufferedOutput();

        $connection->setOutput($output);
    }


    public function output() {
        $output = $this->connection->getOutput();
        if ($output instanceof BufferedOutput) {
            return $output->fetch();
        }

        return null;
    }

    public function outputContent() {
        $output = $this->output();
        $content = preg_replace('/\[.+?\] \(*.+?\) /', '', $output);

        return $content;
    }

    /**
     * Run a set of commands to the connection.
     *
     * @param string|array $commands
     * @param Closure      $callback
     *
     * @return $this
     */
    public function run($commands, ?Closure $callback = null) {
        $this->connection->run($commands, $callback);

        return $this;
    }

    /**
     * @param string $commands
     *
     * @return string
     */
    public function exec($commands) {
        return $this->connection->exec($commands);
    }

    /**
     * Run a set of commands against the connection (blocking).
     *
     * @param string|array $commands
     * @param mixed        $timeout
     *
     * @return string
     */
    public function runBlocking($commands, $timeout = 2) {
        return $this->connection->runBlocking($commands, $timeout);
    }

    /**
     * Get log ssh with defined NET_SSH2_LOGGING.
     *
     * @return string
     */
    public function getLog() {
        return $this->connection->getGateway()->getLog();
    }

    public function disconnect() {
        return $this->connection->disconnect();
    }

    public function reconnect() {
        $this->disconnect();
        $this->connection = $this->makeConnection($this->name, $this->config);
    }

    /**
     * Dynamically pass methods to the default connection.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters) {
        return call_user_func_array([$this->connection, $method], $parameters);
    }

    /**
     * @return phpseclib3\Net\SFTP
     */
    public function getClient() {
        return $this->connection->getGateway()->getConnection();
    }

    /**
     * Upload a local file to the server.
     *
     * @param string $local
     * @param string $remote
     *
     * @return void
     */
    public function put($local, $remote) {
        $this->connection->put($local, $remote);
    }

    /**
     * Upload a string to to the given file on the server.
     *
     * @param string $remote
     * @param string $contents
     *
     * @return void
     */
    public function putString($remote, $contents) {
        $this->connection->putString($remote, $contents);
    }

    /**
     * Check whether a given file exists on the server.
     *
     * @param string $remote
     *
     * @return bool
     */
    public function exists($remote) {
        return $this->connection->exists($remote);
    }

    /**
     * Download the contents of a remote file.
     *
     * @param string $remote
     * @param string $local
     *
     * @return void
     */
    public function get($remote, $local) {
        $this->connection->get($remote, $local);
    }

    /**
     * Get the contents of a remote file.
     *
     * @param string $remote
     *
     * @return string
     */
    public function getString($remote) {
        return $this->connection->getString($remote);
    }

    /**
     * @return CRemote_SSH_Config
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->config->getConnectionHost();
    }
}
