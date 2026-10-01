<?php
use PHPUnit\Framework\TestCase;

/**
 * Konflik provider ditebak dari smtp_host vs MAIL_MAILER eksplisit: pesan, peringatan sekali per proses tanpa
 * mengubah provider yang dipakai, dan CEmail_ConfigAuditor (dipakai `phpcf email:check`).
 */
class ConfigMailerConflictTest extends TestCase {
    /** @var mixed */
    protected $originalMailer;

    /** @var bool */
    protected $hadEnvEntry;

    /** @var mixed */
    protected $envOriginal;

    /** @var bool */
    protected $hadServerEntry;

    /** @var mixed */
    protected $serverOriginal;

    protected function setUp(): void {
        $this->originalMailer = getenv('MAIL_MAILER');
        $this->hadEnvEntry = array_key_exists('MAIL_MAILER', $_ENV);
        $this->envOriginal = $this->hadEnvEntry ? $_ENV['MAIL_MAILER'] : null;
        $this->hadServerEntry = array_key_exists('MAIL_MAILER', $_SERVER);
        $this->serverOriginal = $this->hadServerEntry ? $_SERVER['MAIL_MAILER'] : null;
    }

    protected function tearDown(): void {
        putenv($this->originalMailer === false ? 'MAIL_MAILER' : 'MAIL_MAILER=' . $this->originalMailer);
        if ($this->hadEnvEntry) {
            $_ENV['MAIL_MAILER'] = $this->envOriginal;
        } else {
            unset($_ENV['MAIL_MAILER']);
        }
        if ($this->hadServerEntry) {
            $_SERVER['MAIL_MAILER'] = $this->serverOriginal;
        } else {
            unset($_SERVER['MAIL_MAILER']);
        }
    }

    /**
     * @param null|string $value
     *
     * @return void
     */
    protected function setMailerEnv($value) {
        putenv($value === null ? 'MAIL_MAILER' : 'MAIL_MAILER=' . $value);
        if ($value === null) {
            unset($_ENV['MAIL_MAILER'], $_SERVER['MAIL_MAILER']);
        } else {
            $_ENV['MAIL_MAILER'] = $value;
            $_SERVER['MAIL_MAILER'] = $value;
        }
    }

    /**
     * @param array $overrides
     *
     * @return array
     */
    protected function settings(array $overrides = []) {
        return array_merge([
            'smtp_host' => 'smtp.sendgrid.net',
            'smtp_password' => 'kunci',
            'smtp_from' => 'noreply@app.example.com',
            'mail_mailer' => null,
            'domain' => 'app.example.com',
            'sender_call_files' => 0,
        ], $overrides);
    }

    /**
     * @param array $findings
     *
     * @return array
     */
    protected function keys(array $findings) {
        return array_map(function ($finding) {
            return $finding['level'] . ':' . $finding['key'];
        }, $findings);
    }

    public function testConflictMessageWhenGuessedProviderDiffersFromMailer() {
        $message = CEmail_Config::mailerEnvConflict('sendgrid', 'smtp.sendgrid.net', 'brevo');

        $this->assertStringContainsString("provider 'sendgrid' ditebak dari smtp_host 'smtp.sendgrid.net'", $message);
        $this->assertStringContainsString("MAIL_MAILER='brevo'", $message);
        $this->assertStringContainsString("isi 'driver'", $message);
    }

    public function testNoConflictWhenMailerIsBlankOrEquivalent() {
        $this->assertNull(CEmail_Config::mailerEnvConflict('sendgrid', 'h', null));
        $this->assertNull(CEmail_Config::mailerEnvConflict('sendgrid', 'h', ''));
        $this->assertNull(CEmail_Config::mailerEnvConflict('brevo', 'h', 'brevo'));
        $this->assertNull(CEmail_Config::mailerEnvConflict('smtp', 'h', 'smtp'));
        $this->assertNull(CEmail_Config::mailerEnvConflict('postmarkapp', 'h', 'postmark'), 'nama driver dan transport yang setara tidak dianggap konflik');
    }

    public function testMailerNameIsResolvedThroughItsConfiguredTransport() {
        CConfig::repository()->set('email.mailers.uji_alias', ['transport' => 'brevo']);

        try {
            $this->assertNull(CEmail_Config::mailerEnvConflict('brevo', 'h', 'uji_alias'), 'mailer bernama alias dengan transport brevo');
            $this->assertNotNull(CEmail_Config::mailerEnvConflict('sendgrid', 'h', 'uji_alias'));
        } finally {
            CConfig::repository()->set('email.mailers.uji_alias', null);
        }
    }

    public function testMailerIsReadFromEnvWhenNotGiven() {
        $this->setMailerEnv(null);
        $this->assertNull(CEmail_Config::mailerEnvConflict('sendgrid', 'smtp.sendgrid.net'));

        $this->setMailerEnv('brevo');

        $this->assertNotNull(CEmail_Config::mailerEnvConflict('sendgrid', 'smtp.sendgrid.net'));
    }

    public function testConflictDoesNotChangeTheProviderUsedBySender() {
        $this->setMailerEnv('brevo');

        $config = new CEmail_Config(['smtp_host' => 'smtp.sendgrid.net', 'smtp_username' => 'u', 'smtp_password' => 'p']);

        $this->assertSame('sendgrid', $config->toMailerConfig()['transport'], 'provider tetap hasil tebakan smtp_host');
    }

    public function testConflictIsReportedOncePerProcess() {
        $this->setMailerEnv('brevo');
        $property = new ReflectionProperty(CEmail_Config::class, 'reportedConflicts');
        $property->setAccessible(true);
        $property->setValue(null, []);

        new CEmail_Config(['smtp_host' => 'smtp.sendgrid.net', 'smtp_password' => 'p']);
        new CEmail_Config(['smtp_host' => 'smtp.sendgrid.net', 'smtp_password' => 'p']);

        $this->assertCount(1, $property->getValue(), 'pesan yang sama dicatat sekali');
    }

    public function testExplicitDriverNeverReportsAConflict() {
        $this->setMailerEnv('brevo');
        $property = new ReflectionProperty(CEmail_Config::class, 'reportedConflicts');
        $property->setAccessible(true);
        $property->setValue(null, []);

        new CEmail_Config(['driver' => 'sendgrid', 'password' => 'p']);

        $this->assertCount(0, $property->getValue());
    }

    public function testAuditorFindsNothingForACleanConfig() {
        $this->assertSame([], (new CEmail_ConfigAuditor())->audit($this->settings()));
        $this->assertSame([], (new CEmail_ConfigAuditor())->audit($this->settings(['smtp_host' => '', 'mail_mailer' => 'brevo'])), 'tanpa smtp_host tidak ada yang diperiksa');
    }

    public function testAuditorWarnsOnMailerConflictAndEmptyProviderPassword() {
        $findings = (new CEmail_ConfigAuditor())->audit($this->settings(['mail_mailer' => 'brevo', 'smtp_password' => '']));

        $this->assertSame(['warning:MAIL_MAILER', 'warning:smtp_password'], $this->keys($findings));
    }

    public function testAuditorEmptyPasswordIsFineForAnUnknownSmtpHost() {
        $findings = (new CEmail_ConfigAuditor())->audit($this->settings(['smtp_host' => 'mail.hosting.example', 'smtp_password' => '']));

        $this->assertSame([], $findings, 'host SMTP biasa boleh tanpa kata sandi (relay lokal)');
    }

    public function testAuditorNoticesStaleFromDomainButAcceptsParentAndSubdomain() {
        $auditor = new CEmail_ConfigAuditor();

        $stale = $auditor->audit($this->settings(['smtp_from' => 'noreply@traderquest.dev.ittron.co.id']));
        $this->assertSame(['notice:smtp_from'], $this->keys($stale));

        $this->assertSame([], $auditor->audit($this->settings(['smtp_from' => 'noreply@example.com'])), 'domain induk');
        $this->assertSame([], $auditor->audit($this->settings(['smtp_from' => 'noreply@mail.app.example.com'])), 'subdomain');
        $this->assertSame([], $auditor->audit($this->settings(['smtp_from' => 'Nama <noreply@app.example.com>'])), 'bentuk Nama <alamat>');
        $this->assertSame([], $auditor->audit($this->settings(['smtp_from' => 'noreply@lain.test', 'domain' => ''])), 'domain app kosong dilewati');
    }

    public function testAuditorReportsSenderCallSites() {
        $findings = (new CEmail_ConfigAuditor())->audit($this->settings(['sender_call_files' => 3]));

        $this->assertSame(['notice:CEmail::sender()'], $this->keys($findings));
        $this->assertStringContainsString('3 berkas', $findings[0]['message']);
    }
}
