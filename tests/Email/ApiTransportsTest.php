<?php
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Klien PSR-18 palsu untuk CVendor_MailerSend: mencatat request, membalas 202.
 */
class UjiEmail_RecordingPsrClient implements Psr\Http\Client\ClientInterface {
    /** @var RequestInterface[] */
    public $requests = [];

    /** @var int */
    public $status = 202;

    /** @var string */
    public $body = '{}';

    public function sendRequest(RequestInterface $request): ResponseInterface {
        $this->requests[] = $request;

        return new GuzzleHttp\Psr7\Response($this->status, ['Content-Type' => 'application/json', 'X-Message-Id' => 'ms-123'], $this->body);
    }
}

/**
 * Transport MailerSend dan KirimEmail: email Symfony → request API, tanpa jaringan.
 */
class ApiTransportsTest extends TestCase {
    /** @var array */
    protected $originalMailers;

    protected function setUp(): void {
        $this->originalMailers = CConfig::repository()->get('email.mailers');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('email.mailers', $this->originalMailers);
        CEmail::manager()->purge('ms_uji');
        CEmail::manager()->purge('ke_uji');
        $property = new ReflectionProperty(CHTTP_Client::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Singleton CHTTP_Client baru supaya stub fake() sebelumnya tidak ikut.
     *
     * @return CHTTP_Client
     */
    protected function freshHttpClient() {
        $property = new ReflectionProperty(CHTTP_Client::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, null);

        return CHTTP::client();
    }

    /**
     * @return Symfony\Component\Mime\Email
     */
    protected function sampleEmail() {
        return (new Symfony\Component\Mime\Email())
            ->from(new Symfony\Component\Mime\Address('kirim@x.test', 'Pengirim'))
            ->to(new Symfony\Component\Mime\Address('a@x.test', 'Budi'), 'b@x.test')
            ->cc('cc@x.test')->bcc('bcc@x.test')
            ->replyTo(new Symfony\Component\Mime\Address('balas@x.test', 'Balas'))
            ->subject('Uji API')->html('<p>Halo</p>')->text('Halo')
            ->attach('isi', 'catatan.txt', 'text/plain');
    }

    public function testManagerBuildsBothTransports() {
        CConfig::repository()->set('email.mailers.ms_uji', ['transport' => 'mailersend', 'key' => 'ms-key']);
        CConfig::repository()->set('email.mailers.ke_uji', ['transport' => 'kirimemail', 'key' => 'ke-key', 'domain' => 'x.test']);

        $this->assertInstanceOf(CEmail_Transport_MailersendTransport::class, CEmail::mailer('ms_uji')->getSymfonyTransport());
        $this->assertSame('mailersend', (string) CEmail::mailer('ms_uji')->getSymfonyTransport());
        $this->assertInstanceOf(CEmail_Transport_KirimEmailTransport::class, CEmail::mailer('ke_uji')->getSymfonyTransport());
        $this->assertSame('kirimemail', (string) CEmail::mailer('ke_uji')->getSymfonyTransport());
    }

    public function testMailersendParamsAndSend() {
        $psr = new UjiEmail_RecordingPsrClient();
        $vendor = new CVendor_MailerSend(['api_key' => 'ms-key'], new CVendor_MailerSend_Common_HttpLayer(['api_key' => 'ms-key'], $psr));
        $transport = new CEmail_Transport_MailersendTransport($vendor);
        $params = $transport->params($this->sampleEmail());

        $this->assertSame('kirim@x.test', $params->getFrom());
        $this->assertSame('Pengirim', $params->getFromName());
        $this->assertEquals([['email' => 'a@x.test', 'name' => 'Budi'], ['email' => 'b@x.test']], array_map(function ($r) {
            return array_filter($r->toArray());
        }, $params->getRecipients()));
        $this->assertSame('cc@x.test', $params->getCc()[0]->toArray()['email']);
        $this->assertSame('bcc@x.test', $params->getBcc()[0]->toArray()['email']);
        $this->assertSame('balas@x.test', $params->getReplyTo());
        $this->assertSame('Balas', $params->getReplyToName());
        $this->assertSame('Uji API', $params->getSubject());
        $this->assertSame('<p>Halo</p>', $params->getHtml());
        $this->assertSame('Halo', $params->getText());
        $this->assertEquals(['content' => base64_encode('isi'), 'filename' => 'catatan.txt', 'disposition' => 'attachment'], array_filter($params->getAttachments()[0]->toArray()));

        $mailer = new CEmail_Mailer('ms', $transport);
        $sent = $mailer->send(['html' => new CBase_HtmlString('<p>Halo</p>')], [], function (CEmail_Message $message) {
            $message->from('kirim@x.test', 'Pengirim')->to('a@x.test')->subject('Uji');
        });
        $this->assertCount(1, $psr->requests);
        $request = $psr->requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/v1/email', (string) $request->getUri());
        $this->assertSame('Bearer ms-key', $request->getHeaderLine('Authorization'));
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('kirim@x.test', $body['from']['email']);
        $this->assertSame('a@x.test', $body['to'][0]['email']);
        $this->assertSame('ms-123', $sent->getOriginalMessage()->getHeaders()->get('X-Message-Id')->getBodyAsString());

        $psr->status = 422;
        $psr->body = '{"message":"The from.email must be verified.","errors":{"from.email":["The from.email must be verified."]}}';
        $this->expectException(Symfony\Component\Mailer\Exception\TransportException::class);
        $this->expectExceptionMessage('(422): The from.email must be verified.');
        $mailer->send(['html' => new CBase_HtmlString('x')], [], function (CEmail_Message $message) {
            $message->from('kirim@x.test', 'Pengirim')->to('a@x.test')->subject('Uji');
        });
    }

    public function testMailersendRequiresFromAndRecipient() {
        $transport = new CEmail_Transport_MailersendTransport(new CVendor_MailerSend(['api_key' => 'k'], new CVendor_MailerSend_Common_HttpLayer(['api_key' => 'k'], new UjiEmail_RecordingPsrClient())));
        $this->expectException(Symfony\Component\Mailer\Exception\TransportException::class);
        $transport->params((new Symfony\Component\Mime\Email())->to('a@x.test')->subject('s')->text('t'));
    }

    public function testKirimEmailFieldsAndSend() {
        $transport = new CEmail_Transport_KirimEmailTransport('ke-key');
        $fields = $transport->fields($this->sampleEmail());

        $this->assertSame('kirim@x.test', $fields['from']);
        $this->assertSame('Pengirim', $fields['from_name']);
        $this->assertSame('"Budi" <a@x.test>;b@x.test', $fields['to'], 'daftar dipisah ; seperti driver lama');
        $this->assertSame('cc@x.test', $fields['cc']);
        $this->assertSame('bcc@x.test', $fields['bcc']);
        $this->assertSame('Uji API', $fields['subject']);
        $this->assertSame('<p>Halo</p>', $fields['html']);
        $this->assertSame('Halo', $fields['text']);
        $this->assertSame(['Reply-To' => '"Balas" <balas@x.test>'], $fields['headers']);
        $this->assertSame(['name' => 'catatan.txt', 'type' => 'text/plain', 'content' => base64_encode('isi')], $fields['attachments'][0]);

        $client = $this->freshHttpClient();
        $client->fake(['aplikasi.kirim.email/*' => CHTTP_Client::response(['data' => ['id' => 'ke-77']], 200)]);
        $sent = (new CEmail_Mailer('ke', $transport))->send(['html' => new CBase_HtmlString('<p>Halo</p>')], [], function (CEmail_Message $message) {
            $message->from('kirim@x.test')->to('a@x.test')->subject('Uji');
        });
        $client->assertSent(function (CHTTP_Client_Request $request) {
            return $request->url() === CEmail_Transport_KirimEmailTransport::ENDPOINT
                && $request->hasHeader('Domain', 'x.test')
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('api:ke-key'))
                && $request->isForm()
                && $request['from'] === 'kirim@x.test'
                && $request['to'] === 'a@x.test'
                && $request['html'] === '<p>Halo</p>';
        });
        $this->assertSame('ke-77', $sent->getOriginalMessage()->getHeaders()->get('X-Message-Id')->getBodyAsString());

        $client = $this->freshHttpClient();
        $client->fake(['aplikasi.kirim.email/*' => CHTTP_Client::response('Unauthorized', 401)]);
        $this->expectException(Symfony\Component\Mailer\Exception\TransportException::class);
        $this->expectExceptionMessage('401');
        (new CEmail_Mailer('ke', new CEmail_Transport_KirimEmailTransport('salah', 'x.test')))->send(['html' => new CBase_HtmlString('x')], [], function (CEmail_Message $message) {
            $message->from('kirim@x.test')->to('a@x.test')->subject('Uji');
        });
    }

    public function testKirimEmailWithoutKeyFailsBeforeCallingTheApi() {
        CHTTP::client()->fake();
        $this->expectException(Symfony\Component\Mailer\Exception\TransportException::class);
        $this->expectExceptionMessage('api key');
        (new CEmail_Mailer('ke', new CEmail_Transport_KirimEmailTransport('')))->send(['html' => new CBase_HtmlString('x')], [], function (CEmail_Message $message) {
            $message->from('kirim@x.test')->to('a@x.test')->subject('Uji');
        });
    }
}
