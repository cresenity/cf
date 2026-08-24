<?php

defined('SYSPATH') or die('No direct access allowed.');

class CServer_Server {
    /**
     * @var null|CRemote_SSH
     */
    private $ssh;

    /**
     * @var null|CServer_Storage
     */
    private $storageInstance;

    /**
     * Di-cache per biner agar satu server dapat memegang beberapa versi PHP
     * sekaligus tanpa saling menimpa.
     *
     * @var array
     */
    private $phpInstances = [];

    /**
     * @var null|CServer_Certbot
     */
    private $certbotInstance;

    /**
     * @var null|CServer_Fail2ban
     */
    private $fail2banInstance;

    /**
     * @var null|CServer_WebServer
     */
    private $webServerInstance;

    /**
     * @var null|CServer_Cf
     */
    private $cfInstance;

    /**
     * @var null|CServer_Redis
     */
    private $redisInstance;

    /**
     * @var null|CServer_OSAbstract
     */
    private $distroInstance;

    /**
     * @var null|CServer_SshKey
     */
    private $sshKeyInstance;

    /**
     * @var null|CServer_Mail
     */
    private $mailInstance;

    /**
     * @var null|CServer_Memory
     */
    private $memoryInstance;

    /**
     * @var null|CServer_System
     */
    private $systemInstance;

    /**
     * @var null|CServer_PhpInfo
     */
    private $phpInfoInstance;

    /**
     * @param null|CRemote_SSH|CRemote_SSH_Config $ssh
     */
    public function __construct($ssh = null) {
        if ($ssh instanceof CRemote_SSH_Config) {
            $ssh = CServer_SSHRepository::instance()->getSSH($ssh);
        } elseif ($ssh instanceof CRemote_SSH) {
            $ssh = CServer_SSHRepository::instance()->getSSH($ssh);
        }
        $this->ssh = $ssh;
    }

    /**
     * @return null|CRemote_SSH
     */
    public function getSSH() {
        return $this->ssh;
    }

    /**
     * @return bool
     */
    public function isRemote() {
        return $this->ssh !== null;
    }

    /**
     * @return bool
     */
    public function isLocal() {
        return $this->ssh === null;
    }

    /**
     * @return CServer_Storage
     */
    public function storage() {
        if ($this->storageInstance === null) {
            $this->storageInstance = new CServer_Storage($this);
        }

        return $this->storageInstance;
    }

    /**
     * @param null|string $phpBinary biner tertentu, mis. /usr/local/lsws/lsphp84/bin/php
     *
     * @return CServer_Php
     */
    public function php($phpBinary = null) {
        //instance di-cache per biner agar satu server dapat memegang beberapa
        //versi PHP sekaligus tanpa saling menimpa
        $key = $phpBinary ?: 'php';
        if (!isset($this->phpInstances[$key])) {
            $this->phpInstances[$key] = new CServer_Php($this, $phpBinary);
        }

        return $this->phpInstances[$key];
    }

    /**
     * Distribusi Linux server ini, dideteksi dari /etc/os-release.
     *
     * Berbeda dari getOS(), yang mengembalikan OS proses PHP yang sedang
     * berjalan, bukan server tujuan.
     *
     * @return CServer_OSAbstract
     */
    public function distro() {
        if ($this->distroInstance === null) {
            $this->distroInstance = CServer_OSAbstract::detect($this);
        }

        return $this->distroInstance;
    }

    /**
     * @param null|string $password bila null, dibaca dari requirepass di konfigurasi
     *
     * @return CServer_Redis
     */
    public function redis($password = null) {
        if ($this->redisInstance === null) {
            $this->redisInstance = new CServer_Redis($this, $password);
        } elseif ($password !== null) {
            $this->redisInstance->setPassword($password);
        }

        return $this->redisInstance;
    }

    /**
     * @return CServer_WebServer
     */
    public function webServer() {
        if ($this->webServerInstance === null) {
            $this->webServerInstance = new CServer_WebServer($this);
        }

        return $this->webServerInstance;
    }

    /**
     * Pemeriksa pemasangan Cresenity Framework di server ini.
     *
     * @return CServer_Cf
     */
    public function cf() {
        if ($this->cfInstance === null) {
            $this->cfInstance = new CServer_Cf($this);
        }

        return $this->cfInstance;
    }

    /**
     * @return CServer_Certbot
     */
    public function certbot() {
        if ($this->certbotInstance === null) {
            $this->certbotInstance = new CServer_Certbot($this);
        }

        return $this->certbotInstance;
    }

    /**
     * @return CServer_Fail2ban
     */
    public function fail2ban() {
        if ($this->fail2banInstance === null) {
            $this->fail2banInstance = new CServer_Fail2ban($this);
        }

        return $this->fail2banInstance;
    }

    /**
     * Layanan surel pada server ini.
     *
     * @return CServer_Mail
     */
    public function mail() {
        if ($this->mailInstance === null) {
            $this->mailInstance = new CServer_Mail($this);
        }

        return $this->mailInstance;
    }

    /**
     * Pembuatan pasangan kunci SSH pada server ini.
     *
     * @return CServer_SshKey
     */
    public function sshKey() {
        if ($this->sshKeyInstance === null) {
            $this->sshKeyInstance = new CServer_SshKey($this);
        }

        return $this->sshKeyInstance;
    }

    /**
     * @return CServer_Memory
     */
    public function memory() {
        if ($this->memoryInstance === null) {
            $this->memoryInstance = new CServer_Memory($this);
        }

        return $this->memoryInstance;
    }

    /**
     * @return CServer_System
     */
    public function system() {
        if ($this->systemInstance === null) {
            $this->systemInstance = new CServer_System($this);
        }

        return $this->systemInstance;
    }

    /**
     * @return CServer_Database
     */
    public function database() {
        return CServer_Database::instance();
    }

    /**
     * @param null|mixed $docRoot
     *
     * @return CServer_LetsEncrypt
     */
    public function letsEncrypt($docRoot = null) {
        return new CServer_LetsEncrypt($this, $docRoot);
    }

    /**
     * @return CServer_Config
     */
    public function config() {
        return CServer_Config::instance($this);
    }

    /**
     * @return CServer_PhpInfo
     */
    public function phpInfo() {
        if ($this->phpInfoInstance === null) {
            $this->phpInfoInstance = new CServer_PhpInfo($this);
        }

        return $this->phpInfoInstance;
    }

    /**
     * @return string
     */
    public function getHostname() {
        if ($this->isRemote()) {
            return trim($this->runCommand('hostname'));
        }

        return gethostname();
    }

    /**
     * @return string
     */
    public function getOS() {
        $os = $this->config()->get('os');
        if ($os == null) {
            $os = PHP_OS;
        }

        return $os;
    }

    /**
     * @return bool
     */
    public function isWindows() {
        return $this->getOS() === 'WINNT';
    }

    /**
     * @param string $command
     *
     * @return string
     */
    public function runCommand($command) {
        if ($this->isRemote()) {
            $output = '';
            $this->ssh->run($command, function ($line) use (&$output) {
                $output .= $line . PHP_EOL;
            });

            return $output;
        }

        $result = '';
        exec($command . ' 2>&1', $outputLines, $exitCode);
        if (is_array($outputLines)) {
            $result = implode(PHP_EOL, $outputLines);
        }

        return $result;
    }

    /**
     * @param string $strElem
     * @param string &$strBuffer
     *
     * @return bool
     */
    public function readEnv($strElem, &$strBuffer) {
        $strBuffer = '';
        if ($this->isWindows()) {
            if (isset($_SERVER)) {
                foreach ($_SERVER as $index => $value) {
                    if (is_string($value) && (trim($value) !== '') && (strtolower($index) === strtolower($strElem))) {
                        $strBuffer = $value;

                        return true;
                    }
                }
            }
        } else {
            if (isset($_SERVER[$strElem]) && is_string($value = $_SERVER[$strElem]) && (trim($value) !== '')) {
                $strBuffer = $value;

                return true;
            }
        }

        return false;
    }

    /**
     * @param string $strProgram
     *
     * @return null|string
     */
    public function findProgram($strProgram) {
        $pathParts = pathinfo($strProgram);
        if (empty($pathParts['basename'])) {
            return null;
        }

        $arrPath = [];
        $isWindows = $this->isWindows();

        if (empty($pathParts['dirname']) || ($pathParts['dirname'] == '.')) {
            if ($isWindows && empty($pathParts['extension'])) {
                $strProgram .= '.exe';
                $pathParts = pathinfo($strProgram);
            }
            $serverpath = '';
            if ($isWindows) {
                if ($this->readEnv('Path', $serverpath)) {
                    $arrPath = preg_split('/;/', $serverpath, -1, PREG_SPLIT_NO_EMPTY);
                }
            } else {
                if ($this->readEnv('PATH', $serverpath)) {
                    $arrPath = preg_split('/:/', $serverpath, -1, PREG_SPLIT_NO_EMPTY);
                }
            }
            $config = $this->config();
            if (($config->getUnameo() === 'Android') && !empty($arrPath)) {
                array_push($arrPath, '/system/bin');
            }
            if (is_string($config->getAddPaths())) {
                if (preg_match(CServer::ARRAY_EXP, $config->getAddPaths())) {
                    $arrPath = array_merge(eval($config->getAddPaths()), $arrPath);
                } else {
                    $arrPath = array_merge([$config->getAddPaths()], $arrPath);
                }
            }
        } else {
            array_push($arrPath, $pathParts['dirname']);
            $strProgram = $pathParts['basename'];
        }

        if (empty($arrPath) && !$isWindows) {
            $os = $this->getOS();
            if ($os == 'Android') {
                array_push($arrPath, '/system/bin');
            } else {
                array_push($arrPath, '/bin', '/sbin', '/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/local/sbin');
            }
        }

        $exceptPath = '';
        $windir = '';
        if ($isWindows && $this->readEnv('WinDir', $windir)) {
            foreach ($arrPath as $strPath) {
                if ((strtolower($strPath) == $windir . '\\system32') && is_dir($windir . '\\SysWOW64')) {
                    if (is_dir($windir . '\\sysnative')) {
                        $exceptPath = $windir . '\\sysnative';
                    } else {
                        $exceptPath = $windir . '\\SysWOW64';
                    }
                    array_push($arrPath, $exceptPath);

                    break;
                }
            }
        } elseif ($this->getOS() == 'Android') {
            $exceptPath = '/system/bin';
        }

        $separator = $isWindows ? '\\' : '/';
        foreach ($arrPath as $strPath) {
            $strPath = rtrim($strPath, $separator);
            $strProgramPath = $strPath . $separator . $strProgram;
            if (($strPath !== $exceptPath) && !@is_dir($strPath)) {
                continue;
            }
            if (is_executable($strProgramPath)) {
                return $strProgramPath;
            }
        }

        return null;
    }

    /**
     * @param string $strFileName
     *
     * @return bool
     */
    public function fileExists($strFileName) {
        if ($this->isRemote()) {
            $output = trim($this->runCommand('test -e ' . escapeshellarg($strFileName) . ' && echo 1 || echo 0'));

            return $output === '1';
        }

        return @file_exists($strFileName);
    }

    /**
     * @param string $strFileName
     * @param string &$strRet
     * @param int    $intLines
     * @param int    $intBytes
     * @param bool   $booErrorRep
     *
     * @return bool
     */
    public function rfts($strFileName, &$strRet, $intLines = 0, $intBytes = 4096, $booErrorRep = true) {
        if ($this->isRemote()) {
            $output = '';
            $this->ssh->run('cat ' . $strFileName, function ($line) use (&$output) {
                $output .= $line;
            });

            $strRet = $output;

            return !cstr::contains($output, ['No such file or directory']);
        }

        $strFile = '';
        $intCurLine = 1;
        if (!@file_exists($strFileName)) {
            if ($booErrorRep) {
                CServer::error()->addError('file_exists(' . $strFileName . ')', 'the file does not exist on your machine');
            }

            return false;
        }
        if (!is_readable($strFileName)) {
            if ($booErrorRep) {
                CServer::error()->addError('fopen(' . $strFileName . ')', 'file permission error');
            }

            return false;
        }
        $fd = fopen($strFileName, 'r');
        if (!$fd) {
            if ($booErrorRep) {
                CServer::error()->addError('fopen(' . $strFileName . ')', 'file can not read by phpsysinfo');
            }

            return false;
        }
        while (!feof($fd)) {
            $strFile .= fgets($fd, $intBytes);
            if ($intLines > 0 && $intCurLine >= $intLines) {
                break;
            }
            $intCurLine++;
        }
        fclose($fd);
        $strRet = $strFile;

        return true;
    }

    /**
     * @param string $strProgramname
     * @param string $strArgs
     * @param string &$strBuffer
     * @param bool   $booErrorRep
     * @param int    $timeout
     *
     * @return bool
     */
    public function executeProgram($strProgramname, $strArgs, &$strBuffer, $booErrorRep = true, $timeout = 30) {
        if ($this->isRemote()) {
            $output = '';
            $command = $strProgramname . ' ' . $strArgs;
            $this->ssh->run($command, function ($line) use (&$output) {
                $output .= $line;
            });
            $strBuffer = $output;

            return true;
        }

        $strSet = '';
        if (!$this->isWindows() && preg_match('/^([^=]+=[^ \t]+)[ \t]+(.*)$/', $strProgramname, $strmatch)) {
            $strSet = $strmatch[1] . ' ';
            $strProgramname = $strmatch[2];
        }

        $strProgram = $this->findProgram($strProgramname);
        if (!$strProgram) {
            if ($booErrorRep) {
                CServer::error()->addError('find_program("' . $strProgramname . '")', 'program not found on the machine');
            }

            return false;
        }
        if (preg_match('/\s/', $strProgram)) {
            $strProgram = '"' . $strProgram . '"';
        }

        $config = $this->config();
        if (!$this->isWindows() && is_string($config->getSudoCommands())) {
            if (preg_match(CServer::ARRAY_EXP, $config->getSudoCommands())) {
                $sudocommands = eval($config->getSudoCommands());
            } else {
                $sudocommands = [$config->getSudoCommands()];
            }
            if (in_array($strProgramname, $sudocommands)) {
                $sudoProgram = $this->findProgram('sudo');
                if (!$sudoProgram) {
                    if ($booErrorRep) {
                        CServer::error()->addError('find_program("sudo")', 'program not found on the machine');
                    }

                    return false;
                }
                if (preg_match('/\s/', $sudoProgram)) {
                    $strProgram = '"' . $sudoProgram . '" ' . $strProgram;
                } else {
                    $strProgram = $sudoProgram . ' ' . $strProgram;
                }
            }
        }

        if ($strArgs) {
            $arrArgs = preg_split('/ /', $strArgs, -1, PREG_SPLIT_NO_EMPTY);
            for ($i = 0, $cnt = count($arrArgs); $i < $cnt; $i++) {
                if ($arrArgs[$i] == '|') {
                    $strNewcmd = $this->findProgram($arrArgs[$i + 1]);
                    $strArgs = preg_replace("/\| " . $arrArgs[$i + 1] . '/', '| "' . $strNewcmd . '"', $strArgs);
                }
            }
            $strArgs = ' ' . $strArgs;
        }

        $strBuffer = '';
        $strError = '';

        return $this->procOpen($strSet . $strProgram . $strArgs, $strBuffer, $strError, $booErrorRep, $timeout);
    }

    /**
     * @param string $dfParam
     * @param bool   $getInodes
     *
     * @return array
     */
    public function df($dfParam = '', $getInodes = true) {
        $arrResult = [];
        $mountParm = [];
        $mount = '';

        if ($this->executeProgram('mount', '', $mount, $this->config()->isDebug())) {
            $mount = preg_split("/\n/", $mount, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($mount as $mountLine) {
                if (preg_match("/(\S+) on ([\S ]+) type (.*) \((.*)\)/", $mountLine, $buf)) {
                    $parm = ['mountpoint' => trim($buf[2]), 'fstype' => $buf[3], 'name' => $buf[1]];
                    if (CServer_Storage::SHOW_MOUNT_OPTION) {
                        $parm['options'] = $buf[4];
                    }
                    $mountParm[] = $parm;
                } elseif (preg_match("/(\S+) is (.*) mounted on (\S+) \(type (.*)\)/", $mountLine, $buf)) {
                    $parm = ['mountpoint' => trim($buf[3]), 'fstype' => $buf[4], 'name' => $buf[1]];
                    if (CServer_Storage::SHOW_MOUNT_OPTION) {
                        $parm['options'] = $buf[2];
                    }
                    $mountParm[] = $parm;
                } elseif (preg_match("/(\S+) (.*) on (\S+) \((.*)\)/", $mountLine, $buf)) {
                    $parm = ['mountpoint' => trim($buf[3]), 'fstype' => $buf[2], 'name' => $buf[1]];
                    if (CServer_Storage::SHOW_MOUNT_OPTION) {
                        $parm['options'] = $buf[4];
                    }
                    $mountParm[] = $parm;
                } elseif (preg_match("/(\S+) on ([\S ]+) \((\S+)(,\s(.*))?\)/", $mountLine, $buf)) {
                    $parm = ['mountpoint' => trim($buf[2]), 'fstype' => $buf[3], 'name' => $buf[1]];
                    if (CServer_Storage::SHOW_MOUNT_OPTION) {
                        $parm['options'] = isset($buf[5]) ? $buf[5] : '';
                    }
                    $mountParm[] = $parm;
                }
            }
        } elseif ($this->rfts('/etc/mtab', $mount)) {
            $mount = preg_split("/\n/", $mount, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($mount as $mountLine) {
                if (preg_match("/(\S+) (\S+) (\S+) (\S+) ([0-9]+) ([0-9]+)/", $mountLine, $buf)) {
                    $mountPoint = preg_replace('/\\\\040/i', ' ', $buf[2]);
                    $parm = ['mountpoint' => $mountPoint, 'fstype' => $buf[3], 'name' => $buf[1]];
                    if (CServer_Storage::SHOW_MOUNT_OPTION) {
                        $parm['options'] = $buf[4];
                    }
                    $mountParm[] = $parm;
                }
            }
        }

        $dfInodes = [];
        $df = '';
        if ($this->executeProgram('df', '-k ' . $dfParam, $df, $this->config()->isDebug()) && ($df !== '')) {
            $df = preg_split("/\n/", $df, -1, PREG_SPLIT_NO_EMPTY);
            if ($getInodes && CServer_Storage::SHOW_INODES) {
                $df2 = '';
                if ($this->executeProgram('df', '-i ' . $dfParam, $df2, $this->config()->isDebug())) {
                    $df2 = preg_split("/\n/", $df2, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($df2 as $df2Line) {
                        if (preg_match("/^(\S+).*\s([0-9]+)%/", $df2Line, $inodeBuf)) {
                            $dfInodes[$inodeBuf[1]] = $inodeBuf[2];
                        }
                    }
                }
            }
            foreach ($df as $dfLine) {
                $dfBuf1 = preg_split("/(\%\s)/", $dfLine, 3);
                if (count($dfBuf1) < 2) {
                    continue;
                }
                if (preg_match("/(.*)(\s+)(([0-9]+)(\s+)([0-9]+)(\s+)([\-0-9]+)(\s+)([0-9]+)$)/", $dfBuf1[0], $dfBuf2)) {
                    $dfBuf = (count($dfBuf1) == 3)
                        ? [$dfBuf2[1], $dfBuf2[4], $dfBuf2[6], $dfBuf2[8], $dfBuf2[10], $dfBuf1[2]]
                        : [$dfBuf2[1], $dfBuf2[4], $dfBuf2[6], $dfBuf2[8], $dfBuf2[10], $dfBuf1[1]];
                    if (count($dfBuf) == 6) {
                        $dfBuf[5] = trim($dfBuf[5]);
                        $dev = new CServer_Device_Disk();
                        $dev->setName(trim($dfBuf[0]));
                        if ($dfBuf[2] < 0) {
                            $dev->setTotal($dfBuf[3] * 1024);
                            $dev->setUsed($dfBuf[3] * 1024);
                        } else {
                            $dev->setTotal($dfBuf[1] * 1024);
                            $dev->setUsed($dfBuf[2] * 1024);
                            if ($dfBuf[3] > 0) {
                                $dev->setFree($dfBuf[3] * 1024);
                            }
                        }
                        if (CServer_Storage::SHOW_MOUNT_POINT) {
                            $dev->setMountPoint($dfBuf[5]);
                        }

                        $this->applyMountOptions($dev, $mountParm, trim($dfBuf[0]), $dfBuf[5]);

                        if ($getInodes && CServer_Storage::SHOW_INODES && isset($dfInodes[trim($dfBuf[0])])) {
                            $dev->setPercentInodesUsed($dfInodes[trim($dfBuf[0])]);
                        }
                        $arrResult[] = $dev;
                    }
                }
            }
        } else {
            foreach ($mountParm as $mp) {
                $total = disk_total_space($mp['mountpoint']);
                if (($mp['fstype'] != 'none') && ($total > 0)) {
                    $dev = new CServer_Device_Disk();
                    $dev->setName($mp['name']);
                    $dev->setFsType($mp['fstype']);
                    if (CServer_Storage::SHOW_MOUNT_POINT) {
                        $dev->setMountPoint($mp['mountpoint']);
                    }
                    $dev->setTotal($total);
                    $free = disk_free_space($mp['mountpoint']);
                    if ($free > 0) {
                        $dev->setFree($free);
                    } else {
                        $free = 0;
                    }
                    if ($total > $free) {
                        $dev->setUsed($total - $free);
                    }
                    if (CServer_Storage::SHOW_MOUNT_OPTION) {
                        $dev->setOptions($this->stripMountCredentials($mp['options']));
                    }
                    $arrResult[] = $dev;
                }
            }
        }

        return $arrResult;
    }

    /**
     * @param string $cmd
     * @param string &$strBuffer
     * @param string &$strError
     * @param bool   $booErrorRep
     * @param int    $timeout
     *
     * @return bool
     */
    public function procOpen($cmd, &$strBuffer, &$strError, $booErrorRep = true, $timeout = 30) {
        $pipes = [];
        if ($this->isRemote()) {
            $this->ssh->run($cmd, function ($line) use (&$strBuffer) {
                $strBuffer .= $line;
            });
            $strBuffer = trim($strBuffer);

            return true;
        }

        $process = null;
        $config = $this->config();
        if ($config->isModePopen()) {
            if ($this->isWindows()) {
                $process = $pipes[1] = popen($cmd . ' 2>nul', 'r');
            } else {
                $process = $pipes[1] = popen($cmd . ' 2>/dev/null', 'r');
            }
        } else {
            $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = proc_open($cmd, $descriptorspec, $pipes);
        }

        if (!is_resource($process)) {
            if ($booErrorRep) {
                CServer::error()->addError($cmd, "\nOpen process error");
            }

            return false;
        }

        $te = $this->timeoutfgets($pipes, $strBuffer, $strError, $timeout);

        if ($config->isModePopen()) {
            $returnValue = pclose($pipes[1]);
        } else {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if ($te) {
                proc_terminate($process);
                $returnValue = 0;
            } else {
                $returnValue = proc_close($process);
            }
        }

        $strError = trim($strError);
        $strBuffer = trim($strBuffer);

        if (!empty($strError)) {
            if ($booErrorRep) {
                CServer::error()->addError($cmd, $strError . "\nReturn value: " . $returnValue);
            }

            return $returnValue == 0;
        }

        return true;
    }

    /**
     * @param array  $pipes
     * @param string &$out
     * @param string &$err
     * @param int    $timeout
     *
     * @return bool
     */
    private function timeoutfgets($pipes, &$out, &$err, $timeout) {
        $w = null;
        $e = null;
        $te = false;
        $pipe2 = !$this->config()->isModePopen();

        while (!(feof($pipes[1]) && (!$pipe2 || feof($pipes[2])))) {
            $read = $pipe2 ? [$pipes[1], $pipes[2]] : [$pipes[1]];
            $n = stream_select($read, $w, $e, $timeout);

            if ($n === false) {
                error_log('stream_select: failed !');

                break;
            } elseif ($n === 0) {
                error_log('stream_select: timeout expired !');
                $te = true;

                break;
            }

            foreach ($read as $r) {
                if ($r == $pipes[1]) {
                    $out .= fread($r, 4096);
                } elseif (feof($pipes[1]) && $pipe2 && ($r == $pipes[2])) {
                    $err .= fread($r, 4096);
                }
            }
        }

        return $te;
    }

    /**
     * @param array  $mountParm
     * @param string $devName
     * @param string $mountPoint
     *
     * @return void
     */
    private function applyMountOptions(CServer_Device_Disk $dev, $mountParm, $devName, $mountPoint) {
        $found = false;
        foreach ($mountParm as $mp) {
            if (($mp['name'] === $devName) && ($mp['mountpoint'] === $mountPoint)) {
                $dev->setFsType($mp['fstype']);
                if (CServer_Storage::SHOW_MOUNT_OPTION && (trim(carr::get($mp, 'options', '')) !== '')) {
                    $dev->setOptions($this->stripMountCredentials($mp['options']));
                }
                $found = true;

                break;
            }
        }
        if (!$found) {
            foreach ($mountParm as $mp) {
                if ($mp['mountpoint'] === $mountPoint) {
                    $dev->setFsType($mp['fstype']);
                    if (CServer_Storage::SHOW_MOUNT_OPTION && (trim(carr::get($mp, 'options', '')) !== '')) {
                        $dev->setOptions($this->stripMountCredentials($mp['options']));
                    }

                    break;
                }
            }
        }
        if (!$found && $dev->getFsType() === null) {
            $dev->setFsType('unknown');
        }
    }

    /**
     * @param string $options
     *
     * @return string
     */
    private function stripMountCredentials($options) {
        if (!CServer_Storage::SHOW_MOUNT_CREDENTIALS) {
            $options = preg_replace('/(^guest,)|(^guest$)|(,guest$)/i', '', $options);
            $options = preg_replace('/,guest,/i', ',', $options);
            $options = preg_replace('/(^user=[^,]*,)|(^user=[^,]*$)|(,user=[^,]*$)/i', '', $options);
            $options = preg_replace('/,user=[^,]*,/i', ',', $options);
            $options = preg_replace('/(^username=[^,]*,)|(^username=[^,]*$)|(,username=[^,]*$)/i', '', $options);
            $options = preg_replace('/,username=[^,]*,/i', ',', $options);
            $options = preg_replace('/(^password=[^,]*,)|(^password=[^,]*$)|(,password=[^,]*$)/i', '', $options);
            $options = preg_replace('/,password=[^,]*,/i', ',', $options);
        }

        return $options;
    }
}
