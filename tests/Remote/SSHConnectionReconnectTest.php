<?php

use PHPUnit\Framework\TestCase;

/**
 * CRemote_SSH_Connection::getGateway() used to give up on the very first
 * connect() failure. A connection the remote end had already closed (e.g.
 * "Connection closed by server", devcloud Exception Collector #4596 -
 * DTaskQueue_Collector_Exception SSHing into its own server to pull
 * exception dumps) surfaced as an uncaught RuntimeException even though
 * simply trying again on a fresh socket usually succeeds. getGateway() now
 * retries once, forcing CRemote_SSH_Gateway::resetConnection() in between so
 * the retry builds a brand new connection object instead of hammering the
 * same dead one.
 */
class SSHConnectionReconnectTest extends TestCase {
    private function config() {
        return new CRemote_SSH_Config(['host' => 'example.test', 'username' => 'deploy']);
    }

    public function testGetGatewayRetriesOnceAfterATransientConnectFailure() {
        $gateway = new class() implements CRemote_SSH_GatewayInterface {
            public $connectCalls = 0;

            public $resetCalls = 0;

            public function connect($username) {
                $this->connectCalls++;
                if ($this->connectCalls === 1) {
                    throw new RuntimeException('Connection closed by server');
                }

                return true;
            }

            public function connected() {
                return false;
            }

            public function resetConnection() {
                $this->resetCalls++;
            }

            public function run($command) {
            }

            public function get($remote, $local) {
            }

            public function getString($remote) {
                return '';
            }

            public function put($local, $remote) {
            }

            public function putString($remote, $contents) {
            }

            public function exists($remote) {
                return false;
            }

            public function rename($remote, $newRemote) {
                return false;
            }

            public function delete($remote) {
                return false;
            }

            public function status() {
                return 0;
            }
        };

        $connection = new CRemote_SSH_Connection('test', $this->config(), $gateway);

        $this->assertSame($gateway, $connection->getGateway());
        $this->assertSame(2, $gateway->connectCalls, 'harus mencoba lagi setelah kegagalan pertama');
        $this->assertSame(1, $gateway->resetCalls, 'harus membuang koneksi lama sebelum mencoba lagi, bukan me-retry socket yang sama');
    }

    public function testGetGatewayGivesUpAfterTwoTransientFailures() {
        $gateway = new class() implements CRemote_SSH_GatewayInterface {
            public $connectCalls = 0;

            public $resetCalls = 0;

            public function connect($username) {
                $this->connectCalls++;

                throw new RuntimeException('Connection closed by server');
            }

            public function connected() {
                return false;
            }

            public function resetConnection() {
                $this->resetCalls++;
            }

            public function run($command) {
            }

            public function get($remote, $local) {
            }

            public function getString($remote) {
                return '';
            }

            public function put($local, $remote) {
            }

            public function putString($remote, $contents) {
            }

            public function exists($remote) {
                return false;
            }

            public function rename($remote, $newRemote) {
                return false;
            }

            public function delete($remote) {
                return false;
            }

            public function status() {
                return 0;
            }
        };

        $connection = new CRemote_SSH_Connection('test', $this->config(), $gateway);

        try {
            $connection->getGateway();
            $this->fail('Diharapkan RuntimeException setelah dua kegagalan berturut-turut.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Connection closed by server', $e->getMessage());
        }

        $this->assertSame(2, $gateway->connectCalls, 'tidak boleh mencoba lebih dari dua kali');
        $this->assertSame(2, $gateway->resetCalls);
    }

    public function testGetGatewayDoesNotRetryOnAuthenticationFailure() {
        // connect() mengembalikan false (bukan melempar) untuk kredensial yang
        // salah - mencoba lagi dengan kredensial yang sama percuma, dan bisa
        // memicu pembatasan (fail2ban dsb) di server tujuan.
        $gateway = new class() implements CRemote_SSH_GatewayInterface {
            public $connectCalls = 0;

            public $resetCalls = 0;

            public function connect($username) {
                $this->connectCalls++;

                return false;
            }

            public function connected() {
                return false;
            }

            public function resetConnection() {
                $this->resetCalls++;
            }

            public function run($command) {
            }

            public function get($remote, $local) {
            }

            public function getString($remote) {
                return '';
            }

            public function put($local, $remote) {
            }

            public function putString($remote, $contents) {
            }

            public function exists($remote) {
                return false;
            }

            public function rename($remote, $newRemote) {
                return false;
            }

            public function delete($remote) {
                return false;
            }

            public function status() {
                return 0;
            }
        };

        $connection = new CRemote_SSH_Connection('test', $this->config(), $gateway);

        try {
            $connection->getGateway();
            $this->fail('Diharapkan RuntimeException untuk autentikasi gagal.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('authentication failed', $e->getMessage());
        }

        $this->assertSame(1, $gateway->connectCalls, 'kredensial salah tidak perlu dicoba ulang');
        $this->assertSame(0, $gateway->resetCalls);
    }

    public function testGetGatewaySkipsConnectEntirelyWhenAlreadyConnected() {
        $gateway = new class() implements CRemote_SSH_GatewayInterface {
            public $connectCalls = 0;

            public function connect($username) {
                $this->connectCalls++;

                return true;
            }

            public function connected() {
                return true;
            }

            public function resetConnection() {
            }

            public function run($command) {
            }

            public function get($remote, $local) {
            }

            public function getString($remote) {
                return '';
            }

            public function put($local, $remote) {
            }

            public function putString($remote, $contents) {
            }

            public function exists($remote) {
                return false;
            }

            public function rename($remote, $newRemote) {
                return false;
            }

            public function delete($remote) {
                return false;
            }

            public function status() {
                return 0;
            }
        };

        $connection = new CRemote_SSH_Connection('test', $this->config(), $gateway);

        $this->assertSame($gateway, $connection->getGateway());
        $this->assertSame(0, $gateway->connectCalls);
    }
}
