<?php

/**
 * Description of SSH.
 */

use Symfony\Component\Process\Process;

class CDevSuite_Ssh {
    /**
     * @var CDevSuite_Filesystem
     */
    public $files;

    /**
     * Create a new Ssh instance.
     *
     * @return void
     */
    public function __construct() {
        $this->files = CDevSuite::filesystem();
    }

    /**
     * Get the configuration file path.
     *
     * @return string
     */
    public function path() {
        return CDevSuite::homePath() . '/ssh.json';
    }

    /**
     * Create the SSH configuration file if it does not already exist.
     *
     * @return void
     */
    public function ensureFileExists() {
        if (!$this->files->exists($this->path())) {
            $this->write([]);
        }
    }

    /**
     * Store a new SSH connection configuration under the given name, after verifying it can connect.
     *
     * @param string $name
     * @param array  $configuration
     *
     * @return bool
     */
    public function create($name, $configuration) {
        if (!$this->isCanConnect($configuration)) {
            CDevSuite::info('Error when connecting to:' . $name . ', please check your configuration');

            return false;
        }

        $data = $this->read();
        $data[$name] = $configuration;

        $this->write($data);

        return true;
    }

    /**
     * Write the given configuration to disk.
     *
     * @param mixed $data
     *
     * @return void
     */
    public function write($data) {
        $this->files->putAsUser($this->path(), json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL);
    }

    /**
     * Read the configuration file as JSON.
     *
     * @return array
     */
    public function read() {
        $this->ensureFileExists();

        return json_decode($this->files->get($this->path()), true);
    }

    /**
     * Get the stored SSH connections formatted as table rows.
     *
     * @return \CCollection
     */
    public function getTableData() {
        $data = $this->read();

        return c::collect($data)->map(function ($item, $key) {
            return [
                'key' => $key,
                'host' => carr::get($item, 'host') . ':' . carr::get($item, 'port'),
                'type' => carr::get($item, 'passwordType'),
                'user' => carr::get($item, 'user'),
                'password' => carr::get($item, 'password'),
            ];
        });
    }

    /**
     * Determine if an SSH configuration exists for the given key.
     *
     * @param string $key
     *
     * @return bool
     */
    public function exists($key) {
        return is_array(carr::get($this->read(), $key));
    }

    /**
     * Exit the process with an error if no SSH configuration exists for the given key.
     *
     * @param string $key
     *
     * @return void
     */
    public function existsOrExit($key) {
        if (!$this->exists($key)) {
            CDevSuite::error('Database configuration: ' . $key . ' not exists');
            exit(CConsole::FAILURE_EXIT);
        }
    }

    /**
     * Determine whether an SSH connection can be established for the given config key or raw config array.
     *
     * @param string|array $key
     *
     * @return bool
     */
    public function isCanConnect($key) {
        if (!CServer::isWindows()) {
            try {
                $ssh = $this->getRemoteSsh($key);
                $output = $ssh->run('ls')->output();
            } catch (Exception $ex) {
                $errMessage = $ex->getMessage();
                CDevSuite::info($ex->getMessage());

                return false;
            }
        }

        return true;
    }

    /**
     * Get a CRemote_SSH instance for the given config key or raw config array.
     *
     * @param string|array $key
     *
     * @return CRemote_SSH
     */
    public function getRemoteSsh($key) {
        $config = $this->toRemoteSshConfig($key);

        return CRemote::ssh($config);
    }

    /**
     * Build a CRemote SSH config array from a stored config key or a raw config array.
     *
     * @param string|array $keyFile
     *
     * @return array
     */
    public function toRemoteSshConfig($keyFile) {
        $configArray = $keyFile;
        if (!is_array($configArray)) {
            $configArray = carr::get($this->read(), $keyFile);
        }

        $host = carr::get($configArray, 'host');
        $username = carr::get($configArray, 'user');
        $password = carr::get($configArray, 'password');
        $port = carr::get($configArray, 'port');
        $passwordType = carr::get($configArray, 'passwordType');

        $config = [
            'host' => $host,
            'name' => $host,
            'username' => $username,
            'port' => $port,
            'authentication_type' => $passwordType == 'password' ? 'prompt' : 'pubkey',
        ];
        if ($passwordType == 'password') {
            $config['password'] = $password;
        }
        if ($passwordType == 'pubkey') {
            $keytext = $this->files->get($password);
            $config['keytext'] = $keytext;
        }

        return $config;
    }

    /**
     * Get the name of the SSH client executable for the current OS.
     *
     * @return string
     */
    public function executableName() {
        if (CServer::isWindows()) {
            $bit = PHP_INT_SIZE * 8;

            return 'putty-' . $bit . '-x86.exe';
        }

        return 'ssh';
    }

    /**
     * Open an interactive SSH session for the stored connection with the given name.
     *
     * @param string $name
     *
     * @return void
     */
    public function open($name) {
        $configArray = carr::get($this->read(), $name);

        $host = carr::get($configArray, 'host');
        $username = carr::get($configArray, 'user');
        $password = carr::get($configArray, 'password');
        $port = carr::get($configArray, 'port');
        $passwordType = carr::get($configArray, 'passwordType');
        $executable = 'ssh';
        $cmdArguments = [
            $executable,
            $username . '@' . $host,
            '-p',
            $port
        ];

        if (CServer::isWindows()) {
            $executable = CDevSuite::binPath() . 'putty' . DS . $this->executableName();
            $cmdArguments = [
                $executable,
                '-ssh',
                $username . '@' . $host,
                $port,
            ];
            if ($passwordType == 'password') {
                $cmdArguments[] = '-pw';
                $cmdArguments[] = $password;
            }
        }

        if ($passwordType != 'password') {
            $cmdArguments[] = '-i';
            $cmdArguments[] = $password;
        }
        $process = new Process($cmdArguments);

        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        $process->run();
    }
}
