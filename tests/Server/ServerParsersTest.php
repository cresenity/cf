<?php
use PHPUnit\Framework\TestCase;

/**
 * Fail2ban dengan keluaran perintah yang direkam — tidak ada proses yang dijalankan.
 */
class UjiServer_Fail2ban extends CServer_Fail2ban {
    /** @var string */
    public $output = '';

    /** @var string[] */
    public $commands = [];

    protected function run($command) {
        $this->commands[] = $command;

        return $this->output;
    }

    protected function sudoPrefix() {
        return '';
    }

    public function parseJailStatusPublic($jail, $output) {
        return $this->parseJailStatus($jail, $output);
    }
}

/**
 * CServer bagian murni: parser fail2ban (status jail, riwayat log/jurnal), daftar provider SMTP relay,
 * parser WHOIS, dan pemecah argumen perintah SMTP.
 */
class ServerParsersTest extends TestCase {
    /**
     * @return UjiServer_Fail2ban
     */
    protected function fail2ban() {
        return new UjiServer_Fail2ban(new CServer_Server());
    }

    public function testFail2banValidators() {
        $this->assertTrue(CServer_Fail2ban::isValidIp('203.0.113.9'));
        $this->assertTrue(CServer_Fail2ban::isValidIp('2001:db8::1'));
        $this->assertFalse(CServer_Fail2ban::isValidIp('999.1.1.1'));
        $this->assertFalse(CServer_Fail2ban::isValidIp('sshd'));
        $this->assertTrue(CServer_Fail2ban::isValidJail('sshd'));
        $this->assertTrue(CServer_Fail2ban::isValidJail('litespeed-auth_v2.1'));
        $this->assertFalse(CServer_Fail2ban::isValidJail('sshd; rm -rf /'));
        $this->assertFalse(CServer_Fail2ban::isValidJail(''));
        $this->assertFalse(CServer_Fail2ban::isValidJail(str_repeat('a', 65)));
    }

    public function testParseJailStatusReadsCountersAndBannedList() {
        $output = "Status for the jail: sshd\n"
            . "|- Filter\n"
            . "|  |- Currently failed: 3\n"
            . "|  |- Total failed:     1520\n"
            . "|  `- File list:        /var/log/auth.log\n"
            . "`- Actions\n"
            . "   |- Currently banned: 2\n"
            . "   |- Total banned:     87\n"
            . "   `- Banned IP list:   203.0.113.9 198.51.100.7 bukan-ip\n";
        $data = $this->fail2ban()->parseJailStatusPublic('sshd', $output);
        $this->assertSame([
            'jail' => 'sshd',
            'currentlyFailed' => 3,
            'totalFailed' => 1520,
            'currentlyBanned' => 2,
            'totalBanned' => 87,
            'bannedIp' => ['203.0.113.9', '198.51.100.7'],
        ], $data);
        $this->assertSame(0, $this->fail2ban()->parseJailStatusPublic('x', '')['totalBanned'], 'keluaran kosong → nol semua');
    }

    public function testBanHistoryAggregatesLogAndJournalLines() {
        $fail2ban = $this->fail2ban();
        $fail2ban->output = implode("\n", [
            '2026-09-18 10:00:01,123 fail2ban.actions        [1234]: NOTICE  [sshd] Ban 203.0.113.9',
            '2026-09-18 10:10:01,123 fail2ban.actions        [1234]: NOTICE  [sshd] Unban 203.0.113.9',
            '2026-09-18 11:00:00,000 fail2ban.actions        [1234]: NOTICE  [sshd] Ban 203.0.113.9',
            '2026-09-18T12:30:00+0700 host fail2ban.actions[1234]: NOTICE  [litespeed] Ban 198.51.100.7',
            '2026-09-18 12:31:00,000 fail2ban.actions        [1234]: NOTICE  [sshd] Ban bukan-ip',
            'baris sampah tanpa pola',
        ]);
        $history = $fail2ban->getBanHistory(0);
        $this->assertStringContainsString('tail -n 1', $fail2ban->commands[0], 'limit < 1 dinaikkan ke 1');
        $this->assertStringContainsString('zcat -f', $fail2ban->commands[0]);
        $this->assertStringContainsString('journalctl -u', $fail2ban->commands[0]);
        $this->assertCount(2, $history, 'IP tidak valid dilewati; satu entri per jail|ip');
        $this->assertSame('litespeed', $history[0]['jail'], 'terbaru di atas (12:30 > 11:00)');
        $this->assertSame('198.51.100.7', $history[0]['ip']);
        $this->assertSame('2026-09-18 12:30:00', $history[0]['lastBan'], 'format jurnal systemd dinormalkan');
        $this->assertTrue($history[0]['banned']);
        $this->assertSame(2, $history[1]['banCount']);
        $this->assertSame('2026-09-18 11:00:00', $history[1]['lastBan']);
        $this->assertSame('2026-09-18 10:10:01', $history[1]['lastUnban']);
        $this->assertTrue($history[1]['banned'], 'Ban terakhir setelah Unban → masih diblokir');
    }

    public function testMailRelayProviderList() {
        $providers = CServer_Mail::providerList();
        $this->assertArrayHasKey('mailjet', $providers);
        foreach ($providers as $key => $provider) {
            $this->assertArrayHasKey('label', $provider, $key);
            $this->assertArrayHasKey('host', $provider, $key);
            $this->assertContains($provider['port'], $provider['portList'], $key . ': port default harus ada di daftar port');
            $this->assertArrayHasKey('usernameLabel', $provider, $key);
        }
        $this->assertSame('in-v3.mailjet.com', CServer_Mail::provider('mailjet')['host']);
        $this->assertNull(CServer_Mail::provider('tidak-ada'));
    }

    public function testWhoisParserExtractsDomainFieldsAndContacts() {
        $lines = [
            'Domain Name: CRESENITY.COM',
            'Registry Domain ID: 123456_DOMAIN_COM-VRSN',
            'Registrar WHOIS Server: whois.registrar.test',
            'Registrar URL: http://www.registrar.test',
            'Updated Date: 2026-01-05T10:20:30Z',
            'Creation Date: 2015-03-01T00:00:00Z',
            'Registry Expiry Date: 2027-03-01T00:00:00Z',
            'Registrar: Registrar Uji, Inc.',
            'Domain Status: clientTransferProhibited https://icann.org/epp#clientTransferProhibited',
            'Domain Status: clientUpdateProhibited https://icann.org/epp#clientUpdateProhibited',
            'Registrant Organization: Cresenity',
            'Registrant Country: ID',
            'Admin Email: Admin@Cresenity.TEST',
            'Name Server: NS1.EXAMPLE.NET',
            'Name Server: NS2.EXAMPLE.NET',
        ];
        $result = (new CServer_Domain_WhoIs_Parser())->parseDomain($lines, 'cresenity.com', 'com');
        $this->assertSame('cresenity.com', $result['domain']);
        $this->assertSame('123456_DOMAIN_COM-VRSN', $result['id']);
        $this->assertSame(['Registrar Uji, Inc.'], $result['registrar']);
        $this->assertSame(['NS1.EXAMPLE.NET', 'NS2.EXAMPLE.NET'], $result['dns']);
        $this->assertCount(2, $result['status']);
        $this->assertSame('Cresenity', $result['registrant']['organization'], 'kontak dikelompokkan per peran');
        $this->assertSame('ID', $result['registrant']['country']);
        $this->assertSame('admin@cresenity.test', $result['admin']['email']);
        $this->assertArrayNotHasKey('registrant_organization', $result);
        $this->assertSame('2015-03-01 00:00:00', $result['created_parsed_string']);
        $this->assertSame('2027-03-01 00:00:00', $result['expires_parsed_string']);
        $this->assertSame(2026, $result['updated_parsed']['year']);
    }

    public function testWhoisParserUsesTldSpecificDomainWord() {
        $lines = ['Domain: beispiel.de', 'Status: connect', 'Nserver: ns1.beispiel.de'];
        $result = (new CServer_Domain_WhoIs_Parser())->parseDomain($lines, 'beispiel.de', 'de');
        $this->assertSame('beispiel.de', $result['domain']);
        $this->assertSame(['connect'], $result['status']);
        $this->assertSame([], (new CServer_Domain_WhoIs_Parser())->parseDomain($lines, 'lain.de', 'de'), 'domain yang tidak cocok → kosong');
    }

    public function testSmtpStringParserSplitsArgumentsAndQuotes() {
        $this->assertSame(['MAIL', 'FROM:<a@b.c>'], (new CServer_SMTP_StringParser('MAIL FROM:<a@b.c>'))->parse());
        $this->assertSame(['AUTH', 'LOGIN', 'dXNlcg=='], (new CServer_SMTP_StringParser('  AUTH   LOGIN dXNlcg==  '))->parse(), 'spasi ganda dan tepi dibuang');
        $this->assertSame(['SAY', 'halo dunia', 'x'], (new CServer_SMTP_StringParser('SAY "halo dunia" x'))->parse(), 'teks berkutip jadi satu argumen tanpa tanda kutip');
        $this->assertSame(['RCPT', 'TO:<x@y.z> NOTIFY=SUCCESS'], (new CServer_SMTP_StringParser('RCPT TO:<x@y.z> NOTIFY=SUCCESS', 2))->parse(), 'argsMax menggabungkan sisa ke argumen terakhir');
        $this->assertSame([], (new CServer_SMTP_StringParser(''))->parse());
    }
}
