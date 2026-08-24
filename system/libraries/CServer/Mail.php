<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Pemeriksa layanan surel pada sebuah server.
 *
 * Keberadaan paketnya saja tidak cukup untuk menyebut sebuah mesin "server
 * mail": hampir semua distribusi memasang MTA lokal (Postfix atau sendmail)
 * hanya agar cron dapat mengirim laporan ke root. Yang membedakan adalah
 * apakah ia benar-benar mendengarkan di antarmuka publik pada porta surel.
 * Karena itu kelas ini memeriksa keduanya dan melaporkannya terpisah.
 */
class CServer_Mail {
    /**
     * @var CServer_Server
     */
    protected $server;

    /**
     * Porta yang menandakan layanan surel sungguhan, beserta perannya.
     *
     * @var array
     */
    protected static $portRole = [
        25 => 'smtp',
        465 => 'smtps',
        587 => 'submission',
        110 => 'pop3',
        995 => 'pop3s',
        143 => 'imap',
        993 => 'imaps',
    ];

    /**
     * Penyedia relai yang dikenali dari nama hostnya.
     *
     * @var array
     */
    protected static $relayProvider = [
        'mailjet' => 'Mailjet',
        'sendgrid' => 'SendGrid',
        'amazonaws' => 'Amazon SES',
        'mailgun' => 'Mailgun',
        'postmark' => 'Postmark',
        'sparkpost' => 'SparkPost',
        'gmail' => 'Gmail',
        'office365' => 'Microsoft 365',
        'outlook' => 'Microsoft 365',
        'zoho' => 'Zoho',
        'brevo' => 'Brevo',
        'sendinblue' => 'Brevo',
    ];

    /**
     * @var null|string
     */
    private $sudoPrefix;

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
     * Penyedia relai yang didukung beserta bentuk kredensialnya.
     *
     * Keempatnya berbeda bukan hanya di nama host: SendGrid memakai kata
     * `apikey` harfiah sebagai nama pengguna dan API key sebagai kata sandi,
     * sedangkan Mailjet dan MailerSend memakai sepasang nilai. Brevo memakai
     * sepasang nilai juga, tetapi REST API-nya menuntut kunci ketiga yang
     * berbeda — itulah `apiKeyLabel`, yang null bila penyedianya tidak
     * membedakan keduanya. Perbedaan itu jadi sumber kebingungan yang lazim,
     * jadi dijelaskan di sini alih-alih dibiarkan ditebak.
     *
     * @return array
     */
    public static function providerList() {
        return [
            'mailjet' => [
                'label' => 'Mailjet',
                'host' => 'in-v3.mailjet.com',
                'port' => 587,
                'portList' => [587, 588, 465, 25, 2525],
                'usernameLabel' => 'API Key',
                'passwordLabel' => 'Secret Key',
                'usernameFixed' => null,
                'apiKeyLabel' => null,
                'docUrl' => 'https://app.mailjet.com/account/apikeys',
                'steps' => [
                    'Masuk ke akun Mailjet, buka menu Account Settings.',
                    'Pilih "API Key Management (REST API, SMTP relay)".',
                    'Salin API Key sebagai nama pengguna, dan Secret Key sebagai kata sandi.',
                    'Keduanya sama dengan kunci REST API — memegangnya berarti dapat mengirim'
                        . ' atas nama domain Anda, jadi jangan dibagikan.',
                ],
            ],
            'sendgrid' => [
                'label' => 'SendGrid',
                'host' => 'smtp.sendgrid.net',
                'port' => 587,
                'portList' => [587, 465, 25, 2525],
                'usernameLabel' => 'Nama Pengguna',
                'passwordLabel' => 'API Key',
                //SendGrid selalu memakai kata "apikey" harfiah sebagai nama
                //pengguna; yang berubah hanya kata sandinya
                'usernameFixed' => 'apikey',
                'apiKeyLabel' => null,
                'docUrl' => 'https://app.sendgrid.com/settings/api_keys',
                'steps' => [
                    'Masuk ke SendGrid, buka Settings lalu API Keys.',
                    'Buat API Key baru dengan izin "Mail Send".',
                    'Nama penggunanya selalu kata <code>apikey</code> — bukan alamat surel Anda.',
                    'API Key yang baru dibuat hanya ditampilkan sekali; simpan sebelum menutup halamannya.',
                ],
            ],
            'mailersend' => [
                'label' => 'MailerSend',
                'host' => 'smtp.mailersend.net',
                'port' => 587,
                'portList' => [587, 2525],
                'usernameLabel' => 'SMTP Username',
                'passwordLabel' => 'SMTP Password',
                'usernameFixed' => null,
                'apiKeyLabel' => null,
                'docUrl' => 'https://app.mailersend.com/domains',
                'steps' => [
                    'Masuk ke MailerSend, buka Domains lalu pilih domain yang sudah terverifikasi.',
                    'Buka tab SMTP, lalu buat SMTP user baru.',
                    'Salin Username dan Password yang muncul.',
                    'Domainnya harus terverifikasi lebih dulu; SMTP user tidak dapat dibuat sebelum itu.',
                ],
            ],
            'brevo' => [
                'label' => 'Brevo',
                'host' => 'smtp-relay.brevo.com',
                'port' => 587,
                'portList' => [587, 2525, 465, 25],
                'usernameLabel' => 'SMTP Login',
                'passwordLabel' => 'SMTP Key',
                'usernameFixed' => null,
                //satu-satunya penyedia di sini yang REST API-nya memakai kunci
                //berbeda dari kredensial SMTP-nya
                'apiKeyLabel' => 'API Key',
                'apiKeyDocUrl' => 'https://app.brevo.com/settings/keys/api',
                'docUrl' => 'https://app.brevo.com/settings/keys/smtp',
                'steps' => [
                    'Masuk ke Brevo, buka menu SMTP & API lalu tab SMTP.',
                    'Salin nilai Login sebagai nama pengguna — sebuah alamat surel, bukan nama merek.',
                    'Buat SMTP key baru lalu salin nilainya sebagai kata sandi.',
                    'SMTP key berbeda dari API key di tab sebelahnya: kunci API ditolak relai SMTP,'
                        . ' dan sebaliknya kunci SMTP ditolak REST API.',
                ],
            ],
        ];
    }

    /**
     * @param string $provider
     *
     * @return null|array
     */
    public static function provider($provider) {
        return carr::get(self::providerList(), (string) $provider);
    }

    /**
     * Nama pengguna relai yang sedang dipakai.
     *
     * Hanya bagian pengguna yang dikembalikan — ia pengenal, bukan rahasia.
     * Kata sandinya dibaca terpisah lewat getRelaySecret(), supaya tidak ikut
     * terbawa setiap kali halaman dibuka.
     *
     * @return null|string
     */
    public function getRelayUsername() {
        $rows = $this->readRelayCredentialLine();
        if ($rows === null) {
            return null;
        }

        return carr::get($rows, 'username');
    }

    /**
     * Kata sandi relai. Dipanggil hanya saat pengguna memintanya secara
     * eksplisit.
     *
     * @return null|string
     */
    public function getRelaySecret() {
        $rows = $this->readRelayCredentialLine();
        if ($rows === null) {
            return null;
        }

        return carr::get($rows, 'password');
    }

    /**
     * Membaca satu baris kredensial dari berkas peta SASL.
     *
     * @return null|array username, password
     */
    protected function readRelayCredentialLine() {
        $file = trim((string) $this->run(
            $this->sudo() . 'postconf -h smtp_sasl_password_maps 2>/dev/null'
        ));
        //nilainya berbentuk "hash:/etc/postfix/sasl_passwd"
        if (strpos($file, ':') !== false) {
            $file = trim(substr($file, strpos($file, ':') + 1));
        }
        if (strlen($file) == 0) {
            return null;
        }

        $content = (string) $this->run($this->sudo() . 'cat ' . escapeshellarg($file) . ' 2>/dev/null');
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (strlen($line) == 0 || substr($line, 0, 1) == '#') {
                continue;
            }
            //bentuknya "[host]:port user:pass"; pemisah pertama pada bagian
            //kredensial yang menentukan, karena kata sandi dapat memuat titik dua
            $section = preg_split('/\s+/', $line, 2);
            $credential = trim((string) carr::get($section, 1));
            if (strlen($credential) == 0) {
                continue;
            }
            $pos = strpos($credential, ':');
            if ($pos === false) {
                continue;
            }

            return [
                'username' => substr($credential, 0, $pos),
                'password' => substr($credential, $pos + 1),
            ];
        }

        return null;
    }

    /**
     * Awalan sudo bila koneksinya bukan root.
     *
     * @return string
     */
    protected function sudo() {
        if ($this->sudoPrefix !== null) {
            return $this->sudoPrefix;
        }
        $probe = trim($this->run(
            'if [ "$(id -u)" = "0" ]; then echo ROOT;'
            . ' elif sudo -n true >/dev/null 2>&1; then echo SUDO;'
            . ' else echo NONE; fi'
        ));
        $this->sudoPrefix = strpos($probe, 'SUDO') !== false ? 'sudo -n ' : '';

        return $this->sudoPrefix;
    }

    /**
     * Keadaan layanan surel di server ini.
     *
     * Seluruhnya diambil dalam satu perintah: tiap perjalanan SSH mahal, dan
     * pemeriksaan ini dijalankan untuk banyak server sekaligus.
     *
     * @return array mta, mda, imap, listening, public, isMailServer
     */
    public function inspect() {
        //tiap kandidat dipisah koma, bukan ditempel: postfix memasang binari
        //`sendmail` sebagai kompatibilitas, sehingga keduanya cocok sekaligus
        //dan tanpa pemisah hasilnya terbaca "postfixsendmail"
        $output = (string) $this->run(
            //sudo diputuskan di dalam perintah yang sama, bukan lewat probe
            //tersendiri: doveconf hanya dapat dibaca root, dan satu perjalanan
            //SSH tambahan lebih mahal daripada tiga baris shell ini
            'if [ "$(id -u)" = "0" ]; then S="";'
            . ' elif sudo -n true >/dev/null 2>&1; then S="sudo -n";'
            . ' else S=""; fi;'
            . ' echo "MTA:$(for b in postfix exim sendmail; do'
            . ' command -v $b >/dev/null 2>&1 && printf "%s," "$b"; done)";'
            . ' echo "IMAP:$(for b in dovecot cyrus-master; do'
            . ' command -v $b >/dev/null 2>&1 && printf "%s," "$b"; done)";'
            //ss lebih umum tersedia daripada netstat pada distribusi baru,
            //tetapi netstat masih ada di yang lama — dua-duanya dicoba
            //relayhost dibaca sekalian: penyedia cloud lazim memblokir porta 25
            //keluar, sehingga server surel di sana harus merelai lewat pihak
            //ketiga — dan tanpa keterangan ini halaman surel tampak seolah
            //server mengirim sendiri
            . ' echo "RELAY:$(postconf -h relayhost 2>/dev/null'
            . ' || grep -m1 -E "^relayhost[[:space:]]*=" /etc/postfix/main.cf 2>/dev/null | cut -d= -f2)";'
            . ' echo "RELAYAUTH:$(postconf -h smtp_sasl_auth_enable 2>/dev/null)";'
            //agen yang benar-benar menaruh surat ke kotaknya. -x meminta
            //postconf memekarkan rujukan seperti `$virtual_transport`, yang
            //lazim dipakai pada pemasangan mailbox virtual; postfix lama tidak
            //mengenal -x sehingga nilainya dibaca apa adanya sebagai cadangan
            . ' for p in virtual_transport mailbox_transport local_transport'
            . ' mailbox_command home_mailbox virtual_mailbox_domains; do'
            . ' echo "PF_$p:$(postconf -xh $p 2>/dev/null || postconf -h $p 2>/dev/null)"; done;'
            . ' echo "DOVE_protocols:$($S doveconf -h protocols 2>/dev/null)";'
            . ' echo "DOVE_mail_location:$($S doveconf -h mail_location 2>/dev/null)";'
            //penyaring spam dibaca dari tiga sisi sekaligus, karena terpasang
            //belum tentu berarti dipakai: binarinya ada, prosesnya berjalan,
            //dan yang menentukan — apakah MTA benar-benar mengirim surat
            //kepadanya lewat milter atau content_filter.
            //
            //Jumlah surat yang sudah diperiksa **tidak** ikut di sini: `rspamc
            //stat` menunggu dua detik penuh sebelum menjawab, dan itu saja
            //cukup mendorong seluruh pemeriksaan ini melewati batas koneksi 10
            //detik — yang gagalnya tanpa suara, keluarannya sekadar terpotong
            //sehingga MTA dan MDA pun ikut terbaca kosong. Ambil terpisah lewat
            //getSpamScannedCount() bila memang dibutuhkan.
            . ' echo "SPAM_bin:$(for b in rspamd spamassassin spamd amavisd-new amavisd; do'
            . ' command -v $b >/dev/null 2>&1 && printf "%s," "$b"; done)";'
            . ' echo "SPAM_running:$(for b in rspamd spamd amavisd; do'
            . ' pgrep -x $b >/dev/null 2>&1 && printf "%s," "$b"; done)";'
            . ' echo "PF_smtpd_milters:$(postconf -h smtpd_milters 2>/dev/null)";'
            . ' echo "PF_content_filter:$(postconf -h content_filter 2>/dev/null)";'
            . ' echo "LISTEN_START";'
            . ' (ss -ltnp 2>/dev/null || netstat -ltnp 2>/dev/null) | awk \'{print $4" "$NF}\';'
            . ' echo "LISTEN_END"'
        );

        $mtaList = [];
        $imapList = [];
        $relayRaw = '';
        $relayAuth = false;
        $delivery = [];
        $spam = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (strpos($line, 'MTA:') === 0) {
                $mtaList = self::splitList(substr($line, 4));
            } elseif (strpos($line, 'IMAP:') === 0) {
                $imapList = self::splitList(substr($line, 5));
            } elseif (strpos($line, 'RELAY:') === 0) {
                $relayRaw = trim(substr($line, 6));
            } elseif (strpos($line, 'RELAYAUTH:') === 0) {
                $relayAuth = strtolower(trim(substr($line, 10))) === 'yes';
            } elseif (strpos($line, 'SPAM_') === 0) {
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $spam[substr($line, 0, $separator)] = trim(substr($line, $separator + 1));
                }
            } elseif (strpos($line, 'PF_') === 0 || strpos($line, 'DOVE_') === 0) {
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $delivery[substr($line, 0, $separator)] = trim(substr($line, $separator + 1));
                }
            }
        }
        //postfix dan exim mendahului sendmail: keduanya menyediakan binari
        //sendmail sendiri, jadi sendmail hanya berarti bila ia satu-satunya
        $mta = carr::get($mtaList, 0);
        $imap = carr::get($imapList, 0);

        $listening = $this->parseListening($output);

        //hanya porta yang terikat ke antarmuka publik yang dihitung; 127.0.0.1
        //berarti MTA lokal untuk keperluan sistem, bukan layanan surel
        $publicMap = [];
        foreach ($listening as $port => $address) {
            foreach ($address as $a) {
                if ($this->isPublicAddress($a)) {
                    $publicMap[$port] = carr::get(self::$portRole, $port);

                    break;
                }
            }
        }

        $relay = self::parseRelay($relayRaw, $relayAuth);
        $mda = self::parseDelivery($delivery, $mta);
        $spamFilter = self::parseSpamFilter($spam, $delivery);

        return [
            'relay' => $relay,
            'mta' => $mta,
            'mta_list' => $mtaList,
            'mda' => carr::get($mda, 'name'),
            'mda_detail' => $mda,
            'imap' => $imap,
            'imap_list' => $imapList,
            'listening' => $listening,
            'public' => $publicMap,
            'isMailServer' => count($publicMap) > 0,
            'spam' => carr::get($spamFilter, 'name'),
            'spam_state' => carr::get($spamFilter, 'state'),
            'spam_detail' => $spamFilter,
        ];
    }

    /**
     * Penyaring spam: ada, berjalan, dan — yang menentukan — benar-benar dipakai.
     *
     * Tiga keadaan dibedakan dengan sengaja, karena keadaan tengahnya yang
     * paling menyesatkan: sebuah penyaring dapat terpasang dan layanannya
     * berjalan, tetapi MTA tidak pernah mengirim surat kepadanya. Dari luar
     * server itu tampak terlindungi; kenyataannya tidak ada satu surat pun yang
     * diperiksa, sementara memorinya tetap terpakai. Persis itu yang ditemukan
     * di `mail.cresenity.com` pada 2026-08-02 — rspamd berjalan, `smtpd_milters`
     * hanya memuat opendkim, dan `rspamc stat` mencatat nol surat.
     *
     * Karena itu yang dijadikan penentu adalah `smtpd_milters`/`content_filter`,
     * bukan keberadaan binari atau layanannya.
     *
     * @param array $value    keluaran SPAM_* yang sudah dipilah
     * @param array $delivery keluaran PF_* yang sudah dipilah
     *
     * @return array name, state, installed, running, wired, scanned, hint
     */
    protected static function parseSpamFilter(array $value, array $delivery) {
        $installed = self::splitList((string) carr::get($value, 'SPAM_bin'));
        $runningName = self::splitList((string) carr::get($value, 'SPAM_running'));
        $milter = (string) carr::get($delivery, 'PF_smtpd_milters');
        $contentFilter = (string) carr::get($delivery, 'PF_content_filter');

        //porta bawaan tiap penyaring, itulah yang dicari di daftar milter
        $portMap = [
            'rspamd' => ['11332', '11333'],
            'spamassassin' => ['783'],
            'spamd' => ['783'],
            'amavisd-new' => ['10024'],
            'amavisd' => ['10024'],
        ];

        $name = carr::get($installed, 0);
        foreach ($installed as $candidate) {
            //yang sedang berjalan lebih berarti daripada yang sekadar terpasang
            if (in_array($candidate, $runningName) || in_array($candidate . 'd', $runningName)) {
                $name = $candidate;

                break;
            }
        }

        $wired = false;
        $haystack = strtolower($milter . ' ' . $contentFilter);
        foreach ($installed as $candidate) {
            if (strlen($haystack) == 0) {
                break;
            }
            if (strpos($haystack, strtolower($candidate)) !== false) {
                $wired = true;

                break;
            }
            foreach (carr::get($portMap, $candidate, []) as $port) {
                if (strpos($haystack, ':' . $port) !== false) {
                    $wired = true;

                    break 2;
                }
            }
        }

        $state = 'none';
        $hint = null;
        if (count($installed) == 0) {
            $hint = 'Tidak ada penyaring spam sama sekali. Surat masuk diterima apa adanya.';
        } elseif (!$wired) {
            $state = 'idle';
            $hint = 'Terpasang dan berjalan, tetapi MTA tidak pernah mengirim surat kepadanya —'
                . ' tidak ada satu pun surat yang diperiksa. Sambungkan lewat smtpd_milters.';
        } else {
            $state = 'active';
        }

        return [
            'name' => $name,
            'state' => $state,
            'installed' => $installed,
            'running' => $runningName,
            'wired' => $wired,
            'milter' => $milter,
            'content_filter' => $contentFilter,
            'hint' => $hint,
        ];
    }

    /**
     * Berapa surat yang sudah diperiksa penyaring spam.
     *
     * Dipisah dari `inspect()` dengan sengaja: `rspamc stat` menunggu dua detik
     * penuh sebelum menjawab, dan menyelipkannya ke dalam pemeriksaan gabungan
     * membuat seluruhnya melewati batas koneksi 10 detik.
     *
     * Angka nol adalah jawaban yang paling berarti di sini — penyaring yang
     * berjalan tetapi tidak pernah dikirimi surat.
     *
     * @return null|int null bila tidak dapat dibaca
     */
    public function getSpamScannedCount() {
        $output = trim((string) $this->run(
            'rspamc stat 2>/dev/null | sed -n "s/^Messages scanned: *//p" | head -1'
        ));

        return preg_match('/^\d+$/', $output) === 1 ? (int) $output : null;
    }

    /**
     * Agen yang menaruh surat ke kotaknya (MDA).
     *
     * MTA hanya mengangkut surat antar server; yang benar-benar menulisnya ke
     * kotak surat adalah agen lain, dan itulah yang menentukan surat jatuh ke
     * mana. Pada pemasangan mailbox virtual — yang lazim untuk melayani banyak
     * domain — postfix menyerahkannya ke Dovecot lewat LMTP, sehingga baik
     * "postfix" maupun "dovecot" pada halaman tidak menjawab pertanyaan itu.
     *
     * Urutannya mengikuti postfix sendiri: `virtual_transport` berlaku untuk
     * domain di `virtual_mailbox_domains`, sedangkan surat untuk domain lokal
     * (`mydestination`) mengikuti `mailbox_transport`, lalu `mailbox_command`,
     * dan bila keduanya kosong ditangani `local(8)` bawaan postfix.
     *
     * @param array       $value keluaran postconf dan doveconf yang sudah dipilah
     * @param null|string $mta   MTA yang terpasang, null bila tidak ada
     *
     * @return array name, transport, source, mailbox, protocolList
     */
    protected static function parseDelivery(array $value, $mta = null) {
        $virtualTransport = (string) carr::get($value, 'PF_virtual_transport');
        $virtualDomain = (string) carr::get($value, 'PF_virtual_mailbox_domains');
        $mailboxTransport = (string) carr::get($value, 'PF_mailbox_transport');
        $mailboxCommand = (string) carr::get($value, 'PF_mailbox_command');
        $localTransport = (string) carr::get($value, 'PF_local_transport');

        $transport = '';
        $source = null;
        if (strlen($virtualDomain) > 0 && strlen($virtualTransport) > 0) {
            $transport = $virtualTransport;
            $source = 'virtual_transport';
        } elseif (strlen($mailboxTransport) > 0) {
            $transport = $mailboxTransport;
            $source = 'mailbox_transport';
        } elseif (strlen($mailboxCommand) > 0) {
            $transport = $mailboxCommand;
            $source = 'mailbox_command';
        } elseif (strlen($localTransport) > 0 && $localTransport != 'local') {
            $transport = $localTransport;
            $source = 'local_transport';
        }

        //tanpa MTA tidak ada yang menyerahkan surat sama sekali; menyebut
        //"local(8)" di server seperti itu justru mengarang penyerahan yang
        //tidak ada
        $name = $mta === null ? null : self::deliveryName($transport);
        //kotak surat: doveconf bila Dovecot yang menyerahkannya, selain itu
        //home_mailbox postfix (Maildir/ atau nama berkas mbox)
        $mailbox = (string) carr::get($value, 'DOVE_mail_location');
        if (strlen($mailbox) == 0) {
            $mailbox = (string) carr::get($value, 'PF_home_mailbox');
        }

        return [
            'name' => $name,
            'transport' => $transport,
            'source' => $source,
            'mailbox' => strlen($mailbox) > 0 ? $mailbox : null,
            'protocolList' => self::splitList(str_replace(' ', ',', (string) carr::get($value, 'DOVE_protocols'))),
            'isVirtual' => strlen($virtualDomain) > 0,
        ];
    }

    /**
     * Nama terbaca dari sebuah nilai transport atau perintah pengiriman.
     *
     * @param string $transport
     *
     * @return string
     */
    protected static function deliveryName($transport) {
        $transport = trim((string) $transport);
        if (strlen($transport) == 0) {
            //postfix tetap mengirimkannya sendiri; ini bukan "tidak ada MDA"
            return 'postfix local(8)';
        }
        if (stripos($transport, 'dovecot-lmtp') !== false
            || (stripos($transport, 'lmtp') === 0 && stripos($transport, 'dovecot') !== false)
        ) {
            return 'Dovecot LMTP';
        }
        if (stripos($transport, 'dovecot-lda') !== false
            || stripos($transport, 'dovecot/deliver') !== false
            || $transport == 'dovecot'
        ) {
            return 'Dovecot LDA';
        }
        if (stripos($transport, 'lmtp') === 0) {
            return 'LMTP';
        }
        if (stripos($transport, 'procmail') !== false) {
            return 'procmail';
        }
        if (stripos($transport, 'maildrop') !== false) {
            return 'maildrop';
        }
        if (stripos($transport, 'cyrus') !== false) {
            return 'Cyrus';
        }
        if ($transport == 'virtual') {
            return 'postfix virtual(8)';
        }
        if ($transport == 'local') {
            return 'postfix local(8)';
        }

        return $transport;
    }

    /**
     * Menguji sebuah konfigurasi relai sebelum diterapkan.
     *
     * Empat langkah berurutan, dan berhenti pada kegagalan pertama: nama host
     * dapat diresolusi, portanya dapat dihubungi, TLS terbentuk, lalu
     * autentikasi SMTP diterima. Menguji sampai autentikasi itu yang membedakan
     * "konfigurasinya masuk akal" dari "konfigurasinya benar-benar bekerja" —
     * salah kunci hanya ketahuan di langkah terakhir, dan tanpa uji ini
     * kegagalannya baru muncul diam-diam saat ada surat yang gagal terkirim.
     *
     * Kredensial dikirim lewat berkas sementara bermode 600, bukan lewat
     * argumen perintah: argumen terlihat di daftar proses oleh pengguna lain
     * di server yang sama.
     *
     * @param string $host
     * @param int    $port
     * @param string $username
     * @param string $password
     *
     * @return array errCode, errMessage, steps
     */
    public function testRelay($host, $port, $username, $password) {
        $host = trim((string) $host);
        $port = (int) $port;
        $steps = [];

        if (strlen($host) == 0 || $port <= 0) {
            return ['errCode' => 1, 'errMessage' => 'Host atau porta relai belum diisi', 'steps' => $steps];
        }

        //1. resolusi nama
        $ip = trim($this->run(
            'getent hosts ' . escapeshellarg($host) . " 2>/dev/null | head -1 | awk '{print \$1}'"
        ));
        $steps[] = ['label' => 'Resolusi DNS', 'ok' => strlen($ip) > 0,
            'detail' => strlen($ip) > 0 ? $host . ' -> ' . $ip : 'nama host tidak dapat diresolusi'];
        if (strlen($ip) == 0) {
            return ['errCode' => 1, 'errMessage' => 'Nama host ' . $host . ' tidak dapat diresolusi dari server ini', 'steps' => $steps];
        }

        //2. porta terbuka
        $tcp = trim($this->run(
            'timeout 8 bash -c "</dev/tcp/' . $host . '/' . $port . '" >/dev/null 2>&1 && echo OK || echo GAGAL'
        ));
        $tcpOk = strpos($tcp, 'OK') !== false;
        $steps[] = ['label' => 'Koneksi TCP', 'ok' => $tcpOk,
            'detail' => $tcpOk ? 'porta ' . $port . ' terbuka' : 'porta ' . $port . ' tidak dapat dihubungi'];
        if (!$tcpOk) {
            return [
                'errCode' => 1,
                'errMessage' => 'Porta ' . $port . ' pada ' . $host . ' tidak dapat dihubungi dari server ini.'
                    . ' Penyedia cloud lazim memblokir porta 25 keluar — coba 587 atau 2525.',
                'steps' => $steps,
            ];
        }

        //3 & 4. TLS lalu autentikasi SMTP
        $result = $this->smtpAuthProbe($host, $port, $username, $password);
        $steps[] = ['label' => 'TLS', 'ok' => carr::get($result, 'tls'),
            'detail' => carr::get($result, 'tls') ? 'terbentuk' : 'gagal membentuk TLS'];
        $steps[] = ['label' => 'Autentikasi SMTP', 'ok' => carr::get($result, 'auth'),
            'detail' => carr::get($result, 'detail')];

        if (!carr::get($result, 'auth')) {
            return [
                'errCode' => 1,
                'errMessage' => 'Autentikasi ke relai ditolak: ' . carr::get($result, 'detail'),
                'steps' => $steps,
            ];
        }

        return ['errCode' => 0, 'errMessage' => '', 'steps' => $steps];
    }

    /**
     * Melakukan jabat tangan SMTP sampai AUTH LOGIN.
     *
     * @param string $host
     * @param int    $port
     * @param string $username
     * @param string $password
     *
     * @return array tls, auth, detail
     */
    protected function smtpAuthProbe($host, $port, $username, $password) {
        //porta 465 memakai TLS sejak awal; sisanya menaikkannya lewat STARTTLS
        $tlsOption = ((int) $port === 465) ? '' : ' -starttls smtp';

        $script = "EHLO devcloud.local\r\nAUTH LOGIN\r\n"
            . base64_encode((string) $username) . "\r\n"
            . base64_encode((string) $password) . "\r\nQUIT\r\n";

        //berkas sementara bermode 600 supaya kredensial tidak muncul di daftar proses
        $file = '/tmp/.devcloud-smtp-' . bin2hex(random_bytes(6));
        $command = 'umask 077; printf %s ' . escapeshellarg($script) . ' > ' . $file . '; '
            . 'timeout 25 openssl s_client -quiet -crlf' . $tlsOption
            . ' -connect ' . escapeshellarg($host . ':' . $port) . ' < ' . $file . ' 2>&1; '
            . 'rm -f ' . $file;

        $output = (string) $this->run($command);

        $tls = stripos($output, 'CONNECTED') !== false
            || stripos($output, 'verify return') !== false
            || preg_match('/^220[\s-]/m', $output) === 1;

        if (preg_match('/^235[\s-]*(.*)$/m', $output, $m)) {
            return ['tls' => true, 'auth' => true, 'detail' => trim($m[1]) ?: 'diterima'];
        }
        if (preg_match('/^(535|534|530|454)[\s-]*(.*)$/m', $output, $m)) {
            return ['tls' => $tls, 'auth' => false, 'detail' => trim($m[1] . ' ' . $m[2])];
        }
        if (stripos($output, 'openssl: not found') !== false || stripos($output, 'command not found') !== false) {
            return ['tls' => false, 'auth' => false, 'detail' => 'openssl tidak tersedia di server ini'];
        }

        return [
            'tls' => $tls,
            'auth' => false,
            'detail' => cstr::limit(trim(preg_replace('/\s+/', ' ', $output)), 160) ?: 'tidak ada jawaban dari relai',
        ];
    }

    /**
     * Menerapkan konfigurasi relai pada Postfix.
     *
     * Menguji lebih dulu dan menolak menulis apa pun bila ujinya gagal:
     * mengganti relai dengan kredensial yang salah membuat seluruh surat keluar
     * mengendap di antrean, dan itu baru ketahuan berjam-jam kemudian.
     *
     * Berkas lama dicadangkan bertanggal sebelum ditimpa, dan Postfix hanya
     * di-reload — bukan restart — supaya sambungan yang sedang berjalan tidak
     * terputus.
     *
     * @param string $host
     * @param int    $port
     * @param string $username
     * @param string $password
     * @param bool   $skipTest hanya untuk pemanggil yang sudah menguji sendiri
     *
     * @return array errCode, errMessage, steps, output
     */
    public function applyRelay($host, $port, $username, $password, $skipTest = false) {
        $steps = [];
        if (!$skipTest) {
            $test = $this->testRelay($host, $port, $username, $password);
            $steps = carr::get($test, 'steps', []);
            if (carr::get($test, 'errCode') != 0) {
                return [
                    'errCode' => 1,
                    'errMessage' => carr::get($test, 'errMessage'),
                    'steps' => $steps,
                    'output' => '',
                ];
            }
        }

        $relayValue = '[' . trim((string) $host) . ']:' . (int) $port;
        $credentialLine = $relayValue . ' ' . $username . ':' . $password;
        $file = '/etc/postfix/sasl_passwd';
        $stamp = trim($this->run('date +%Y%m%d%H%M%S'));

        //umask 077 dipasang sebelum menulis: berkas ini memuat kredensial relai
        //dan tidak boleh terbaca pengguna lain walau sekejap
        $command = $this->sudo() . 'bash -c ' . escapeshellarg(
            'set -e; umask 077; '
            . '[ -f ' . $file . ' ] && cp -a ' . $file . ' ' . $file . '.bak-' . $stamp . '; '
            //format printf **wajib dikutip**. Tanpa kutip, shell membaca `\n`
            //sebagai huruf `n` yang di-escape, sehingga formatnya menjadi `%sn`
            //dan setiap kata sandi tertulis dengan huruf `n` menempel di
            //belakangnya — juga tanpa baris baru. Postfix lalu memakai kata
            //sandi yang salah satu karakter, dan penyedia menolaknya dengan 535
            //yang tidak menyebutkan sebab apa pun. Terjadi di produksi
            //2026-08-02 dan makan waktu lama untuk ditemukan, karena uji
            //kredensialnya berjalan **sebelum** penulisan sehingga selalu lulus.
            . 'printf ' . escapeshellarg('%s\n') . ' ' . escapeshellarg($credentialLine) . ' > ' . $file . '; '
            . 'chmod 600 ' . $file . '; '
            . 'postmap ' . $file . '; '
            . 'postconf -e ' . escapeshellarg('relayhost = ' . $relayValue) . '; '
            . 'postconf -e ' . escapeshellarg('smtp_sasl_auth_enable = yes') . '; '
            . 'postconf -e ' . escapeshellarg('smtp_sasl_password_maps = hash:' . $file) . '; '
            . 'postconf -e ' . escapeshellarg('smtp_sasl_security_options = noanonymous') . '; '
            . 'postconf -e ' . escapeshellarg('smtp_tls_security_level = may') . '; '
            . 'postfix reload'
        ) . ' 2>&1; echo "exit status $?"';

        $output = (string) $this->run($command);

        if (strpos($output, 'exit status 0') === false) {
            return [
                'errCode' => 1,
                'errMessage' => 'Konfigurasi lulus uji tetapi gagal diterapkan. Berkas lama dicadangkan sebagai '
                    . $file . '.bak-' . $stamp,
                'steps' => $steps,
                'output' => $output,
            ];
        }

        //dipastikan dari sisi Postfix sendiri, bukan sekadar dari kode keluar
        $currentRelay = trim($this->run($this->sudo() . 'postconf -h relayhost 2>/dev/null'));
        if (strpos($currentRelay, trim((string) $host)) === false) {
            return [
                'errCode' => 1,
                'errMessage' => 'Perintah berhasil tetapi relayhost belum berubah (terbaca: ' . $currentRelay . ')',
                'steps' => $steps,
                'output' => $output,
            ];
        }

        //Kredensial yang benar-benar tertulis diperiksa ulang, bukan dianggap
        //sama dengan yang diuji. Ujinya berjalan sebelum penulisan, jadi ia
        //tidak dapat menangkap kerusakan yang terjadi **saat** menulis — dan
        //satu karakter yang menempel karena kesalahan kutip pernah lolos persis
        //lewat celah itu. Yang dibandingkan sidik jarinya, dihitung di server,
        //sehingga kata sandinya tidak perlu melintas balik.
        $expected = sha1($credentialLine);
        $written = trim($this->run(
            $this->sudo() . 'sha1sum ' . $file . ' 2>/dev/null | cut -d" " -f1'
        ));
        $writtenLine = trim($this->run(
            $this->sudo() . 'tr -d "\\n" < ' . $file . ' | sha1sum | cut -d" " -f1'
        ));

        if (strlen($writtenLine) > 0 && $writtenLine !== $expected) {
            return [
                'errCode' => 1,
                'errMessage' => 'Kredensial tertulis tetapi isinya tidak sama dengan yang diuji.'
                    . ' Berkas lama dicadangkan sebagai ' . $file . '.bak-' . $stamp,
                'steps' => $steps,
                'output' => $output . "\n" . 'sha1 berkas: ' . $written,
            ];
        }

        return ['errCode' => 0, 'errMessage' => '', 'steps' => $steps, 'output' => $output];
    }

    /**
     * Penyaring baku untuk log surat, dari yang paling sering ditanyakan.
     *
     * @return array kunci => [label, pattern]
     */
    public static function logFilterList() {
        return [
            'sent' => ['label' => 'Terkirim', 'pattern' => 'status=sent'],
            'deferred' => ['label' => 'Tertahan', 'pattern' => 'status=deferred'],
            'bounced' => ['label' => 'Dikembalikan', 'pattern' => 'status=bounced'],
            'reject' => ['label' => 'Ditolak', 'pattern' => 'NOQUEUE: reject'],
            'auth' => ['label' => 'Autentikasi gagal', 'pattern' => 'authentication failed'],
            'dkim' => ['label' => 'DKIM', 'pattern' => 'opendkim'],
            'spam' => ['label' => 'Penyaring spam', 'pattern' => 'rspamd\\|spamd\\|milter'],
        ];
    }

    /**
     * Baris terakhir log surat, disaring.
     *
     * Log surat adalah satu-satunya tempat yang tahu apa yang **sungguh**
     * terjadi pada sebuah surat: diterima, ditandatangani, diserahkan ke relai,
     * ditolak, atau diam-diam tertahan. Tanpa ini jawabannya hanya dapat dicari
     * lewat SSH.
     *
     * Kata kuncinya dilewatkan `escapeshellarg` dan `grep -F` — dicocokkan
     * harfiah, bukan sebagai ekspresi reguler, sehingga tanda titik atau kurung
     * pada nama domain tidak berubah arti dan tidak ada yang dapat disisipkan
     * ke perintahnya.
     *
     * @param null|string $keyword kata kunci bebas, mis. nama domain atau queue id
     * @param null|string $filter  kunci dari logFilterList()
     * @param int         $limit   berapa baris terakhir yang diambil
     *
     * @return array baris log, terbaru di bawah
     */
    public function getLogTail($keyword = null, $filter = null, $limit = 200) {
        $limit = (int) $limit;
        if ($limit < 1 || $limit > 2000) {
            $limit = 200;
        }

        $filterList = static::logFilterList();
        $pattern = carr::get($filterList, (string) $filter . '.pattern');

        //berkas yang diputar ikut dibaca, termasuk yang sudah dipadatkan —
        //pertanyaan tentang surat kerap menyangkut beberapa hari lalu
        $command = 'for f in /var/log/mail.log /var/log/maillog; do'
            . ' [ -f "$f" ] && cat "$f"; [ -f "$f.1" ] && cat "$f.1";'
            . ' for g in "$f".*.gz; do [ -f "$g" ] && zcat "$g" 2>/dev/null; done; done';

        if ($pattern !== null) {
            $command .= ' | grep -a ' . escapeshellarg($pattern);
        }
        if (strlen((string) $keyword) > 0) {
            $command .= ' | grep -aiF ' . escapeshellarg((string) $keyword);
        }
        $command .= ' | tail -n ' . $limit;

        $output = (string) $this->run($this->sudo() . 'bash -c ' . escapeshellarg($command));

        $line = [];
        foreach (explode("\n", $output) as $row) {
            $row = rtrim($row, "\r");
            if (strlen(trim($row)) > 0) {
                $line[] = $row;
            }
        }

        return $line;
    }

    /**
     * Ringkasan jumlah baris per jenis, untuk log yang sedang dilihat.
     *
     * @param null|string $keyword
     *
     * @return array kunci => jumlah
     */
    public function getLogSummary($keyword = null) {
        $summary = [];
        foreach (static::logFilterList() as $key => $meta) {
            $command = 'for f in /var/log/mail.log /var/log/maillog; do'
                . ' [ -f "$f" ] && cat "$f"; [ -f "$f.1" ] && cat "$f.1"; done'
                . ' | grep -a ' . escapeshellarg(carr::get($meta, 'pattern'));
            if (strlen((string) $keyword) > 0) {
                $command .= ' | grep -aiF ' . escapeshellarg((string) $keyword);
            }
            $command .= ' | wc -l';

            $summary[$key] = (int) trim($this->run($this->sudo() . 'bash -c ' . escapeshellarg($command)));
        }

        return $summary;
    }

    /**
     * Klien surel berbasis web yang terpasang di server ini.
     *
     * **Tidak** ikut di `inspect()`: penelusurannya memakan ~1,5 detik, dan
     * pemeriksaan gabungan itu sudah nyaris menyentuh batas koneksi 10 detik —
     * yang gagalnya tanpa suara, keluarannya sekadar terpotong.
     *
     * Yang dicari folder konfigurasi domainnya, bukan sekadar keberadaan
     * foldernya. RainLoop menyimpan dua folder bernama `domains`: yang hidup di
     * `data/_data_/_default_/domains`, dan satu lagi berisi contoh bawaan di
     * `v/<versi>/app/domains` yang tidak berpengaruh apa pun. Menyunting yang
     * kedua tidak akan mengubah perilaku, jadi yang ber-`/v/` dibuang.
     *
     * Roundcube tidak mengelola daftar domain seperti itu — ia tetap dilaporkan
     * agar terlihat, hanya tanpa daftar domain.
     *
     * @return array tiap entri: type, path, version, domain_path, domain_list
     */
    public function getWebmailList() {
        $output = (string) $this->run(
            'for r in /var/www /usr/share /srv /opt /home/*/public_html; do'
            . ' [ -d "$r" ] && find "$r" -maxdepth 3 -type d \\('
            . ' -iname rainloop -o -iname snappymail -o -iname roundcube -o -iname roundcubemail'
            . ' \\) 2>/dev/null; done | head -8'
        );

        //diurutkan dari path terpendek supaya folder induk diperiksa lebih dulu;
        //folder di dalamnya kemudian dilewati sebagai bagian dari pemasangan
        //yang sama — RainLoop punya `<akar>/rainloop` di dalam dirinya sendiri
        $candidate = [];
        foreach (explode("\n", $output) as $path) {
            $path = trim($path);
            if (strlen($path) > 0 && preg_match('#^/#', $path) === 1) {
                $candidate[] = $path;
            }
        }
        usort($candidate, function ($a, $b) {
            return strlen($a) - strlen($b);
        });

        $list = [];
        $seen = [];
        $accepted = [];
        foreach ($candidate as $path) {
            foreach ($accepted as $parent) {
                if (strpos($path, rtrim($parent, '/') . '/') === 0) {
                    continue 2;
                }
            }

            $name = strtolower(basename($path));
            $type = strpos($name, 'roundcube') === 0 ? 'roundcube' : $name;

            $domainPath = '';
            if ($type != 'roundcube') {
                $domainPath = trim($this->run(
                    'find ' . escapeshellarg($path) . ' -maxdepth 4 -type d -name domains 2>/dev/null'
                    . ' | grep -v "/v/" | head -1'
                ));
            }

            //satu pemasangan dapat terdeteksi lewat dua path (folder induk dan
            //folder aplikasinya); yang menentukan sama-tidaknya adalah folder
            //konfigurasi domainnya
            //RainLoop atau SnappyMail tanpa folder domain bukan pemasangan yang
            //dapat dipakai — biasanya folder aplikasi di dalam pemasangan lain
            if ($type != 'roundcube' && strlen($domainPath) == 0) {
                continue;
            }

            $key = strlen($domainPath) > 0 ? $domainPath : $path;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $accepted[] = $path;

            $version = trim($this->run(
                'cat ' . escapeshellarg($path) . '/data/VERSION 2>/dev/null'
                . ' || ls ' . escapeshellarg($path) . '/rainloop 2>/dev/null | head -1'
            ));

            $domainList = [];
            if (strlen($domainPath) > 0) {
                $raw = (string) $this->run('ls -1 ' . escapeshellarg($domainPath) . ' 2>/dev/null');
                foreach (explode("\n", $raw) as $file) {
                    $file = trim($file);
                    if (preg_match('/^(.+)\.(ini|alias)$/', $file, $match) === 1) {
                        $domainList[$match[1]] = $match[2];
                    }
                }
            }

            $list[] = array_merge([
                'type' => $type,
                'path' => $path,
                'version' => strlen($version) > 0 ? $version : null,
                'domain_path' => strlen($domainPath) > 0 ? $domainPath : null,
                'domain_list' => $domainList,
            ], $this->getWebmailAccess($path, $type));
        }

        return $list;
    }

    /**
     * Alamat dan login panel admin sebuah pemasangan webmail.
     *
     * Jalur berkasnya sendiri tidak memberi tahu alamat apa yang melayaninya,
     * jadi vhost yang menunjuk jalur itu dicari lebih dulu, baru namanya
     * dipetakan ke domain. Bila tidak ketemu, nilainya null - alamat yang salah
     * lebih buruk daripada alamat yang tidak ditampilkan.
     *
     * Kunci panel admin dibaca, bukan diasumsikan `?admin`: `admin_panel_key`
     * dapat diganti, dan pemasangan yang menggantinya justru yang paling perlu
     * ditampilkan alamatnya dengan benar.
     *
     * Kata sandinya tidak ikut - RainLoop dan SnappyMail menyimpannya sebagai
     * hash bcrypt, jadi tidak ada yang bisa dibaca dari sana.
     *
     * @param string $path
     * @param string $type
     *
     * @return array url, admin_url, admin_login, admin_enabled
     */
    protected function getWebmailAccess($path, $type) {
        $empty = ['url' => null, 'admin_url' => null, 'admin_login' => null, 'admin_enabled' => null];

        $quoted = escapeshellarg($path);
        $config = escapeshellarg($path . '/data/_data_/_default_/configs/application.ini');

        $output = (string) $this->run(
            'if [ "$(id -u)" = "0" ]; then S="";'
            . ' elif sudo -n true >/dev/null 2>&1; then S="sudo -n";'
            . ' else S=""; fi;'
            . ' P=' . $quoted . '; D="";'
            //LiteSpeed: berkas vhost menyebut jalurnya, dan nama foldernya
            //dipetakan ke domain di httpd_config.conf lewat baris `map`
            . ' VH=$($S grep -rlF "$P" /usr/local/lsws/conf/vhosts/*/vhconf.conf 2>/dev/null | head -1);'
            . ' if [ -n "$VH" ]; then N=$(basename $(dirname "$VH"));'
            . ' D=$($S grep -rhE "map[[:space:]]+$N[[:space:]]" /usr/local/lsws/conf/httpd_config.conf 2>/dev/null'
            . ' | head -1 | awk "{print \$3}" | cut -d, -f1); fi;'
            . ' if [ -z "$D" ]; then F=$($S grep -rlF "$P" /etc/nginx 2>/dev/null | head -1);'
            . ' [ -n "$F" ] && D=$($S grep -hE "^[[:space:]]*server_name" "$F" 2>/dev/null'
            . ' | head -1 | awk "{print \$2}" | tr -d ";"); fi;'
            . ' if [ -z "$D" ]; then F=$($S grep -rlF "$P" /etc/apache2 /etc/httpd 2>/dev/null | head -1);'
            . ' [ -n "$F" ] && D=$($S grep -hE "^[[:space:]]*ServerName" "$F" 2>/dev/null'
            . ' | head -1 | awk "{print \$2}"); fi;'
            . ' echo "DOMAIN:$D";'
            . ' C=' . $config . ';'
            . ' echo "LOGIN:$($S grep -E "^admin_login" "$C" 2>/dev/null | cut -d\" -f2)";'
            . ' echo "KEY:$($S grep -E "^admin_panel_key" "$C" 2>/dev/null | cut -d\" -f2)";'
            . ' echo "ALLOW:$($S grep -E "^allow_admin_panel" "$C" 2>/dev/null | awk "{print \$3}")";'
        );

        $parsed = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (strpos($line, ':') === false) {
                continue;
            }
            list($key, $value) = explode(':', $line, 2);
            $parsed[$key] = trim($value);
        }

        $domain = carr::get($parsed, 'DOMAIN');
        if (strlen((string) $domain) == 0) {
            return $empty;
        }

        $url = 'https://' . $domain . '/';
        $result = ['url' => $url, 'admin_url' => null, 'admin_login' => null, 'admin_enabled' => null];

        //roundcube tidak punya panel admin berbasis web
        if ($type == 'roundcube') {
            return $result;
        }

        $allow = strtolower((string) carr::get($parsed, 'ALLOW'));
        $result['admin_enabled'] = $allow == 'on' || $allow == '1' || $allow == 'true';
        $result['admin_login'] = strlen((string) carr::get($parsed, 'LOGIN')) > 0
            ? carr::get($parsed, 'LOGIN') : null;

        $key = carr::get($parsed, 'KEY');
        if (strlen((string) $key) == 0) {
            $key = 'admin';
        }
        $result['admin_url'] = $url . '?' . $key;

        return $result;
    }

    /**
     * Mendaftarkan sebuah domain ke klien surel berbasis web.
     *
     * Menambah domain di basis data server surel membuatnya menerima surat,
     * tetapi webmail punya daftar domainnya **sendiri** dan menolak login untuk
     * domain yang tidak ada di sana — dengan pesan yang tidak menyebut-nyebut
     * webmail sama sekali. Ditemukan di produksi 2026-08-02.
     *
     * Yang dibuat berkas `.alias`, bukan `.ini`: satu baris berisi nama domain
     * acuan, sehingga seluruh pengaturan IMAP/SMTP-nya mengikuti domain itu dan
     * tidak ada nilai yang perlu diduplikasi.
     *
     * Mengembalikan hasil per pemasangan; server tanpa webmail menghasilkan
     * daftar kosong, bukan galat — tidak semua server surel punya webmail.
     *
     * @param string      $domain
     * @param null|string $reference domain acuan; bila null dipilih otomatis
     *
     * @return array tiap entri: path, errCode, errMessage, reference
     */
    public function addWebmailDomain($domain, $reference = null) {
        $domain = strtolower(trim((string) $domain));
        $result = [];

        if (preg_match('/^[a-z0-9.-]+$/', $domain) !== 1) {
            return [['path' => null, 'errCode' => 1, 'reference' => null,
                'errMessage' => 'Nama domain tidak sah', ]];
        }

        foreach ($this->getWebmailList() as $webmail) {
            $domainPath = carr::get($webmail, 'domain_path');
            if ($domainPath === null) {
                continue;
            }

            $domainList = carr::get($webmail, 'domain_list', []);
            if (array_key_exists($domain, $domainList)) {
                $result[] = ['path' => $domainPath, 'errCode' => 0, 'reference' => null,
                    'errMessage' => 'sudah terdaftar', ];

                continue;
            }

            $target = $reference !== null ? $reference : static::pickWebmailReference($domainList);
            if ($target === null) {
                $result[] = ['path' => $domainPath, 'errCode' => 1, 'reference' => null,
                    'errMessage' => 'Tidak ada domain acuan yang dapat dipakai; buat satu berkas .ini dulu', ];

                continue;
            }

            $file = rtrim($domainPath, '/') . '/' . $domain . '.alias';
            //pemilik dan modenya mengikuti folder tempatnya berada — webmail
            //dijalankan sebagai pengguna web, dan berkas milik root di sana
            //tidak akan terbaca
            $owner = trim($this->run('stat -c %U:%G ' . escapeshellarg($domainPath) . ' 2>/dev/null'));
            $command = $this->sudo() . 'bash -c ' . escapeshellarg(
                'set -e; printf ' . escapeshellarg('%s\n') . ' ' . escapeshellarg($target)
                . ' > ' . escapeshellarg($file) . '; '
                . (strlen($owner) > 0 ? 'chown ' . escapeshellarg($owner) . ' ' . escapeshellarg($file) . '; ' : '')
                . 'chmod 644 ' . escapeshellarg($file)
            ) . ' 2>&1; echo "exit status $?"';

            $output = (string) $this->run($command);
            $ok = strpos($output, 'exit status 0') !== false;

            $result[] = [
                'path' => $file,
                'errCode' => $ok ? 0 : 1,
                'reference' => $target,
                'errMessage' => $ok ? '' : cstr::limit(trim($output), 160),
            ];
        }

        return $result;
    }

    /**
     * Memilih domain acuan untuk berkas `.alias`.
     *
     * Yang dipilih domain yang punya berkas `.ini` — hanya itu yang benar-benar
     * memuat pengaturan IMAP dan SMTP. Menunjuk ke sesama `.alias` akan
     * berantai dan tidak berujung pada konfigurasi mana pun.
     *
     * @param array $domainList domain => ini|alias
     *
     * @return null|string
     */
    protected static function pickWebmailReference(array $domainList) {
        //penyedia surel umum ikut terdaftar di RainLoop bawaan (gmail.com,
        //yahoo.com, …) dan itu bukan acuan yang benar untuk domain sendiri
        $public = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'qq.com',
            'yandex.ru', 'mail.ru', 'icloud.com', ];

        foreach ($domainList as $name => $type) {
            if ($type == 'ini' && !in_array($name, $public)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Menyambungkan penyaring spam yang sudah terpasang ke MTA.
     *
     * Hanya menyambungkan; tidak memasang apa pun. Yang ditambahkan satu entri
     * milter ke `smtpd_milters`, **di samping** entri yang sudah ada — opendkim
     * lazim sudah duduk di sana, dan menimpanya berarti seluruh surat keluar
     * berhenti ditandatangani.
     *
     * `milter_default_action` ikut dipastikan `accept`, sehingga bila penyaring
     * mati suatu saat, surat tetap lewat alih-alih menumpuk di antrean.
     * Bawaan Postfix untuk nilai itu adalah `tempfail`.
     *
     * @param null|string $port porta milter penyaringnya; bawaannya rspamd
     *
     * @return array errCode, errMessage, before, after, output
     */
    public function attachSpamFilter($port = '11332') {
        $port = trim((string) $port);
        if (preg_match('/^\d{2,5}$/', $port) !== 1) {
            return ['errCode' => 1, 'errMessage' => 'Porta milter tidak sah', 'before' => '',
                'after' => '', 'output' => '', ];
        }

        $entry = 'inet:127.0.0.1:' . $port;
        $before = trim($this->run($this->sudo() . 'postconf -h smtpd_milters 2>/dev/null'));

        if (strpos($before, ':' . $port) !== false) {
            return ['errCode' => 0, 'errMessage' => '', 'before' => $before,
                'after' => $before, 'output' => 'sudah tersambung, tidak ada yang diubah', ];
        }

        $value = strlen($before) > 0 ? $before . ', ' . $entry : $entry;
        $stamp = trim($this->run('date +%Y%m%d%H%M%S'));

        $command = $this->sudo() . 'bash -c ' . escapeshellarg(
            'set -e; '
            . 'cp -a /etc/postfix/main.cf /etc/postfix/main.cf.bak-' . $stamp . '; '
            . 'postconf -e ' . escapeshellarg('smtpd_milters = ' . $value) . '; '
            . 'postconf -e ' . escapeshellarg('non_smtpd_milters = $smtpd_milters') . '; '
            . 'postconf -e ' . escapeshellarg('milter_default_action = accept') . '; '
            . 'postfix reload'
        ) . ' 2>&1; echo "exit status $?"';

        $output = (string) $this->run($command);

        if (strpos($output, 'exit status 0') === false) {
            return ['errCode' => 1, 'before' => $before, 'after' => $before, 'output' => $output,
                'errMessage' => 'Gagal menerapkan. main.cf lama dicadangkan sebagai'
                    . ' /etc/postfix/main.cf.bak-' . $stamp, ];
        }

        //dipastikan dari sisi Postfix sendiri, bukan dari kode keluar
        $after = trim($this->run($this->sudo() . 'postconf -h smtpd_milters 2>/dev/null'));
        if (strpos($after, ':' . $port) === false) {
            return ['errCode' => 1, 'before' => $before, 'after' => $after, 'output' => $output,
                'errMessage' => 'Perintah berhasil tetapi smtpd_milters belum memuat porta ' . $port, ];
        }

        return ['errCode' => 0, 'errMessage' => '', 'before' => $before, 'after' => $after,
            'output' => $output, ];
    }

    /**
     * Domain yang benar-benar dipakai server ini sebagai pengirim.
     *
     * Diambil dari log, bukan dari konfigurasi. `virtual_mailbox_domains` hanya
     * menyebut domain yang kotak suratnya dilayani di sini, sedangkan surat
     * keluar kerap dikirim atas nama domain lain yang hanya direlai — dan
     * justru domain itulah yang ditolak penyedia bila belum divalidasi. Log
     * menjawab pertanyaan yang sebenarnya: atas nama siapa mesin ini mengirim.
     *
     * Penyaringnya bekerja pada **waktu ubah berkas log**, bukan pada tanggal
     * tiap barisnya: berkas yang sudah diputar tidak lagi berubah, sehingga
     * `mail.log.1` yang berhenti ditulis seminggu lalu ikut tersaring keluar
     * walau isinya masih dalam rentang. Karena itu bawaannya lebar — dengan
     * tujuh hari, pensiunq.com yang mengirim 12 surat sempat tidak terlihat
     * sama sekali.
     *
     * @param int $day berapa hari ke belakang berkas lognya diambil
     *
     * @return array domain => jumlah surat, terurut dari yang terbanyak
     */
    public function getSenderDomainList($day = 30) {
        $day = max(1, (int) $day);

        //hanya surat yang benar-benar dikirim keluar yang dihitung. `from=<>`
        //muncul pada semua surat, termasuk yang masuk — mesin ini menerima
        //surat juga — sehingga menghitungnya mentah-mentah membuat domain
        //pengirim spam ikut terdaftar seolah kita yang mengirim atas namanya.
        //Pembedanya: pengiriman keluar ditangani `postfix/smtp`, sedangkan
        //surat masuk diserahkan ke kotak lewat lmtp atau local. Jadi alamat
        //pengirim dikumpulkan per antrean dari baris qmgr, lalu hanya antrean
        //yang punya baris smtp yang ikut dihitung.
        $awk = <<<'AWK'
/postfix\/qmgr\[/ && /from=</ {
    if (match($0, /[A-F0-9]{8,}:/)) { qid = substr($0, RSTART, RLENGTH - 1) }
    if (match($0, /from=<[^>]*>/)) { from[qid] = substr($0, RSTART + 6, RLENGTH - 7) }
    next
}
/postfix\/smtp\[/ {
    if (match($0, /[A-F0-9]{8,}:/)) { out[substr($0, RSTART, RLENGTH - 1)] = 1 }
}
END {
    for (q in out) {
        if (q in from) {
            f = from[q]
            p = index(f, "@")
            if (p > 0) { c[tolower(substr(f, p + 1))]++ }
        }
    }
    for (d in c) { print c[d], d }
}
AWK;

        $script = 'find /var/log -maxdepth 1 \( -name "mail.log*" -o -name "maillog*" \)'
            . ' -mtime -' . $day . ' 2>/dev/null | head -20'
            . ' | xargs -r zgrep -h -E "postfix/(qmgr|smtp)\[" 2>/dev/null'
            . ' | awk ' . escapeshellarg($awk)
            . ' | sort -rn | head -50';

        $output = (string) $this->run($this->sudo() . 'bash -c ' . escapeshellarg($script));

        $list = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (strlen($line) == 0) {
                continue;
            }
            $section = preg_split('/\s+/', $line, 2);
            $count = (int) carr::get($section, 0);
            $domain = trim((string) carr::get($section, 1));
            //baris yang bukan hitungan domain diabaikan diam-diam: isi log
            //berbeda antar distribusi dan tidak layak menggagalkan seluruhnya
            if ($count > 0 && strlen($domain) > 0 && strpos($domain, ' ') === false) {
                $list[$domain] = $count;
            }
        }

        return $list;
    }

    /**
     * Domain yang kotak suratnya dilayani server ini.
     *
     * Berbeda dari getSenderDomainList(): yang ini menyebut domain yang
     * *di-hosting*, sedangkan yang itu menyebut domain yang benar-benar pernah
     * mengirim keluar. Keduanya kerap tidak sama — sebuah domain dapat menerima
     * surat bertahun-tahun tanpa sekali pun mengirim, dan sebaliknya surat
     * keluar dapat dikirim atas nama domain yang tidak di-hosting di sini.
     *
     * Petanya lazim berupa `mysql:` sehingga daftarnya harus ditanyakan ke
     * basis data yang ditunjuk berkas konfigurasinya; bentuk `hash:` dan daftar
     * harfiah dibaca langsung.
     *
     * @return array
     */
    public function getHostedDomainList() {
        $script = 'v=$(postconf -h virtual_mailbox_domains 2>/dev/null);'
            . ' case "$v" in'
            . ' mysql:*) f=${v#mysql:};'
            . ' u=$(grep -m1 -E "^user" "$f" | cut -d= -f2 | xargs);'
            . ' p=$(grep -m1 -E "^password" "$f" | cut -d= -f2 | xargs);'
            . ' d=$(grep -m1 -E "^dbname" "$f" | cut -d= -f2 | xargs);'
            . ' q=$(grep -m1 -E "^query" "$f" | cut -d= -f2-);'
            . ' t=$(echo "$q" | grep -oiE "from[[:space:]]+[a-z_]+" | awk "{print \$2}");'
            . ' [ -n "$t" ] && mysql -u"$u" -p"$p" "$d" -N -e "SELECT name FROM $t" 2>/dev/null;;'
            . ' hash:*|texthash:*|lmdb:*) f=${v#*:}; [ -f "$f" ] && awk "{print \$1}" "$f";;'
            . ' *) echo "$v" | tr ", " "\\n\\n";;'
            . ' esac';

        $output = (string) $this->run($this->sudo() . 'bash -c ' . escapeshellarg($script));

        $list = [];
        foreach (explode("\n", $output) as $line) {
            $line = strtolower(trim($line));
            //baris kosong, komentar, dan pesan galat mysql diabaikan diam-diam
            if (strlen($line) == 0 || strpos($line, ' ') !== false || strpos($line, '.') === false) {
                continue;
            }
            $list[] = $line;
        }

        return array_values(array_unique($list));
    }

    /**
     * Ringkas: apakah server ini melayani surel ke luar.
     *
     * @return bool
     */
    public function isMailServer() {
        $info = $this->inspect();

        return carr::get($info, 'isMailServer') === true;
    }

    /**
     * Mengurai nilai relayhost Postfix.
     *
     * Bentuknya `[host]:port`, `host:port`, atau `host` saja; kurung siku
     * berarti jangan cari MX, dan itu justru yang lazim untuk relai.
     *
     * @param string $value
     * @param bool   $auth
     *
     * @return null|array host, port, provider, auth, raw
     */
    protected static function parseRelay($value, $auth) {
        $value = trim((string) $value);
        if (strlen($value) == 0) {
            return null;
        }

        $host = $value;
        $port = null;
        if (preg_match('/^\[?([^\]:]+)\]?(?::(\d+))?$/', $value, $m)) {
            $host = $m[1];
            $port = isset($m[2]) ? (int) $m[2] : null;
        }

        $provider = null;
        foreach (self::$relayProvider as $hint => $providerName) {
            if (stripos($host, $hint) !== false) {
                $provider = $providerName;

                break;
            }
        }

        return [
            'host' => $host,
            'port' => $port,
            'provider' => $provider,
            'auth' => (bool) $auth,
            'raw' => $value,
        ];
    }

    /**
     * @param string $value
     *
     * @return array
     */
    protected static function splitList($value) {
        $result = [];
        foreach (explode(',', (string) $value) as $section) {
            $section = trim($section);
            if (strlen($section) > 0) {
                $result[] = $section;
            }
        }

        return $result;
    }

    /**
     * @param string $output
     *
     * @return array porta => daftar alamat
     */
    protected function parseListening($output) {
        $start = strpos($output, 'LISTEN_START');
        $end = strpos($output, 'LISTEN_END');
        if ($start === false || $end === false) {
            return [];
        }
        $block = substr($output, $start + strlen('LISTEN_START'), $end - $start - strlen('LISTEN_START'));

        $result = [];
        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if (strlen($line) == 0) {
                continue;
            }
            $address = trim((string) carr::get(explode(' ', $line), 0));
            //bentuknya 0.0.0.0:25, [::]:25, atau *:25
            $pos = strrpos($address, ':');
            if ($pos === false) {
                continue;
            }
            $port = (int) substr($address, $pos + 1);
            if (!isset(self::$portRole[$port])) {
                continue;
            }
            $host = substr($address, 0, $pos);
            if (!isset($result[$port])) {
                $result[$port] = [];
            }
            if (!in_array($host, $result[$port])) {
                $result[$port][] = $host;
            }
        }

        return $result;
    }

    /**
     * @param string $address
     *
     * @return bool
     */
    protected function isPublicAddress($address) {
        $address = trim($address, '[]');

        return !in_array($address, ['127.0.0.1', '::1', 'localhost'], true);
    }
}
