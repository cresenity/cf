<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 */

use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\System\SSH\Agent;

class CRemote_SSH_Gateway implements CRemote_SSH_GatewayInterface {
    /**
     * @var CRemote_SSH_Config
     */
    protected $config;

    /**
     * @var \phpseclib3\Net\SFTP
     */
    protected $connection;

    /**
     * ssh -L subprocess forwarding to the proxy jump target, when configured.
     *
     * @var null|resource
     */
    protected $tunnelProcess;

    /**
     * Temp private key file written for the proxy jump hop, if any.
     *
     * @var null|string
     */
    protected $tunnelKeyFile;

    /**
     * @param CRemote_SSH_Config $config
     */
    public function __construct(CRemote_SSH_Config $config) {
        $this->config = $config;
    }

    /**
     * @param string $username
     *
     * @return bool
     */
    public function connect($username) {
        return $this->getConnection()->login($username, $this->getAuthForLogin());
    }

    /**
     * @return bool
     */
    public function connected() {
        return $this->getConnection()->isConnected();
    }

    /**
     * @param string $command
     * @param mixed  $callback
     *
     * @return string
     */
    public function run($command, $callback = null) {
        return $this->getConnection()->exec($command, $callback);
    }

    /**
     * @param string $remote
     * @param string $local
     *
     * @return void
     */
    public function get($remote, $local) {
        $this->getConnection()->get($remote, $local);
    }

    /**
     * @param string $remote
     *
     * @return string
     */
    public function getString($remote) {
        return $this->getConnection()->get($remote);
    }

    /**
     * @param string $remote
     *
     * @return int
     */
    public function getFilesize($remote) {
        return $this->getConnection()->filesize($remote);
    }

    /**
     * @param string $local
     * @param string $remote
     *
     * @return void
     */
    public function put($local, $remote) {
        $this->getConnection()->put($remote, $local, SFTP::SOURCE_LOCAL_FILE);
    }

    /**
     * @param string $remote
     * @param string $contents
     *
     * @return void
     */
    public function putString($remote, $contents) {
        $this->getConnection()->put($remote, $contents);
    }

    /**
     * @return \phpseclib3\Net\SFTP
     */
    public function getConnection() {
        if ($this->connection) {
            return $this->connection;
        }

        $host = $this->config->getConnectionHost();
        $port = $this->config->getPort();

        if (cstr::contains($host, ':')) {
            list($host, $port) = explode(':', $host);
            $port = (int) $port;
        }

        if ($this->config->hasProxyJump()) {
            list($host, $port) = $this->openProxyJumpTunnel($host, $port);
        }

        return $this->connection = new SFTP($host, $port, $this->config->getTimeout());
    }

    /**
     * Membuka local port forward lewat bastion host memakai binary ssh
     * sistem, lalu mengembalikan host:port lokal yang siap dipakai
     * phpseclib seolah koneksi langsung.
     *
     * phpseclib3 di sini tidak mengimplementasikan kanal direct-tcpip, jadi
     * tunneling didelegasikan ke `ssh -L` yang sudah teruji, alih-alih
     * menambal protokol SSH sendiri.
     *
     * @param string $targetHost
     * @param int    $targetPort
     *
     * @throws \RuntimeException
     *
     * @return array [string $host, int $port]
     */
    protected function openProxyJumpTunnel($targetHost, $targetPort) {
        $jump = $this->config->getProxyJump();
        $localPort = $this->findFreePort();
        $command = $this->buildProxyJumpCommand($jump, $targetHost, $targetPort, $localPort);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $this->tunnelProcess = proc_open($command, $descriptors, $pipes);
        if (!is_resource($this->tunnelProcess)) {
            throw new \RuntimeException('Failed to start SSH proxy jump tunnel');
        }
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $deadline = microtime(true) + max($this->config->getTimeout(), 10);
        while (microtime(true) < $deadline) {
            $status = proc_get_status($this->tunnelProcess);
            if (!$status['running']) {
                $stderr = trim((string) stream_get_contents($pipes[2]));
                $this->closeTunnel();

                throw new \RuntimeException('SSH proxy jump tunnel exited before it was ready: ' . $stderr);
            }

            $probe = @fsockopen('127.0.0.1', $localPort, $errno, $errstr, 0.2);
            if ($probe) {
                fclose($probe);

                return ['127.0.0.1', $localPort];
            }
            usleep(100000);
        }

        $this->closeTunnel();

        throw new \RuntimeException('Timed out waiting for SSH proxy jump tunnel to ' . $targetHost . ':' . $targetPort);
    }

    /**
     * @return int
     */
    protected function findFreePort() {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$socket) {
            throw new \RuntimeException('Failed to allocate a local port for SSH proxy jump: ' . $errstr);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    /**
     * @param CRemote_SSH_Config $jump
     * @param string             $targetHost
     * @param int                $targetPort
     * @param int                $localPort
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    protected function buildProxyJumpCommand(CRemote_SSH_Config $jump, $targetHost, $targetPort, $localPort) {
        //`exec` lets the shell proc_open spawns replace itself with ssh instead
        //of forking a child - otherwise proc_terminate() only kills the shell
        //and the actual ssh tunnel is orphaned and keeps running.
        $parts = [
            'exec', 'ssh', '-N', '-T',
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ExitOnForwardFailure=yes',
            '-o', 'ServerAliveInterval=10',
            '-p', escapeshellarg((string) $jump->getPort()),
            '-L', escapeshellarg('127.0.0.1:' . $localPort . ':' . $targetHost . ':' . $targetPort),
        ];

        if (!$jump->getUseAgent()) {
            if (!$jump->hasPrivateKey()) {
                throw new \RuntimeException('SSH proxy jump only supports key or agent authentication for the bastion host');
            }
            $keyPath = $jump->getKeyPath();
            if ($keyPath === null || trim($keyPath) === '') {
                $keyPath = $this->tunnelKeyFile = $this->writeTemporaryKeyFile($jump->getPrivateKey());
            }
            $parts[] = '-o';
            $parts[] = 'IdentitiesOnly=yes';
            $parts[] = '-i';
            $parts[] = escapeshellarg($keyPath);
        }

        $parts[] = escapeshellarg($jump->getUsername() . '@' . $jump->getConnectionHost());

        return implode(' ', $parts);
    }

    /**
     * @param string $keytext
     *
     * @return string
     */
    protected function writeTemporaryKeyFile($keytext) {
        $path = tempnam(sys_get_temp_dir(), 'cf-ssh-jump-');
        file_put_contents($path, rtrim($keytext) . "\n");
        chmod($path, 0600);

        return $path;
    }

    /**
     * @return void
     */
    protected function closeTunnel() {
        if (is_resource($this->tunnelProcess)) {
            $status = proc_get_status($this->tunnelProcess);
            if ($status['running']) {
                proc_terminate($this->tunnelProcess, 15);
                $deadline = microtime(true) + 2;
                while ($status['running'] && microtime(true) < $deadline) {
                    usleep(50000);
                    $status = proc_get_status($this->tunnelProcess);
                }
                if ($status['running']) {
                    proc_terminate($this->tunnelProcess, 9);
                }
            }
            proc_close($this->tunnelProcess);
        }
        $this->tunnelProcess = null;

        if ($this->tunnelKeyFile !== null) {
            @unlink($this->tunnelKeyFile);
        }
        $this->tunnelKeyFile = null;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return \phpseclib3\Crypt\Common\PrivateKey|\phpseclib3\System\SSH\Agent|string
     */
    protected function getAuthForLogin() {
        if ($this->config->getUseAgent()) {
            return new Agent();
        }
        if ($this->config->hasPrivateKey()) {
            return $this->loadPrivateKey();
        }
        if ($this->config->hasPassword()) {
            return $this->config->getPassword();
        }

        throw new \InvalidArgumentException('Password / key is required.');
    }

    /**
     * @return \phpseclib3\Crypt\Common\PrivateKey
     */
    protected function loadPrivateKey() {
        $keytext = $this->config->getPrivateKey();
        if ($keytext !== null && trim($keytext) !== '') {
            return PublicKeyLoader::loadPrivateKey(trim($keytext));
        }

        $keyPath = $this->config->getKeyPath();
        if ($keyPath !== null && trim($keyPath) !== '') {
            $keyContent = file_get_contents($keyPath);

            return PublicKeyLoader::loadPrivateKey(trim($keyContent));
        }

        throw new \InvalidArgumentException('No private key available');
    }

    /**
     * @return int
     */
    public function getTimeout() {
        return $this->config->getTimeout();
    }

    /**
     * @param int $timeout
     *
     * @return void
     */
    public function setTimeout($timeout) {
        $this->config->setTimeout($timeout);
        $this->connection = null;
        if ($this->config->hasProxyJump()) {
            $this->closeTunnel();
        }
        $this->getConnection();
    }

    /**
     * @param mixed $commands
     * @param int   $timeout
     *
     * @return string
     */
    public function runBlocking($commands, $timeout = 2) {
        $connection = $this->getConnection();
        $connection->write($commands);
        $connection->setTimeout($timeout);

        return $connection->read();
    }

    /**
     * @param string $remote
     *
     * @return bool
     */
    public function exists($remote) {
        return $this->getConnection()->file_exists($remote);
    }

    /**
     * @param string $remote
     * @param string $newRemote
     *
     * @return bool
     */
    public function rename($remote, $newRemote) {
        return $this->getConnection()->rename($remote, $newRemote);
    }

    /**
     * @param string $remote
     *
     * @return bool
     */
    public function delete($remote) {
        return $this->getConnection()->delete($remote);
    }

    /**
     * @return int|bool
     */
    public function status() {
        return $this->getConnection()->getExitStatus();
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->config->getConnectionHost();
    }

    /**
     * @return int
     */
    public function getPort() {
        return $this->config->getPort();
    }

    /**
     * @return string
     */
    public function getLog() {
        return $this->getConnection()->getLog();
    }

    /**
     * @return void
     */
    public function disconnect() {
        if ($this->connection) {
            $this->connection->disconnect();
        }
        $this->closeTunnel();
    }
}
