<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Pembungkus fail2ban-client pada sebuah server.
 */
class CServer_Fail2ban {
    /**
     * @var CServer_Server
     */
    protected $server;

    /**
     * @var null|string
     */
    protected $sudoPrefix;

    public function __construct(CServer_Server $server) {
        $this->server = $server;
    }

    /**
     * @return CServer_Server
     */
    public function getServer() {
        return $this->server;
    }

    /**
     * @param array|string $command
     *
     * @return string
     */
    protected function run($command) {
        return $this->server->runCommand($command);
    }

    /**
     * Awalan agar perintah berjalan dengan hak yang cukup.
     *
     * @return string '' bila sudah root atau sudo tidak tersedia
     */
    protected function sudoPrefix() {
        if ($this->sudoPrefix !== null) {
            return $this->sudoPrefix;
        }
        $this->sudoPrefix = strpos($this->probePrivilege(), 'SUDO') !== false ? 'sudo -n ' : '';

        return $this->sudoPrefix;
    }

    /**
     * @return string ROOT, SUDO, atau NONE
     */
    protected function probePrivilege() {
        return trim($this->run(
            'if [ "$(id -u)" = "0" ]; then echo ROOT;'
            . ' elif sudo -n true >/dev/null 2>&1; then echo SUDO;'
            . ' else echo NONE; fi'
        ));
    }

    /**
     * Hak akun SSH: root, sudo, atau none.
     *
     * Diperiksa dengan `sudo -n` karena perintah di kelas ini berjalan tanpa
     * terminal — sudo yang masih meminta kata sandi sama saja dengan tanpa hak.
     *
     * @return string
     */
    public function getPrivilegeLevel() {
        $probe = $this->probePrivilege();
        if (strpos($probe, 'ROOT') !== false) {
            return 'root';
        }

        return strpos($probe, 'SUDO') !== false ? 'sudo' : 'none';
    }

    /**
     * @return bool
     */
    public function canManage() {
        return $this->getPrivilegeLevel() != 'none';
    }

    /**
     * @return string
     */
    public function getSshUser() {
        return trim($this->run('whoami'));
    }

    /**
     * Alamat asal koneksi SSH ini, yaitu alamat pengelola sebagaimana dilihat
     * server — dan karenanya alamat yang akan diblokir fail2ban bila salah.
     *
     * Dibaca dari server, bukan dari konfigurasi, supaya tetap benar walau
     * pengelolanya di belakang NAT atau alamatnya berubah.
     *
     * @return null|string
     */
    public function getManagerIp() {
        $raw = trim($this->run('echo "$SSH_CONNECTION" | awk ' . escapeshellarg('{print $1}')));

        return static::isValidIp($raw) ? $raw : null;
    }

    /**
     * @return bool
     */
    public function isInstalled() {
        return trim($this->run('command -v fail2ban-client >/dev/null 2>&1 && echo ADA || echo TIDAK')) === 'ADA';
    }

    /**
     * @return null|string
     */
    public function getVersion() {
        $raw = trim($this->run('command -v fail2ban-client >/dev/null 2>&1 && fail2ban-client --version 2>/dev/null | head -1'));
        if (strlen($raw) == 0) {
            return null;
        }

        return trim(str_ireplace('fail2ban-client', '', $raw));
    }

    /**
     * @return string active, inactive, atau unknown
     */
    public function getServiceStatus() {
        $unit = $this->server->distro()->getServiceUnit('fail2ban');
        $status = trim($this->run('systemctl is-active ' . escapeshellarg($unit) . ' 2>/dev/null || true'));
        if (in_array($status, ['active', 'inactive', 'failed'], true)) {
            return $status == 'failed' ? 'inactive' : $status;
        }

        return 'unknown';
    }

    /**
     * @param string $ip
     *
     * @return bool
     */
    public static function isValidIp($ip) {
        return filter_var((string) $ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * @param string $jail
     *
     * @return bool
     */
    public static function isValidJail($jail) {
        return (bool) preg_match('/^[A-Za-z0-9._-]{1,64}$/', (string) $jail);
    }

    /**
     * Seluruh keadaan fail2ban dalam satu perintah.
     *
     * Memanggil status per jail satu per satu berarti satu koneksi baru untuk
     * tiap jail, dan halaman pemanggilnya menunggu sebanyak itu.
     *
     * @return array
     */
    public function getOverview() {
        $prefix = $this->sudoPrefix();
        $unit = escapeshellarg($this->server->distro()->getServiceUnit('fail2ban'));
        $script = 'echo "[managerip]"; echo "$SSH_CONNECTION" | awk ' . escapeshellarg('{print $1}') . ';'
            . ' echo "[user]"; whoami;'
            . ' echo "[privilege]"; if [ "$(id -u)" = "0" ]; then echo ROOT; elif sudo -n true >/dev/null 2>&1; then echo SUDO; else echo NONE; fi;'
            . ' echo "[installed]"; command -v fail2ban-client >/dev/null 2>&1 && echo ADA || echo TIDAK;'
            . ' echo "[status]"; systemctl is-active ' . $unit . ' 2>/dev/null || true;'
            . ' echo "[version]"; command -v fail2ban-client >/dev/null 2>&1 && fail2ban-client --version 2>/dev/null | head -1;'
            . ' JAILS=$(' . $prefix . 'fail2ban-client status 2>/dev/null | sed -n "s/.*Jail list:[[:space:]]*//p" | tr "," " ");'
            . ' echo "[jail]"; echo "$JAILS";'
            . ' for j in $JAILS; do'
            . '   echo "[jailstatus $j]"; ' . $prefix . 'fail2ban-client status "$j" 2>/dev/null;'
            . '   echo "[jailignore $j]"; ' . $prefix . 'fail2ban-client get "$j" ignoreip 2>/dev/null;'
            . '   echo "[jailparam $j]";'
            . '   for p in bantime findtime maxretry; do'
            . '     printf "%s=%s\n" "$p" "$(' . $prefix . 'fail2ban-client get "$j" $p 2>/dev/null | tr -d "\r\n")";'
            . '   done;'
            . ' done';

        $section = '';
        $buffer = [];
        foreach (explode("\n", (string) $this->run($script)) as $line) {
            if (preg_match('/^\[([a-z]+)(?:\s+([A-Za-z0-9._-]+))?\]$/', trim($line), $match)) {
                $section = $match[1] . (isset($match[2]) ? ' ' . $match[2] : '');
                $buffer[$section] = '';

                continue;
            }
            if (strlen($section) > 0) {
                $buffer[$section] .= $line . PHP_EOL;
            }
        }

        $privilege = trim((string) carr::get($buffer, 'privilege'));
        $status = trim((string) carr::get($buffer, 'status'));
        $data = [
            'managerIp' => static::isValidIp(trim((string) carr::get($buffer, 'managerip')))
                ? trim((string) carr::get($buffer, 'managerip')) : null,
            'sshUser' => trim((string) carr::get($buffer, 'user')),
            'privilege' => $privilege == 'ROOT' ? 'root' : ($privilege == 'SUDO' ? 'sudo' : 'none'),
            'installed' => trim((string) carr::get($buffer, 'installed')) === 'ADA',
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : ($status == 'failed' ? 'inactive' : 'unknown'),
            'version' => trim(str_ireplace('fail2ban-client', '', (string) carr::get($buffer, 'version'))),
            'jail' => [],
        ];

        foreach (preg_split('/\s+/', trim((string) carr::get($buffer, 'jail'))) as $jail) {
            $jail = trim($jail);
            if (strlen($jail) == 0 || !static::isValidJail($jail)) {
                continue;
            }
            $data['jail'][$jail] = array_merge(
                $this->parseJailStatus($jail, (string) carr::get($buffer, 'jailstatus ' . $jail)),
                ['ignoreIp' => $this->parseIgnoreList((string) carr::get($buffer, 'jailignore ' . $jail))],
                $this->parseJailParam((string) carr::get($buffer, 'jailparam ' . $jail))
            );
        }

        return $data;
    }

    /**
     * @return string[]
     */
    public function getJailList() {
        $output = $this->run($this->sudoPrefix() . 'fail2ban-client status 2>/dev/null');
        if (strpos($output, 'Jail list') === false) {
            return [];
        }
        $raw = trim((string) carr::get(explode('Jail list:', $output), 1));
        $list = [];
        foreach (explode(',', $raw) as $jail) {
            $jail = trim($jail);
            if (strlen($jail) > 0 && static::isValidJail($jail)) {
                $list[] = $jail;
            }
        }

        return $list;
    }

    /**
     * @param string $jail
     *
     * @return array
     */
    public function getJailStatus($jail) {
        if (!static::isValidJail($jail)) {
            return $this->parseJailStatus($jail, '');
        }

        return $this->parseJailStatus(
            $jail,
            $this->run($this->sudoPrefix() . 'fail2ban-client status ' . escapeshellarg($jail) . ' 2>/dev/null')
        );
    }

    /**
     * @param string $jail
     * @param string $output
     *
     * @return array
     */
    protected function parseJailStatus($jail, $output) {
        $data = [
            'jail' => $jail,
            'currentlyFailed' => 0,
            'totalFailed' => 0,
            'currentlyBanned' => 0,
            'totalBanned' => 0,
            'bannedIp' => [],
        ];
        foreach (explode("\n", (string) $output) as $line) {
            $line = trim($line, " \t-|`");
            if (preg_match('/Currently failed:\s*(\d+)/i', $line, $match)) {
                $data['currentlyFailed'] = (int) $match[1];
            } elseif (preg_match('/Total failed:\s*(\d+)/i', $line, $match)) {
                $data['totalFailed'] = (int) $match[1];
            } elseif (preg_match('/Currently banned:\s*(\d+)/i', $line, $match)) {
                $data['currentlyBanned'] = (int) $match[1];
            } elseif (preg_match('/Total banned:\s*(\d+)/i', $line, $match)) {
                $data['totalBanned'] = (int) $match[1];
            } elseif (preg_match('/Banned IP list:\s*(.*)$/i', $line, $match)) {
                foreach (preg_split('/\s+/', trim($match[1])) as $ip) {
                    if (static::isValidIp($ip)) {
                        $data['bannedIp'][] = $ip;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Riwayat blokir dari log fail2ban.
     *
     * Status jail hanya menyimpan alamat yang sedang diblokir, jadi alamat yang
     * masa blokirnya sudah lewat hanya tersisa di log.
     *
     * @param int $limit baris log terakhir yang dibaca
     *
     * @return array jail, ip, banCount, lastBan, lastUnban, banned
     */
    public function getBanHistory($limit = 5000) {
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = 1;
        }
        //berkas dibaca urut waktu ubah supaya yang terpotong tail adalah yang
        //terlama, dan zcat -f menerima log yang sudah dirotasi maupun yang belum;
        //kalau logtarget-nya systemd tidak ada berkas sama sekali, jadi jurnal dibaca
        $unit = escapeshellarg($this->server->distro()->getServiceUnit('fail2ban'));
        $script = 'FILES=$(ls -1tr /var/log/fail2ban.log* 2>/dev/null);'
            . ' if [ -n "$FILES" ]; then zcat -f $FILES 2>/dev/null;'
            . ' else journalctl -u ' . $unit . ' --no-pager -o short-iso 2>/dev/null; fi'
            . ' | grep -aE "\] (Ban|Unban) " | tail -n ' . $limit;

        $output = $this->run($this->sudoPrefix() . 'bash -c ' . escapeshellarg($script) . ' 2>/dev/null');

        $history = [];
        foreach (explode("\n", (string) $output) as $line) {
            //dua bentuk waktu: berkas log fail2ban (spasi, milidetik) dan
            //jurnal systemd (T, zona waktu)
            if (!preg_match(
                '/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(?:[.,]\d+)?(?:[+-]\d{2}:?\d{2})?'
                    . '\s+.*\[([A-Za-z0-9._-]+)\]\s+(Ban|Unban)\s+(\S+)/',
                trim($line),
                $match
            )) {
                continue;
            }
            list(, $date, $clock, $jail, $action, $ip) = $match;
            $time = $date . ' ' . $clock;
            if (!static::isValidIp($ip) || !static::isValidJail($jail)) {
                continue;
            }
            $key = $jail . '|' . $ip;
            if (!isset($history[$key])) {
                $history[$key] = [
                    'jail' => $jail,
                    'ip' => $ip,
                    'banCount' => 0,
                    'lastBan' => null,
                    'lastUnban' => null,
                    'banned' => false,
                ];
            }
            if ($action == 'Ban') {
                $history[$key]['banCount']++;
                $history[$key]['lastBan'] = $time;
                $history[$key]['banned'] = true;
            } else {
                $history[$key]['lastUnban'] = $time;
                $history[$key]['banned'] = false;
            }
        }

        //terbaru di atas: yang baru saja diblokir adalah yang sedang dicari
        usort($history, function ($a, $b) {
            return strcmp(
                (string) max($b['lastBan'], $b['lastUnban']),
                (string) max($a['lastBan'], $a['lastUnban'])
            );
        });

        return $history;
    }

    /**
     * Ambang blokir sebuah jail.
     *
     * Nilainya dibiarkan apa adanya: fail2ban menjawab dalam detik, tapi versi
     * lama bisa mengembalikan bentuk seperti `15m`.
     *
     * @param string $output
     *
     * @return array bantime, findtime, maxretry
     */
    protected function parseJailParam($output) {
        $data = ['bantime' => null, 'findtime' => null, 'maxretry' => null];
        foreach (explode("\n", (string) $output) as $line) {
            if (preg_match('/^(bantime|findtime|maxretry)=(.+)$/', trim($line), $match)) {
                $value = trim($match[2]);
                $data[$match[1]] = strlen($value) > 0 ? $value : null;
            }
        }

        return $data;
    }

    /**
     * @param string $jail
     *
     * @return string[]
     */
    public function getIgnoreList($jail) {
        if (!static::isValidJail($jail)) {
            return [];
        }

        return $this->parseIgnoreList(
            $this->run($this->sudoPrefix() . 'fail2ban-client get ' . escapeshellarg($jail) . ' ignoreip 2>/dev/null')
        );
    }

    /**
     * @param string $output
     *
     * @return string[]
     */
    protected function parseIgnoreList($output) {
        $list = [];
        foreach (preg_split('/[\s,|]+/', (string) $output) as $item) {
            $item = trim($item, "`- \t");
            //ignoreip boleh berisi CIDR, jadi bagian setelah garis miring dilepas saat divalidasi
            $ip = carr::first(explode('/', $item));
            if (strlen($item) > 0 && static::isValidIp($ip)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @param string $jail
     * @param string $ip
     *
     * @return string
     */
    public function unban($jail, $ip) {
        return $this->clientSet($jail, 'unbanip', $ip);
    }

    /**
     * @param string $jail
     * @param string $ip
     *
     * @return string
     */
    public function ban($jail, $ip) {
        return $this->clientSet($jail, 'banip', $ip);
    }

    /**
     * Berlaku sampai fail2ban dimulai ulang; untuk menetap, alamatnya harus
     * ikut ditulis di jail.local.
     *
     * @param string $jail
     * @param string $ip
     *
     * @return string
     */
    public function addIgnoreIp($jail, $ip) {
        return $this->clientSet($jail, 'addignoreip', $ip);
    }

    /**
     * @param string $jail
     * @param string $ip
     *
     * @return string
     */
    public function removeIgnoreIp($jail, $ip) {
        return $this->clientSet($jail, 'delignoreip', $ip);
    }

    /**
     * @param string $jail
     * @param string $action
     * @param string $ip
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    protected function clientSet($jail, $action, $ip) {
        if (!static::isValidJail($jail)) {
            throw new InvalidArgumentException('Invalid jail name: ' . $jail);
        }
        if (!static::isValidIp($ip)) {
            throw new InvalidArgumentException('Invalid IP address: ' . $ip);
        }

        return $this->run($this->sudoPrefix() . 'fail2ban-client set ' . escapeshellarg($jail)
            . ' ' . $action . ' ' . escapeshellarg($ip) . ' 2>&1');
    }

    /**
     * Isi jail.local bawaan: ambangnya longgar supaya salah ketik tidak
     * langsung memblokir orang yang berhak.
     *
     * Alamat pengelola selalu ikut dikecualikan. Tanpa itu, jalur yang dipakai
     * untuk melepas blokir adalah jalur yang bisa ikut terblokir.
     *
     * @param string[] $ignoreIpList
     *
     * @return string
     */
    public function getDefaultJailLocal(array $ignoreIpList = []) {
        $ignore = ['127.0.0.1/8', '::1'];
        $managerIp = $this->getManagerIp();
        if ($managerIp !== null) {
            $ignore[] = $managerIp;
        }
        foreach ($ignoreIpList as $ip) {
            if (static::isValidIp($ip) && !in_array($ip, $ignore, true)) {
                $ignore[] = $ip;
            }
        }

        return "[DEFAULT]\n"
            . "bantime = 15m\n"
            . "findtime = 10m\n"
            . "maxretry = 6\n"
            . 'ignoreip = ' . implode(' ', $ignore) . "\n\n"
            . "[sshd]\n"
            . "enabled = true\n";
    }

    /**
     * Perintah pemasangan sesuai distribusi.
     *
     * fail2ban tidak ada di repositori bawaan RHEL, jadi EPEL dipasang lebih
     * dulu di sana.
     *
     * @return array
     */
    public function getInstallCommand() {
        $distro = $this->server->distro();
        $command = [];
        if ($distro->getFamily() == 'rhel') {
            $command[] = $distro->getPackageManager() . ' install -y epel-release';
        }

        return array_merge($command, $distro->getInstallCommand('fail2ban'));
    }

    /**
     * Pasang fail2ban lalu nyalakan.
     *
     * jail.local hanya ditulis bila belum ada, supaya pemasangan ulang tidak
     * menimpa setelan yang sudah disesuaikan.
     *
     * @param string[] $ignoreIpList alamat yang tidak boleh diblokir
     *
     * @return array errCode, errMessage, output, version
     */
    public function install(array $ignoreIpList = []) {
        if (!$this->canManage()) {
            return [
                'errCode' => 1,
                'errMessage' => 'SSH user (' . $this->getSshUser() . ') is not root and has no passwordless sudo.',
                'output' => '',
                'version' => null,
            ];
        }
        $distro = $this->server->distro();
        if ($distro->getFamily() == 'unknown') {
            return [
                'errCode' => 1,
                'errMessage' => 'Unknown distribution, refusing to install.',
                'output' => '',
                'version' => null,
            ];
        }

        $prefix = $this->sudoPrefix();
        $unit = escapeshellarg($distro->getServiceUnit('fail2ban'));
        $writeJail = 'printf ' . escapeshellarg($this->getDefaultJailLocal($ignoreIpList)) . ' > /etc/fail2ban/jail.local';

        $command = [];
        foreach ($this->getInstallCommand() as $row) {
            $command[] = $prefix . $row;
        }
        $command[] = 'if [ -f /etc/fail2ban/jail.local ]; then echo "jail.local exists, kept";'
            . ' else ' . $prefix . 'bash -c ' . escapeshellarg($writeJail) . ' && echo "jail.local written"; fi';
        $command[] = $prefix . 'systemctl enable --now ' . $unit . ' 2>&1';

        try {
            $output = $this->run($command);
        } catch (Exception $ex) {
            return ['errCode' => 1, 'errMessage' => $ex->getMessage(), 'output' => '', 'version' => null];
        }

        $version = $this->getVersion();
        if ($version === null) {
            return [
                'errCode' => 1,
                'errMessage' => 'Install command finished but fail2ban is still not detected.',
                'output' => $output,
                'version' => null,
            ];
        }
        if ($this->getServiceStatus() != 'active') {
            return [
                'errCode' => 1,
                'errMessage' => 'fail2ban is installed but the service is not active.',
                'output' => $output,
                'version' => $version,
            ];
        }

        return ['errCode' => 0, 'errMessage' => '', 'output' => $output, 'version' => $version];
    }
}
