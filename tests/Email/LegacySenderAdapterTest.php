<?php
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Transport yang selalu gagal dengan pesan tertentu.
 */
class UjiEmail_FailingTransport extends AbstractTransport {
    /** @var string */
    public $reason;

    public function __construct($reason) {
        $this->reason = $reason;
        parent::__construct();
    }

    protected function doSend(SymfonySentMessage $message): void {
        throw new TransportException($this->reason);
    }

    public function __toString(): string {
        return 'failing';
    }
}

/**
 * CEmail::sender()->send() dengan saklar email.legacy_sender_via_mailer: tanda tangan lama, mesin CEmail_Mailer.
 */
class LegacySenderAdapterTest extends TestCase {
    /** @var mixed */
    protected $originalSwitch;

    /** @var array */
    protected $originalApp = [];

    protected function setUp(): void {
        $this->originalSwitch = CConfig::repository()->get('email.legacy_sender_via_mailer');
        foreach (['app.smtp_from', 'app.smtp_from_name', 'app.email'] as $key) {
            $this->originalApp[$key] = CF::config($key);
        }
        CConfig::repository()->set('email.legacy_sender_via_mailer', true);
        CConfig::repository()->set('app.email', null);
        CConfig::repository()->set('app.smtp_from', 'noreply@app.test');
        CConfig::repository()->set('app.smtp_from_name', 'Aplikasi');
        CEmail_Sender_MailerDriver::forgetMailers();
    }

    protected function tearDown(): void {
        CConfig::repository()->set('email.legacy_sender_via_mailer', $this->originalSwitch);
        foreach ($this->originalApp as $key => $value) {
            CConfig::repository()->set($key, $value);
        }
        CEmail_Sender_MailerDriver::forgetMailers();
        CEvent::dispatcher()->forget(CEmail_Event_MessageSending::class);
    }

    /**
     * @return Symfony\Component\Mime\Email
     */
    protected function lastEmail(CEmail_Sender $sender) {
        return $sender->getDriver()->getMailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }

    public function testSwitchSelectsTheAdapterAndKeepsTheDriverContract() {
        $sender = CEmail::sender(['driver' => 'null']);
        $this->assertInstanceOf(CEmail_Sender_MailerDriver::class, $sender->getDriver());
        $this->assertInstanceOf(CEmail_DriverInterface::class, $sender->getDriver(), 'instanceof CEmail_DriverInterface tetap benar');
        $this->assertInstanceOf(CEmail_Transport_ArrayTransport::class, $sender->getDriver()->getMailer()->getSymfonyTransport(), 'driver null → transport array');
        $this->assertSame($sender->getDriver()->getMailer(), CEmail::sender(['driver' => 'null'])->getDriver()->getMailer(), 'mailer di-cache per konfigurasi');

        CConfig::repository()->set('email.legacy_sender_via_mailer', false);
        $this->assertInstanceOf(CEmail_Driver_NullDriver::class, CEmail::sender(['driver' => 'null'])->getDriver(), 'saklar mati → driver lama');
    }

    public function testSendMapsEveryLegacyOptionOntoTheMessage() {
        $file = tempnam(sys_get_temp_dir(), 'uji') . '.txt';
        file_put_contents($file, 'isi berkas');
        $sender = CEmail::sender(['driver' => 'null', 'from' => 'kirim@x.test', 'from_name' => 'Pengirim']);
        $result = $sender->send(
            [['toEmail' => 'a@x.test', 'toName' => 'Budi'], 'Cici <c@x.test>'],
            'Verifikasi — Résumé',
            '<p>Halo <b>dunia</b></p>',
            [
                'cc' => 'cc@x.test',
                'bcc' => [['email' => 'bcc@x.test', 'name' => 'Rahasia']],
                'replyTo' => 'balas@x.test',
                'returnPath' => 'bounce@x.test',
                'priority' => 1,
                'headers' => ['X-Kampanye' => 'sept'],
                'attachments' => [$file, ['data' => 'csv,data', 'name' => 'd.csv', 'mime' => 'text/csv']],
            ]
        );

        $this->assertInstanceOf(CEmail_SentMessage::class, $result);
        $email = $this->lastEmail($sender);
        $this->assertSame('Verifikasi — Résumé', $email->getSubject());
        $this->assertSame('<p>Halo <b>dunia</b></p>', $email->getHtmlBody());
        $this->assertSame('kirim@x.test', $email->getFrom()[0]->getAddress(), 'from dari config pengirim');
        $this->assertSame('Pengirim', $email->getFrom()[0]->getName());
        $this->assertSame(['a@x.test', 'c@x.test'], array_map(function ($a) {
            return $a->getAddress();
        }, $email->getTo()));
        $this->assertSame('Budi', $email->getTo()[0]->getName());
        $this->assertSame('Cici', $email->getTo()[1]->getName());
        $this->assertSame('cc@x.test', $email->getCc()[0]->getAddress());
        $this->assertSame('Rahasia', $email->getBcc()[0]->getName());
        $this->assertSame('balas@x.test', $email->getReplyTo()[0]->getAddress());
        $this->assertSame('bounce@x.test', $email->getReturnPath()->getAddress());
        $this->assertSame(1, $email->getPriority());
        $this->assertSame('sept', $email->getHeaders()->get('X-Kampanye')->getBodyAsString());
        $this->assertCount(2, $email->getAttachments());
        unlink($file);

        $json = json_decode(json_encode($result), true);
        $this->assertSame(['a@x.test', 'c@x.test', 'cc@x.test', 'bcc@x.test'], $json['recipients'], 'nilai balik bisa di-json_encode untuk log_notification.vendor_response');
        $this->assertNotEmpty($json['message_id']);
        $this->assertSame($json['message_id'], (string) $result);
    }

    public function testFromFallsBackToAppConfigAndPerSendOptionsWin() {
        $sender = CEmail::sender(['driver' => 'null']);
        $sender->send('a@x.test', 'Uji', 'x');
        $email = $this->lastEmail($sender);
        $this->assertSame('noreply@app.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('Aplikasi', $email->getFrom()[0]->getName());

        $sender->send('a@x.test', 'Uji', 'x', ['smtp_from' => 'record@x.test', 'smtp_from_name' => 'Dari Record']);
        $email = $this->lastEmail($sender);
        $this->assertSame('record@x.test', $email->getFrom()[0]->getAddress(), 'options smtp_from (bentuk record notifikasi) menang');
        $this->assertSame('Dari Record', $email->getFrom()[0]->getName());
    }

    public function testPlainTypeSendsTextAndVetoReturnsFalse() {
        $sender = CEmail::sender(['driver' => 'null', 'from' => 'kirim@x.test']);
        $sender->send('a@x.test', 'Teks', 'hanya teks', ['type' => 'plain']);
        $email = $this->lastEmail($sender);
        $this->assertSame('hanya teks', $email->getTextBody());
        $this->assertNull($email->getHtmlBody());

        CEvent::dispatcher()->listen(CEmail_Event_MessageSending::class, function () {
            return false;
        });
        $this->assertFalse($sender->send('a@x.test', 'Ditolak', 'x'), 'MessageSending memveto → false, seperti mail() gagal');
    }

    public function testTransportFailuresBecomeTheLegacyExceptionClasses() {
        $cases = [
            ['Failed to authenticate on SMTP server with username "u" using the following authenticators: "LOGIN"', CEmail_Exception_SmtpAuthenticationFailedException::class],
            ['Connection could not be established with host "mail.x.test:587": Connection refused', CEmail_Exception_SmtpConnectionException::class],
            ['Expected response code "250" but got code "550", with message "550 Mailbox unavailable"', CEmail_Exception_EmailSendingFailedException::class],
        ];
        foreach ($cases as list($reason, $expected)) {
            $driver = new CEmail_Sender_MailerDriver(new CEmail_Config(['driver' => 'null', 'from' => 'kirim@x.test']));
            $driver->getMailer()->setSymfonyTransport(new UjiEmail_FailingTransport($reason));
            try {
                $driver->send(['a@x.test'], 'Uji', 'x');
                $this->fail('harus melempar: ' . $reason);
            } catch (Exception $e) {
                $this->assertInstanceOf($expected, $e, $reason);
                $this->assertInstanceOf(TransportException::class, $e->getPrevious(), 'exception Symfony tersimpan di previous');
                $this->assertSame($reason, $e->getMessage());
            }
            CEmail_Sender_MailerDriver::forgetMailers();
        }
    }

    public function testEmailChannelRecordGoesThroughTheAdapter() {
        $sender = CEmail::sender(['smtp_from' => 'record@x.test', 'smtp_from_name' => 'Notifikasi', 'driver' => 'null']);
        $sender->send(['u@x.test'], 'Subjek', '<p>Isi</p>', ['smtp_from' => 'record@x.test', 'smtp_from_name' => 'Notifikasi', 'cc' => [], 'bcc' => [], 'attachments' => []]);
        $email = $this->lastEmail($sender);

        $this->assertSame('record@x.test', $email->getFrom()[0]->getAddress());
        $this->assertSame([], $email->getCc());
        $this->assertSame([], $email->getAttachments());
    }
}
