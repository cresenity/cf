<?php

defined('SYSPATH') or die('No direct access allowed.');

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class CRemote_SSH_Connection implements CRemote_SSH_ConnectionInterface {
    /**
     * @var CRemote_SSH_Gateway
     */
    protected $gateway;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var CRemote_SSH_Config
     */
    protected $config;

    /**
     * @var array
     */
    protected $tasks = [];

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @param string $name
     */
    public function __construct($name, CRemote_SSH_Config $config, ?CRemote_SSH_GatewayInterface $gateway = null) {
        $this->name = $name;
        $this->config = $config;
        $this->gateway = $gateway ?: new CRemote_SSH_Gateway($config);
    }

    /**
     * @param string       $task
     * @param string|array $commands
     *
     * @return $this
     */
    public function define($task, $commands) {
        $this->tasks[$task] = $commands;

        return $this;
    }

    /**
     * @param string $task
     *
     * @return void
     */
    public function task($task, ?Closure $callback = null) {
        if (isset($this->tasks[$task])) {
            $this->run($this->tasks[$task], $callback);
        }
    }

    /**
     * @param string|array $commands
     *
     * @return mixed
     */
    public function run($commands, ?Closure $callback = null) {
        $gateway = $this->getGateway();
        $callback = $this->getCallback($callback);

        return $gateway->run($this->formatCommands($commands), $callback);
    }

    /**
     * @param string|array $commands
     *
     * @return string
     */
    public function exec($commands) {
        $gateway = $this->getGateway();

        return $gateway->run($this->formatCommands($commands));
    }

    /**
     * @param string|array $commands
     * @param mixed        $timeout
     *
     * @return string
     */
    public function runBlocking($commands, $timeout = 2) {
        $gateway = $this->getGateway();

        return $gateway->runBlocking($this->formatCommands($commands), $timeout);
    }

    /**
     * The connect attempt is retried once on a transient connection failure
     * (e.g. "Connection closed by server") before giving up - the first
     * attempt can land on a socket the remote end already dropped, and
     * simply trying again against a fresh connection usually succeeds.
     *
     * @throws \RuntimeException
     *
     * @return CRemote_SSH_Gateway
     */
    public function getGateway() {
        if (!$this->gateway->connected()) {
            $lastException = null;

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $connected = $this->gateway->connect($this->config->getUsername());
                    $lastException = null;

                    break;
                } catch (\Exception $ex) {
                    $lastException = $ex;
                    $this->gateway->resetConnection();
                }
            }

            if ($lastException !== null) {
                throw new \RuntimeException('Unable to connect to remote server: ' . $lastException->getMessage(), 0, $lastException);
            }
            if (!$connected) {
                throw new \RuntimeException('Unable to connect to remote server: authentication failed for user ' . $this->config->getUsername());
            }
        }

        return $this->gateway;
    }

    /**
     * @param null|\Closure $callback
     *
     * @return \Closure
     */
    protected function getCallback($callback) {
        if (!is_null($callback)) {
            return $callback;
        }

        return function ($line) {
            $this->display($line);
        };
    }

    /**
     * @param string $line
     *
     * @return void
     */
    public function display($line) {
        $server = $this->config->getUsername() . '@' . $this->config->getConnectionHost();
        $lead = '<comment>[' . $server . ']</comment> <info>(' . $this->name . ')</info>';
        $this->getOutput()->writeln($lead . ' ' . $line);
    }

    /**
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput() {
        if (is_null($this->output)) {
            $this->output = new BufferedOutput();
        }

        return $this->output;
    }

    /**
     * @return void
     */
    public function setOutput(OutputInterface $output) {
        $this->output = $output;
    }

    /**
     * @param string|array $commands
     *
     * @return string
     */
    protected function formatCommands($commands) {
        return is_array($commands) ? implode(' && ', $commands) : $commands;
    }

    /**
     * @param string $remote
     * @param string $local
     *
     * @return void
     */
    public function get($remote, $local) {
        $this->getGateway()->get($remote, $local);
    }

    /**
     * @param string $remote
     *
     * @return string
     */
    public function getString($remote) {
        return $this->getGateway()->getString($remote);
    }

    /**
     * @param string $local
     * @param string $remote
     *
     * @return void
     */
    public function put($local, $remote) {
        $this->getGateway()->put($local, $remote);
    }

    /**
     * @param string $remote
     * @param string $contents
     *
     * @return void
     */
    public function putString($remote, $contents) {
        $this->getGateway()->putString($remote, $contents);
    }

    /**
     * @param string $remote
     *
     * @return bool
     */
    public function exists($remote) {
        return $this->getGateway()->exists($remote);
    }

    /**
     * @param string $remote
     * @param string $newRemote
     *
     * @return bool
     */
    public function rename($remote, $newRemote) {
        return $this->getGateway()->rename($remote, $newRemote);
    }

    /**
     * @param string $remote
     *
     * @return bool
     */
    public function delete($remote) {
        return $this->getGateway()->delete($remote);
    }

    /**
     * @return int|bool
     */
    public function status() {
        return $this->gateway->status();
    }

    /**
     * @param int $second
     *
     * @return int|bool
     */
    public function setTimeout($second) {
        return $this->gateway->setTimeout($second);
    }

    /**
     * @return int
     */
    public function getTimeout() {
        return $this->gateway->getTimeout();
    }

    /**
     * @return void
     */
    public function disconnect() {
        $this->gateway->disconnect();
    }
}
