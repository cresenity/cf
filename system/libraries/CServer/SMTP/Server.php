<?php

/**
 * Main Server
 * Handles Sockets and Clients.
 */

use Exception;
use RuntimeException;
use Psr\Log\NullLogger;
use Smtpd\Network\Socket;
use Psr\Log\LoggerAwareTrait;
use Smtpd\Network\AbstractSocket;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CServer_SMTP_Server extends CServer_SMTP_Thread {
    use LoggerAwareTrait;

    const LOOP_USLEEP = 10000;

    /**
     * @var AbstractSocket
     */
    private $socket;

    /**
     * @var bool
     */
    private $isListening = false;

    /**
     * @var array
     */
    private $options;

    /**
     * @var string
     *
     * @deprecated
     */
    private $ip;

    /**
     * @var int
     *
     * @deprecated
     */
    private $port;

    /**
     * @var int
     */
    private $clientsId = 0;

    /**
     * @var CServer_SMTP_Client[]
     */
    private $clients = [];

    /**
     * @var int
     */
    private $eventsId = 0;

    /**
     * @var Event[]
     */
    private $events = [];

    /**
     * @var string
     *
     * @deprecated
     */
    private $hostname;

    /**
     * Server constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = []) {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'ip' => '127.0.0.1',
            'port' => 20025,
            'hostname' => 'localhost.localdomain',
            'logger' => new NullLogger(),
        ]);
        $this->options = $resolver->resolve($options);

        $this->logger = $this->options['logger'];

        $this->setIp($this->options['ip']);
        $this->setPort($this->options['port']);
        $this->setHostname($this->options['hostname']);

        $this->logger->info('start');
        $this->logger->info('ip = "' . $this->options['ip'] . '"');
        $this->logger->info('port = "' . $this->options['port'] . '"');
        $this->logger->info('hostname = "' . $this->options['hostname'] . '"');
    }

    /**
     * @param string $ip
     */
    public function setIp($ip) {
        $this->ip = $ip;
    }

    /**
     * @param int $port
     */
    public function setPort($port) {
        $this->port = $port;
    }

    /**
     * @param array $contextOptions
     *
     * @return bool
     */
    public function listen(array $contextOptions) {
        if (!$this->ip && !$this->port) {
            return false;
        }

        $this->socket = new CServer_SMTP_Network_Socket();

        $bind = false;

        try {
            $bind = $this->socket->bind($this->ip, $this->port);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        if ($bind) {
            try {
                if ($this->socket->listen($contextOptions)) {
                    $this->logger->notice('listen ok');
                    $this->isListening = true;

                    return true;
                }
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        return false;
    }

    /**
     * Main Loop.
     */
    public function loop() {
        while (!$this->getExit()) {
            $this->run();
            usleep(static::LOOP_USLEEP);
        }

        $this->shutdown();
    }

    /**
     * Main Function
     * Handles everything, keeps everything up-to-date.
     */
    public function run() {
        if (!$this->socket) {
            throw new RuntimeException('Socket not initialized. You need to execute listen().', 1);
        }

        $readHandles = [];
        $writeHandles = [];
        $exceptHandles = [];

        if ($this->isListening) {
            $readHandles[] = $this->socket->getHandle();
        }
        foreach ($this->clients as $clientId => $client) {
            // Collect client handles.
            $readHandles[] = $client->getSocket()->getHandle();
        }

        $handlesChanged = $this->socket->select($readHandles, $writeHandles, $exceptHandles);

        if ($handlesChanged) {
            foreach ($readHandles as $readableHandle) {
                if ($this->isListening && $readableHandle == $this->socket->getHandle()) {
                    // Server
                    $socket = $this->socket->accept();
                    if ($socket) {
                        $client = $this->newClient($socket);
                        $client->sendReady();
                        //$this->logger->debug('new client: '.$client->getId().', '.$client->getIpPort());
                    }
                } else {
                    // Client
                    $client = $this->getClientByHandle($readableHandle);
                    if ($client) {
                        if (feof($client->getSocket()->getHandle())) {
                            $this->removeClient($client);
                        } else {
                            //$this->logger->debug('old client: '.$client->getId().', '.$client->getIpPort());
                            $client->dataRecv();

                            if ($client->getStatus('hasShutdown')) {
                                $this->removeClient($client);
                            }
                        }
                    }
                    //$this->logger->debug('old client: '.$client->getId().', '.$client->getIpPort());
                }
            }
        }
    }

    /**
     * Create a new Client for a new incoming socket connection.
     *
     * @param mixed $socket
     *
     * @return CServer_SMTP_Client
     */
    public function newClient($socket) {
        $this->clientsId++;

        $options = [
            'hostname' => $this->getHostname(),
            'logger' => $this->logger,
        ];
        $client = new CServer_SMTP_Client($options);
        $client->setSocket($socket);
        $client->setId($this->clientsId);
        $client->setServer($this);

        $this->clients[$this->clientsId] = $client;

        return $client;
    }

    /**
     * @return string
     */
    public function getHostname() {
        return $this->hostname;
    }

    /**
     * @param string $hostname
     */
    public function setHostname(string $hostname) {
        $this->hostname = $hostname;
    }

    /**
     * Find a Client by socket handle.
     *
     * @param resource $handle
     *
     * @return null|CServer_SMTP_Client
     */
    public function getClientByHandle($handle) {
        foreach ($this->clients as $clientId => $client) {
            $socket = $client->getSocket();
            if ($socket->getHandle() == $handle) {
                return $client;
            }
        }

        return null;
    }

    /**
     * @param CServer_SMTP_Client $client
     */
    public function removeClient(CServer_SMTP_Client $client) {
        $this->logger->debug('client remove: ' . $client->getId());

        $client->shutdown();

        $clientsId = $client->getId();
        unset($this->clients[$clientsId]);
    }

    /**
     * Shutdown the server.
     * Should be executed before your application exits.
     */
    public function shutdown() {
        $this->logger->debug('shutdown');

        // Notify all clients.
        foreach ($this->clients as $clientId => $client) {
            $client->sendQuit();
            $this->removeClient($client);
        }

        $this->logger->debug('shutdown done');
    }

    /**
     * @param CServer_SMTP_Event $event
     */
    public function addEvent(CServer_SMTP_Event $event) {
        $this->eventsId++;
        $this->events[$this->eventsId] = $event;
    }

    /**
     * @param CServer_SMTP_Client $client
     * @param string              $from
     * @param array               $rcpt
     * @param string              $mail
     */
    public function newMail(CServer_SMTP_Client $client, string $from, array $rcpt, string $mail) {
        $this->eventExecute(CServer_SMTP_Event::TRIGGER_NEW_MAIL, $client, [$from, $rcpt, $mail]);
    }

    /**
     * @param int                 $trigger
     * @param CServer_SMTP_Client $client
     * @param array               $args
     */
    private function eventExecute($trigger, CServer_SMTP_Client $client, array $args = []) {
        foreach ($this->events as $eventId => $event) {
            if ($event->getTrigger() == $trigger) {
                $event->execute($client, $args);
            }
        }
    }

    /**
     * @param CServer_SMTP_Client $client
     * @param string              $rcpt
     *
     * @return bool
     */
    public function newRcpt(CServer_SMTP_Client $client, $rcpt) {
        foreach ($this->events as $eventId => $event) {
            if ($event->getTrigger() == CServer_SMTP_Event::TRIGGER_NEW_RCPT && !$event->execute($client, [$rcpt])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute authentication events.
     * All authentication events must return true for authentication to be successful.
     *
     * @param CServer_SMTP_Client $client
     * @param string              $method
     *
     * @return bool
     */
    public function authenticateUser(CServer_SMTP_Client $client, $method) {
        $authenticated = false;
        $args = [$method, $client->getCredentials()];

        foreach ($this->events as $eventId => $event) {
            if ($event->getTrigger() == CServer_SMTP_Event::TRIGGER_AUTH_ATTEMPT) {
                if (!$event->execute($client, $args)) {
                    return false;
                }

                $authenticated = true;
            }
        }

        return $authenticated;
    }
}
