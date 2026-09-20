<?php
use PHPUnit\Framework\TestCase;

/**
 * SmtpDriver dengan koneksi palsu: perintah SMTP dicatat, data ditulis ke php://memory.
 */
class UjiEmail_RecordingSmtpDriver extends CEmail_Driver_SmtpDriver {
    /** @var string[] */
    public $commands = [];

    /** @var array */
    public $responses = [];

    /** @var bool */
    public $rejectLogin = false;

    protected function smtpConnect($smtpHost, $smtpPort, $smtpOptions = []) {
        $this->commands[] = 'CONNECT ' . $smtpHost . ':' . $smtpPort . ' ' . $this->config->getProtocol() . '/' . ($this->config->getEncryption() ?: 'none');
        $this->smtpConnection = fopen('php://memory', 'w+');

        return $this->smtpConnection;
    }

    protected function smtpSend($data, $expecting, $returnNumber = false) {
        $this->commands[] = $data;
        if ($this->rejectLogin && $data === 'AUTH LOGIN') {
            throw new CEmail_Exception_SmtpCommandFailureException('504 unrecognized');
        }

        return '250 OK';
    }

    /** @var string */
    public $transmitted = '';

    protected function smtpDisconnect() {
        $this->commands[] = 'QUIT';
        rewind($this->smtpConnection);
        $this->transmitted = stream_get_contents($this->smtpConnection);
        fclose($this->smtpConnection);
        $this->smtpConnection = null;
    }

    /**
     * @return string
     */
    public function transmitted() {
        return $this->transmitted;
    }
}

/**
 * Jalur pengirim lama: CEmail_Config (pemetaan smtp_* → driver), CEmail_Factory, CEmail_Sender,
 * format alamat, dan pesan/perintah yang dibangun CEmail_Driver_SmtpDriver — tanpa jaringan.
 */
class LegacySenderTest extends TestCase {
    /** @var array */
    protected $originalApp = [];

    protected function setUp(): void {
        foreach (['app.smtp_from', 'app.smtp_from_name', 'app.smtp_host', 'app.email', 'app.smtp_secure', 'vendor.ses'] as $key) {
            $this->originalApp[$key] = CF::config($key);
        }
        CConfig::repository()->set('app.email', null);
        CConfig::repository()->set('app.smtp_host', '');
        CConfig::repository()->set('app.smtp_from', 'noreply@app.test');
        CConfig::repository()->set('app.smtp_from_name', 'Aplikasi');
    }

    protected function tearDown(): void {
        foreach ($this->originalApp as $key => $value) {
            CConfig::repository()->set($key, $value);
        }
    }

    public function testLegacySendgridHostBecomesTheSendgridDriverWithoutSmtpKeys() {
        $config = new CEmail_Config(['smtp_host' => 'smtp.sendgrid.net', 'smtp_username' => 'apikey', 'smtp_password' => 'SG.rahasia', 'smtp_from' => 'a@b.test', 'smtp_from_name' => 'AB']);

        $this->assertSame('sendgrid', $config->getDriver());
        $this->assertSame('SG.rahasia', $config->getPassword());
        $this->assertSame('apikey', $config->getUsername());
        $this->assertSame('a@b.test', $config->getFrom());
        $this->assertSame('AB', $config->getFromName());
        $this->assertNull($config->getHost(), 'host hanya untuk driver smtp');
        $this->assertInstanceOf(CEmail_Driver_SendGridDriver::class, CEmail_Factory::createDriver($config));
    }

    public function testLegacyCustomHostBecomesSmtpWithPortAndSecure() {
        $config = new CEmail_Config(['smtp_host' => 'mail.kantor.test', 'smtp_port' => '587', 'smtp_secure' => 'tls', 'smtp_username' => 'u', 'smtp_password' => 'p']);

        $this->assertSame('smtp', $config->getDriver());
        $this->assertSame('mail.kantor.test', $config->getHost());
        $this->assertSame('587', $config->getPort());
        $this->assertSame('tls', $config->getSecure());
        $this->assertSame('tls', $config->getEncryption());
        $this->assertSame('noreply@app.test', $config->getFrom(), 'from jatuh ke app.smtp_from');
        $this->assertSame('Aplikasi', $config->getFromName());
    }

    public function testExplicitDriverKeepsEveryKey() {
        $config = new CEmail_Config(['driver' => 'brevo', 'password' => 'k', 'from' => 'x@y.test', 'endpoint' => 'eu']);

        $this->assertSame('brevo', $config->getDriver());
        $this->assertSame('eu', $config->get('endpoint'), 'kunci tambahan tersedia lewat get()');
        $this->assertSame('x@y.test', $config->getFrom());
    }

    public function testHostIsKeptWhenFromIsBlank() {
        $config = new CEmail_Config(['driver' => 'smtp', 'host' => 'mail.kantor.test', 'from' => '']);

        $this->assertSame('mail.kantor.test', $config->getHost(), 'host tidak lagi tertimpa saat from kosong');
        $this->assertSame('noreply@app.test', $config->getFrom());
    }

    public function testEncryptionIsNormalizedFromWhatAppsActuallyWrite() {
        $cases = [['tls', 'tls'], ['TLS', 'tls'], ['starttls', 'tls'], [true, 'tls'], ['ssl', 'ssl'], ['SSL', 'ssl'], ['false', null], [false, null], ['', null], ['none', null], [null, null]];
        foreach ($cases as list($input, $expected)) {
            $config = new CEmail_Config(['driver' => 'smtp', 'host' => 'h', 'secure' => $input]);
            $this->assertSame($expected, $config->getEncryption(), 'secure=' . var_export($input, true));
        }
    }

    public function testMissingDriverAndHostThrowsAnInformativeException() {
        $this->expectException(CEmail_Exception_InvalidConfigException::class);
        $this->expectExceptionMessage('smtp_host');
        new CEmail_Config(['smtp_username' => 'u']);
    }

    public function testFromResolutionOrderIsSharedBySenderAndConfig() {
        CConfig::repository()->set('app.email', ['from' => 'email@app.test', 'from_name' => 'Email Cfg']);
        $this->assertSame('a@x.test', CEmail_Config::resolveFrom(['from' => 'a@x.test', 'smtp_from' => 'b@x.test']));
        $this->assertSame('b@x.test', CEmail_Config::resolveFrom(['smtp_from' => 'b@x.test']));
        $this->assertSame('email@app.test', CEmail_Config::resolveFrom(['from' => '']), 'app.email.from sebelum app.smtp_from');
        $this->assertSame('Email Cfg', CEmail_Config::resolveFromName([]));
        CConfig::repository()->set('app.email', null);
        $this->assertSame('noreply@app.test', CEmail_Config::resolveFrom([]));

        $sender = CEmail::sender(['driver' => 'null']);
        $method = new ReflectionMethod($sender, 'rebuildOptions');
        $method->setAccessible(true);
        $options = $method->invoke($sender, ['smtp_from_name' => 'Dari Record', 'cc' => 'cc@x.test', 'attachments' => '/tmp/a.pdf']);
        $this->assertSame('noreply@app.test', $options['from']);
        $this->assertSame('Dari Record', $options['from_name']);

        $this->assertSame(['cc@x.test'], $options['cc'], 'cc string dibungkus array');
        $this->assertSame([], $options['bcc']);
        $this->assertSame(['/tmp/a.pdf'], $options['attachments']);

        $configured = CEmail::sender(['driver' => 'null', 'from' => 'kirim@x.test', 'from_name' => 'Config']);
        $options = $method->invoke($configured, []);
        $this->assertSame('kirim@x.test', $options['from'], 'from di config pengirim menang atas default app');
        $this->assertSame('Config', $options['from_name']);
        $options = $method->invoke($configured, ['from' => 'opsi@x.test']);
        $this->assertSame('opsi@x.test', $options['from'], 'opsi per-kirim menang atas config');
    }

    public function testFactoryResolvesDriverNamesAndRejectsUnknownOnes() {
        $this->assertInstanceOf(CEmail_Driver_NullDriver::class, CEmail_Factory::createDriver(new CEmail_Config(['driver' => 'null'])));
        $this->assertInstanceOf(CEmail_Driver_MailDriver::class, CEmail_Factory::createDriver(new CEmail_Config(['driver' => 'mail'])));
        $this->assertInstanceOf(CEmail_Driver_SmtpDriver::class, CEmail_Factory::createDriver(new CEmail_Config(['driver' => 'smtp', 'host' => 'h'])));
        $this->assertInstanceOf(CEmail_DriverInterface::class, CEmail::sender(['driver' => 'null'])->getDriver());
        $this->assertNull(CEmail::sender(['driver' => 'null'])->send('a@x.test', 'Uji', '<p>Halo</p>'));

        try {
            CEmail_Factory::createDriver(new CEmail_Config(['driver' => 'tidak-ada']));
            $this->fail('driver tak dikenal harus ditolak');
        } catch (CEmail_Exception_DriverNotFoundException $e) {
            $this->assertInstanceOf(Exception::class, $e, 'catch (Exception) lama tetap menangkap');
            $this->assertStringContainsString('sendgrid', $e->getMessage(), 'pesan menyebut driver yang tersedia');
        }
    }

    public function testAddressFormatting() {
        $items = [['email' => 'a@x.test', 'name' => 'Budi'], 'c@x.test', ['toEmail' => 'd@x.test', 'toName' => 'Dedi "Si" Ok'], ['email' => 'e@x.test', 'name' => 'Ãgnès'], ['email' => 'f@x.test']];

        $this->assertSame('"Budi" <a@x.test>, c@x.test, "Dedi \"Si\" Ok" <d@x.test>, ' . mb_encode_mimeheader('Ãgnès', 'utf-8', 'B', "\r\n") . ' <e@x.test>, f@x.test', CEmail_DriverAbstract::formatAddresses($items));
        $this->assertSame(['a@x.test', 'c@x.test', 'd@x.test', 'e@x.test', 'f@x.test'], CEmail_DriverAbstract::emailAddresses($items));
        $this->assertSame('', CEmail_DriverAbstract::formatAddresses([]));
    }

    public function testSmtpMessageCarriesTheMandatoryHeaders() {
        $config = new CEmail_Config(['driver' => 'smtp', 'host' => 'mail.x.test', 'from' => 'kirim@x.test', 'from_name' => 'Pengirim', 'domain' => 'x.test']);
        $message = new CEmail_Driver_Smtp_Message(['a@x.test', ['email' => 'b@x.test', 'name' => 'Budi']], '<p>Halo</p>', 'Verifikasi — Résumé', $config, ['cc' => ['cc@x.test'], 'bcc' => 'bcc@x.test', 'reply_to' => 'balas@x.test']);
        $built = $message->buildMessage();
        $headers = $built['header'];

        $this->assertMatchesRegularExpression('/^Date: [A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} /m', $headers);
        $this->assertStringContainsString("Return-Path: <kirim@x.test>\r\n", $headers);
        $this->assertStringContainsString("From: \"Pengirim\" <kirim@x.test>\r\n", $headers);
        $this->assertStringContainsString("To: a@x.test, \"Budi\" <b@x.test>\r\n", $headers);
        $this->assertStringContainsString("Cc: cc@x.test\r\n", $headers);
        $this->assertStringContainsString("Bcc: bcc@x.test\r\n", $headers);
        $this->assertStringContainsString("Reply-To: balas@x.test\r\n", $headers);
        $this->assertMatchesRegularExpression('/^Subject: .*=\?utf-8\?B\?[A-Za-z0-9+\/=]+\?=/mi', $headers, 'subjek non-ASCII di-encode');
        $this->assertSame('Verifikasi — Résumé', iconv_mime_decode(trim(substr($headers, strpos($headers, 'Subject: ') + 9, strpos($headers, "\r\n", strpos($headers, 'Subject: ')) - strpos($headers, 'Subject: ') - 9)), 0, 'UTF-8'));
        $this->assertMatchesRegularExpression('/^Message-ID: <[A-Za-z0-9]+@x\.test>\r\n/m', $headers);
        $this->assertStringContainsString("MIME-Version: 1.0\r\n", $headers);
        $this->assertStringContainsString("Content-Type: text/html; charset=\"utf-8\"\r\n", $headers);
        $this->assertStringContainsString("Content-Transfer-Encoding: 8bit\r\n", $headers);
        $this->assertStringNotContainsString('MINE', $headers);
        $this->assertSame('<p>Halo</p>', $built['body']);

        $this->assertStringNotContainsString('Bcc:', $message->buildMessage(true)['header'], 'noBcc menyembunyikan Bcc');
        $plain = new CEmail_Driver_Smtp_Message(['a@x.test'], 'Halo', 'Uji ASCII', $config, ['type' => 'plain']);
        $this->assertStringContainsString("Subject: Uji ASCII\r\n", $plain->buildMessage()['header'], 'subjek ASCII apa adanya');
        $this->assertStringContainsString('Content-Type: text/plain;', $plain->buildMessage()['header']);
    }

    public function testSmtpMessageBuildsMultipartWithEveryAttachmentShape() {
        $tmp = tempnam(sys_get_temp_dir(), 'uji') . '.txt';
        file_put_contents($tmp, 'isi berkas');
        $config = new CEmail_Config(['driver' => 'smtp', 'host' => 'h', 'from' => 'kirim@x.test']);
        $message = new CEmail_Driver_Smtp_Message(['a@x.test'], '<b>Html</b>', 'Lampiran', $config, ['attachments' => [
            $tmp,
            ['path' => $tmp, 'filename' => 'laporan.pdf', 'type' => 'application/pdf'],
            ['data' => 'data mentah', 'name' => 'catatan.txt', 'mime' => 'text/plain'],
            CEmail_Attachment::fromData(function () {
                return 'dari closure';
            }, 'closure.csv')->withMime('text/csv'),
            CEmail_Attachment::fromPath($tmp)->as('alias.txt'),
        ]]);
        $built = $message->buildMessage();
        unlink($tmp);

        $this->assertCount(5, $message->getAttachments());
        $this->assertMatchesRegularExpression('/^Content-Type: multipart\/mixed; boundary="(b1=_[A-Za-z0-9]+)"\r\n/m', $built['header'], $built['header']);
        preg_match('/boundary="([^"]+)"/', $built['header'], $m);
        $boundary = $m[1];
        $this->assertSame(6, substr_count($built['body'], '--' . $boundary . "\r\n"), '1 pembuka html + 5 lampiran');
        $this->assertStringContainsString('--' . $boundary . '--', $built['body'], 'penutup multipart');
        $this->assertStringContainsString("Content-Type: text/html; charset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\n\r\n<b>Html</b>", $built['body']);
        $this->assertStringContainsString('Content-Type: text/plain; name="' . basename($tmp) . '"', $built['body'], 'path string: nama dari basename, mime dideteksi');
        $this->assertStringContainsString('Content-Disposition: attachment; filename="laporan.pdf"', $built['body']);
        $this->assertStringContainsString('Content-Type: application/pdf; name="laporan.pdf"', $built['body']);
        $this->assertStringContainsString('Content-Disposition: attachment; filename="catatan.txt"', $built['body']);
        $this->assertStringContainsString(base64_encode('data mentah'), $built['body']);
        $this->assertStringContainsString('Content-Type: text/csv; name="closure.csv"', $built['body']);
        $this->assertStringContainsString(base64_encode('dari closure'), $built['body']);
        $this->assertStringContainsString('Content-Disposition: attachment; filename="alias.txt"', $built['body']);
        $this->assertSame(3, substr_count($built['body'], base64_encode('isi berkas')), 'isi file dilampirkan tiga kali (path, array, alias)');

        $none = new CEmail_Driver_Smtp_Message(['a@x.test'], 'x', 's', $config, ['attachments' => []]);
        $this->assertSame([], $none->getAttachments());
        $this->assertStringContainsString('Content-Type: text/html;', $none->buildMessage()['header'], 'tanpa lampiran tetap single part');
    }

    public function testMissingAttachmentFileThrows() {
        $config = new CEmail_Config(['driver' => 'smtp', 'host' => 'h', 'from' => 'kirim@x.test']);
        $this->expectException(CEmail_Exception_EmailSendingFailedException::class);
        new CEmail_Driver_Smtp_Message(['a@x.test'], 'x', 's', $config, ['attachments' => ['/tidak/ada.pdf']]);
    }

    public function testSmtpDriverSendsOneRcptPerRecipientAndTheWholeMessage() {
        $config = new CEmail_Config(['driver' => 'smtp', 'host' => 'mail.x.test', 'port' => 587, 'secure' => 'tls', 'username' => 'u', 'password' => 'p', 'from' => 'kirim@x.test']);
        $driver = new UjiEmail_RecordingSmtpDriver($config);
        $result = $driver->send(['a@x.test', ['email' => 'b@x.test', 'name' => 'Budi']], 'Uji', '<p>Isi</p>', ['cc' => 'cc@x.test', 'bcc' => ['bcc@x.test'], 'from' => 'kirim@x.test']);

        $this->assertTrue($result);
        $this->assertSame('CONNECT mail.x.test:587 tcp/tls', $driver->commands[0]);
        $this->assertContains('AUTH LOGIN', $driver->commands);
        $this->assertContains('MAIL FROM:<kirim@x.test>', $driver->commands);
        $rcpt = array_values(array_filter($driver->commands, function ($c) {
            return strpos($c, 'RCPT TO:') === 0;
        }));
        $this->assertSame(['RCPT TO:<a@x.test>', 'RCPT TO:<b@x.test>', 'RCPT TO:<cc@x.test>', 'RCPT TO:<bcc@x.test>'], $rcpt, 'satu RCPT per alamat, tanpa nama');
        $this->assertContains('DATA', $driver->commands);
        $this->assertSame('.', $driver->commands[count($driver->commands) - 2]);
        $this->assertSame('QUIT', end($driver->commands));
        $transmitted = $driver->transmitted();
        $this->assertStringContainsString("To: a@x.test, \"Budi\" <b@x.test>\r\n", $transmitted);
        $this->assertStringContainsString("MIME-Version: 1.0\r\n", $transmitted);
        $this->assertStringContainsString("<p>Isi</p>\r\n", $transmitted);
    }

    public function testSmtpDriverFallsBackToAuthPlainAndUsesImplicitTlsForSsl() {
        $config = new CEmail_Config(['driver' => 'smtp', 'host' => 'mail.x.test', 'port' => 465, 'secure' => 'ssl', 'username' => 'u', 'password' => 'p', 'from' => 'kirim@x.test']);
        $driver = new UjiEmail_RecordingSmtpDriver($config);
        $driver->rejectLogin = true;
        $driver->send(['a@x.test'], 'Uji', 'Isi');

        $this->assertSame('CONNECT mail.x.test:465 tcp/ssl', $driver->commands[0]);
        $this->assertContains('AUTH PLAIN ' . base64_encode("\0u\0p"), $driver->commands, 'AUTH LOGIN ditolak → AUTH PLAIN');
    }
}
