<?php
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

/**
 * Port MailMailerTest + MailMessageTest hulu ke CEmail_Mailer/CEmail_Message di atas
 * CEmail_Transport_ArrayTransport (tanpa SMTP): isi html/raw, alamat global (alwaysFrom/
 * alwaysTo/alwaysReplyTo/alwaysReturnPath), event MessageSending/MessageSent, pembungkus
 * Message (from/to/cc/bcc/replyTo/subject/priority/attach/embed).
 */
class MailerAndMessageTest extends TestCase {
    /** @var CEmail_Transport_ArrayTransport */
    protected $transport;

    protected function setUp(): void {
        $this->transport = new CEmail_Transport_ArrayTransport();
    }

    protected function tearDown(): void {
        CEvent::dispatcher()->forget(CEmail_Event_MessageSending::class);
        CEvent::dispatcher()->forget(CEmail_Event_MessageSent::class);
    }

    /**
     * @return CEmail_Mailer
     */
    protected function mailer() {
        return new CEmail_Mailer('array', $this->transport);
    }

    /**
     * @return Email
     */
    protected function lastEmail() {
        return $this->transport->messages()->last()->getOriginalMessage();
    }

    public function testMailerSendSendsMessageWithProperViewContent() {
        $sent = $this->mailer()->html('<p>Hello Cresenity</p>', function ($message) {
            $message->to('taylor@example.com')->from('hello@example.com')->subject('Halo');
        });
        $this->assertInstanceOf(CEmail_SentMessage::class, $sent);
        $this->assertCount(1, $this->transport->messages());
        $email = $this->lastEmail();
        $this->assertSame('<p>Hello Cresenity</p>', $email->getHtmlBody());
        $this->assertNull($email->getTextBody());
        $this->assertSame('taylor@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('hello@example.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('Halo', $email->getSubject());
    }

    public function testMailerRawSendsPlainText() {
        $this->mailer()->raw('teks polos', function ($message) {
            $message->to('taylor@example.com')->from('hello@example.com');
        });
        $email = $this->lastEmail();
        $this->assertSame('teks polos', $email->getTextBody());
        $this->assertNull($email->getHtmlBody());
    }

    public function testGlobalFromIsRespectedOnAllMessages() {
        $mailer = $this->mailer();
        $mailer->alwaysFrom('hello@cresenity.com', 'Cresenity');
        $mailer->html('<p>x</p>', function ($message) {
            $message->to('taylor@example.com');
        });
        $from = $this->lastEmail()->getFrom();
        $this->assertSame('hello@cresenity.com', $from[0]->getAddress());
        $this->assertSame('Cresenity', $from[0]->getName());
    }

    public function testGlobalReplyToAndReturnPath() {
        $mailer = $this->mailer();
        $mailer->alwaysReplyTo('balas@cresenity.com', 'Balas');
        $mailer->alwaysReturnPath('bounce@cresenity.com');
        $mailer->html('<p>x</p>', function ($message) {
            $message->to('taylor@example.com')->from('hello@example.com');
        });
        $email = $this->lastEmail();
        $this->assertSame('balas@cresenity.com', $email->getReplyTo()[0]->getAddress());
        $this->assertSame('bounce@cresenity.com', $email->getReturnPath()->getAddress());
    }

    public function testGlobalToIsRespectedOnAllMessagesAndDropsCcBcc() {
        $mailer = $this->mailer();
        $mailer->alwaysTo('debug@cresenity.com', 'Debug');
        $mailer->html('<p>x</p>', function ($message) {
            $message->from('hello@example.com')->to('taylor@example.com')->cc('cc@example.com')->bcc('bcc@example.com');
        });
        $email = $this->lastEmail();
        $this->assertCount(1, $email->getTo());
        $this->assertSame('debug@cresenity.com', $email->getTo()[0]->getAddress());
        $this->assertSame([], $email->getCc(), 'cc dibuang saat alwaysTo aktif');
        $this->assertSame([], $email->getBcc());
    }

    public function testMailerToCcBccBuildPendingMailWithMailable() {
        $mailable = new UjiEmail_SimpleMailable('<b>hai</b>');
        $this->mailer()->to('a@example.com')->cc('c@example.com')->bcc('b@example.com')->send($mailable);
        $email = $this->lastEmail();
        $this->assertSame('a@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('c@example.com', $email->getCc()[0]->getAddress());
        $this->assertSame('b@example.com', $email->getBcc()[0]->getAddress());
        $this->assertSame('<b>hai</b>', $email->getHtmlBody());
    }

    public function testMailerToAcceptsObjectsWithEmailAndName() {
        $user = (object) ['email' => 'u@example.com', 'name' => 'User Uji'];
        $this->mailer()->to($user)->send(new UjiEmail_SimpleMailable('x'));
        $to = $this->lastEmail()->getTo()[0];
        $this->assertSame('u@example.com', $to->getAddress());
        $this->assertSame('User Uji', $to->getName());
    }

    public function testEventsAreDispatchedAndSendingCanVeto() {
        $seen = [];
        CEvent::dispatcher()->listen(CEmail_Event_MessageSending::class, function ($event) use (&$seen) {
            $seen[] = 'sending:' . $event->message->getSubject();
        });
        CEvent::dispatcher()->listen(CEmail_Event_MessageSent::class, function ($event) use (&$seen) {
            $seen[] = 'sent:' . $event->sent->getOriginalMessage()->getSubject();
        });
        $this->mailer()->html('<p>x</p>', function ($message) {
            $message->to('a@example.com')->from('b@example.com')->subject('S1');
        });
        $this->assertSame(['sending:S1', 'sent:S1'], $seen);

        CEvent::dispatcher()->listen(CEmail_Event_MessageSending::class, function () {
            return false;
        });
        $result = $this->mailer()->html('<p>x</p>', function ($message) {
            $message->to('a@example.com')->from('b@example.com')->subject('S2');
        });
        $this->assertNull($result, 'listener yang mengembalikan false membatalkan pengiriman');
        $this->assertCount(1, $this->transport->messages());
    }

    public function testMessageDataIsAvailableInSendingEvent() {
        $data = null;
        CEvent::dispatcher()->listen(CEmail_Event_MessageSending::class, function ($event) use (&$data) {
            $data = $event->data;
        });
        $this->mailer()->send(['raw' => 'x'], ['foo' => 'bar'], function ($message) {
            $message->to('a@example.com')->from('b@example.com');
        });
        $this->assertSame('bar', $data['foo']);
        $this->assertInstanceOf(CEmail_Message::class, $data['message']);
    }

    public function testTransportCanBeSwappedAndFlushed() {
        $mailer = $this->mailer();
        $this->assertSame($this->transport, $mailer->getSymfonyTransport());
        $other = new CEmail_Transport_ArrayTransport();
        $mailer->setSymfonyTransport($other);
        $mailer->raw('x', function ($message) {
            $message->to('a@example.com')->from('b@example.com');
        });
        $this->assertCount(0, $this->transport->messages());
        $this->assertCount(1, $other->messages());
        $other->flush();
        $this->assertCount(0, $other->messages());
        $this->assertSame('array', (string) $other);
    }

    // ---- CEmail_Message ----

    /**
     * @return CEmail_Message
     */
    protected function message() {
        return new CEmail_Message(new Email());
    }

    public function testMessageFromMethod() {
        $message = $this->message();
        $this->assertSame($message, $message->from('foo@bar.baz', 'Foo'));
        $from = $message->getSymfonyMessage()->getFrom()[0];
        $this->assertSame('foo@bar.baz', $from->getAddress());
        $this->assertSame('Foo', $from->getName());
    }

    public function testMessageSenderAndReturnPath() {
        $message = $this->message()->sender('foo@bar.baz', 'Foo')->returnPath('bounce@bar.baz');
        $this->assertSame('foo@bar.baz', $message->getSymfonyMessage()->getSender()->getAddress());
        $this->assertSame('bounce@bar.baz', $message->getSymfonyMessage()->getReturnPath()->getAddress());
    }

    public function testMessageToScalarAppendsAndArrayReplaces() {
        $message = $this->message()->to('a@bar.baz', 'A')->to('a2@bar.baz');
        $this->assertCount(2, $message->getSymfonyMessage()->getTo(), 'bentuk skalar menambah (addTo)');
        $message->to(['b@bar.baz' => 'B', 'c@bar.baz']);
        $to = $message->getSymfonyMessage()->getTo();
        $this->assertCount(2, $to, 'bentuk array mengganti seluruh daftar (to), seperti hulu');
        $this->assertSame('B', $to[0]->getName());
        $this->assertSame('b@bar.baz', $to[0]->getAddress());
        $this->assertSame('c@bar.baz', $to[1]->getAddress());
        $message->to('z@bar.baz', 'Z', true);
        $this->assertCount(1, $message->getSymfonyMessage()->getTo(), 'override=true mengganti seluruh daftar');
        $message->forgetTo();
        $this->assertSame([], $message->getSymfonyMessage()->getTo());
    }

    public function testMessageCcBccReplyToSubjectPriority() {
        $message = $this->message()->cc('c@bar.baz')->bcc('b@bar.baz')->replyTo('r@bar.baz', 'R')->subject('Subjek')->priority(1);
        $email = $message->getSymfonyMessage();
        $this->assertSame('c@bar.baz', $email->getCc()[0]->getAddress());
        $this->assertSame('b@bar.baz', $email->getBcc()[0]->getAddress());
        $this->assertSame('R', $email->getReplyTo()[0]->getName());
        $this->assertSame('Subjek', $email->getSubject());
        $this->assertSame(1, $email->getPriority());
        $message->forgetCc()->forgetBcc();
        $this->assertSame([], $email->getCc());
        $this->assertSame([], $email->getBcc());
    }

    public function testMessageAttachDataAndEmbedData() {
        $message = $this->message()->attachData('isi berkas', 'lampiran.txt', ['mime' => 'text/plain']);
        $attachments = $message->getSymfonyMessage()->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('isi berkas', $attachments[0]->getBody());
        $this->assertSame('lampiran.txt', $attachments[0]->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
        $this->assertSame('text/plain', $attachments[0]->getPreparedHeaders()->get('Content-Type')->getBody());

        $cid = $message->embedData('PNGDATA', 'logo', 'image/png');
        $this->assertSame('cid:logo', $cid);
        $this->assertCount(2, $message->getSymfonyMessage()->getAttachments());
    }

    public function testMessageAttachAndEmbedFromPath() {
        $file = tempnam(sys_get_temp_dir(), 'uji-mail');
        file_put_contents($file, 'dari berkas');
        try {
            $message = $this->message()->attach($file, ['as' => 'nama.txt', 'mime' => 'text/plain']);
            $attachment = $message->getSymfonyMessage()->getAttachments()[0];
            $this->assertSame('nama.txt', $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
            $this->assertSame('dari berkas', $attachment->getBody());
            $cid = $message->embed($file);
            $this->assertStringStartsWith('cid:', $cid);
            $this->assertSame(14, strlen($cid), 'cid acak 10 karakter');
        } finally {
            unlink($file);
        }
    }

    public function testMessageForwardsUnknownCallsToSymfonyEmail() {
        $message = $this->message();
        $this->assertSame($message, $message->text('polos'), 'pemanggilan yang mengembalikan Email dibungkus lagi');
        $this->assertSame('polos', $message->getSymfonyMessage()->getTextBody());
        $this->assertSame('polos', $message->getTextBody(), 'nilai skalar diteruskan apa adanya');
    }
}

class UjiEmail_SimpleMailable extends CEmail_Mailable {
    /** @var string */
    protected $body;

    public function __construct($body) {
        $this->body = $body;
    }

    public function build() {
        return $this->from('noreply@cresenity.com', 'Cresenity')->html($this->body);
    }
}
