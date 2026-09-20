<?php
use PHPUnit\Framework\TestCase;

/**
 * CEmail_Transport_BrevoTransport: email Symfony → payload REST Brevo, lewat CHTTP client palsu.
 */
class BrevoTransportTest extends TestCase {
    /** @var array */
    protected $originalMailers;

    protected function setUp(): void {
        $this->originalMailers = CConfig::repository()->get('email.mailers');
        CEmail::manager()->purge('brevo');
        CEmail::manager()->purge('brevo_uji');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('email.mailers', $this->originalMailers);
        CEmail::manager()->purge('brevo');
        CEmail::manager()->purge('brevo_uji');
        $property = new ReflectionProperty(CHTTP_Client::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    public function testManagerBuildsTheBrevoTransportFromConfig() {
        CConfig::repository()->set('email.mailers.brevo_uji', ['transport' => 'brevo', 'key' => 'xkeysib-uji']);
        $mailer = CEmail::mailer('brevo_uji');

        $this->assertInstanceOf(CEmail_Transport_BrevoTransport::class, $mailer->getSymfonyTransport());
        $this->assertSame('brevo', (string) $mailer->getSymfonyTransport());
        $this->assertInstanceOf(CEmail_Transport_BrevoTransport::class, CEmail::manager()->build(['transport' => 'brevo', 'key' => 'k'])->getSymfonyTransport(), 'config bawaan mailers.brevo tidak lagi Unsupported mail transport');
    }

    public function testSendPostsTheBrevoPayloadAndRecordsTheMessageId() {
        $client = CHTTP::client();
        $client->fake([
            'api.brevo.com/v3/smtp/email' => CHTTP_Client::response(['messageId' => '<202609@smtp-relay.brevo.com>'], 201),
        ]);
        CConfig::repository()->set('email.mailers.brevo_uji', ['transport' => 'brevo', 'key' => 'xkeysib-uji']);

        $sent = CEmail::mailer('brevo_uji')->html('<p>Halo</p>', function (CEmail_Message $message) {
            $message->from('kirim@x.test', 'Pengirim')->to('a@x.test', 'Budi')->to('b@x.test')->cc('cc@x.test')->bcc('bcc@x.test')
                ->replyTo('balas@x.test', 'Balas')->subject('Uji Brevo')
                ->attachData('isi', 'catatan.txt', ['mime' => 'text/plain'])
                ->getHeaders()->addTextHeader('X-Kampanye', 'sept');
        });

        $this->assertInstanceOf(CEmail_SentMessage::class, $sent);
        $client->assertSentCount(1);
        $client->assertSent(function (CHTTP_Client_Request $request) {
            $payload = $request->data();

            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'xkeysib-uji')
                && $payload['sender'] === ['email' => 'kirim@x.test', 'name' => 'Pengirim']
                && $payload['to'] === [['email' => 'a@x.test', 'name' => 'Budi'], ['email' => 'b@x.test']]
                && $payload['cc'] === [['email' => 'cc@x.test']]
                && $payload['bcc'] === [['email' => 'bcc@x.test']]
                && $payload['replyTo'] === ['email' => 'balas@x.test', 'name' => 'Balas']
                && $payload['subject'] === 'Uji Brevo'
                && $payload['htmlContent'] === '<p>Halo</p>'
                && $payload['attachment'] === [['name' => 'catatan.txt', 'content' => base64_encode('isi')]]
                && $payload['headers'] === ['X-Kampanye' => 'sept'];
        });
        $this->assertSame('<202609@smtp-relay.brevo.com>', $sent->getOriginalMessage()->getHeaders()->get('X-Message-Id')->getBodyAsString());
    }

    public function testApiRejectionBecomesATransportException() {
        CHTTP::client()->fake([
            'api.brevo.com/*' => CHTTP_Client::response(['code' => 'unauthorized', 'message' => 'Key not found'], 401),
        ]);
        CConfig::repository()->set('email.mailers.brevo_uji', ['transport' => 'brevo', 'key' => 'salah']);

        try {
            CEmail::mailer('brevo_uji')->html('<p>x</p>', function (CEmail_Message $message) {
                $message->from('kirim@x.test')->to('a@x.test')->subject('s');
            });
            $this->fail('penolakan API harus melempar');
        } catch (Symfony\Component\Mailer\Exception\TransportException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
            $this->assertStringContainsString('Key not found', $e->getMessage());
            $this->assertInstanceOf(CVendor_Brevo_Exception::class, $e->getPrevious());
        }
    }

    public function testPayloadRequiresSenderAndRecipient() {
        $transport = new CEmail_Transport_BrevoTransport(CVendor_Brevo::transactionalEmail(['apiKey' => 'k']));
        $email = (new Symfony\Component\Mime\Email())->to('a@x.test')->subject('s')->text('t');
        try {
            $transport->payload($email);
            $this->fail('tanpa from harus ditolak');
        } catch (Symfony\Component\Mailer\Exception\TransportException $e) {
            $this->assertStringContainsString('from', $e->getMessage());
        }
        $payload = $transport->payload((new Symfony\Component\Mime\Email())->from('k@x.test')->to('a@x.test')->subject('s')->text('teks saja'));
        $this->assertSame('teks saja', $payload['textContent']);
        $this->assertArrayNotHasKey('htmlContent', $payload);
        $this->assertArrayNotHasKey('cc', $payload);
        $this->assertArrayNotHasKey('attachment', $payload);
    }
}
