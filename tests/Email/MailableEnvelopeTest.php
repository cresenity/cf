<?php
use PHPUnit\Framework\TestCase;

/**
 * Mailable gaya envelope()/content()/headers()/attachments() tanpa build(), assertion has*, attachMany,
 * fasad CEmail::to(), dan pengalihan non-produksi.
 */
class MailableEnvelopeStyleMailable extends CEmail_Mailable {
    public function envelope() {
        return (new CEmail_Mailable_Envelope())
            ->from('app@x.test', 'Aplikasi')
            ->to('a@x.test', 'Si A')
            ->cc('c@x.test')
            ->bcc('b@x.test')
            ->replyTo('r@x.test')
            ->subject('Judul Uji')
            ->tag('welcome')
            ->metadata('order', '42')
            ->using(function ($message) {
                $message->getHeaders()->addTextHeader('X-Probe', '1');
            });
    }

    public function content() {
        $builder = CEmail::builder()->createRuntimeBuilder();
        $builder->addBody()->addSection()->addColumn()->addText()->add('Halo dari builder');

        return (new CEmail_Mailable_Content())->builder($builder);
    }

    public function headers() {
        return new CEmail_Mailable_Headers('abc123@x.test', ['ref1@x.test', '<ref2@x.test>'], ['X-Campaign' => 'ok']);
    }

    public function attachments() {
        return [
            CEmail_Attachment::fromData(function () {
                return 'ISI';
            }, 'a.txt')->withMime('text/plain'),
            '/tmp/lampiran-uji.txt',
        ];
    }
}

class MailableEnvelopeLegacyAndEnvelopeMailable extends CEmail_Mailable {
    public function build() {
        $this->to('lama@x.test')->html('<p>lama</p>');
    }

    public function envelope() {
        return new CEmail_Mailable_Envelope(['to' => 'baru@x.test', 'subject' => 'Gabungan', 'from' => 'f@x.test']);
    }
}

class MailableEnvelopeForeignMethodsMailable extends CEmail_Mailable {
    public function build() {
        $this->to('a@x.test')->from('f@x.test')->subject('Tanpa envelope')->html('<p>x</p>');
    }

    public function envelope() {
        return 'bukan objek envelope';
    }

    public function content() {
        return ['bukan' => 'content'];
    }

    public function headers() {
        return null;
    }

    public function attachments() {
        return 'bukan daftar';
    }
}

class MailableEnvelopeTest extends TestCase {
    /** @var CEmail_Transport_ArrayTransport */
    protected $transport;

    /** @var CEmail_Mailer */
    protected $mailer;

    protected function setUp(): void {
        $this->transport = new CEmail_Transport_ArrayTransport();
        $this->mailer = new CEmail_Mailer('array', $this->transport);
    }

    /**
     * @return \Symfony\Component\Mime\Email
     */
    protected function lastEmail() {
        return $this->transport->messages()->last()->getOriginalMessage();
    }

    public function testEnvelopeStyleMailableWithoutBuildIsSentWithAllParts() {
        (new MailableEnvelopeStyleMailable())->send($this->mailer);
        $email = $this->lastEmail();

        $this->assertSame('app@x.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('Aplikasi', $email->getFrom()[0]->getName());
        $this->assertSame('a@x.test', $email->getTo()[0]->getAddress());
        $this->assertSame('Si A', $email->getTo()[0]->getName());
        $this->assertSame('c@x.test', $email->getCc()[0]->getAddress());
        $this->assertSame('b@x.test', $email->getBcc()[0]->getAddress());
        $this->assertSame('r@x.test', $email->getReplyTo()[0]->getAddress());
        $this->assertSame('Judul Uji', $email->getSubject());
        $this->assertSame('welcome', $email->getHeaders()->get('X-Tag')->getBodyAsString());
        $this->assertSame('42', $email->getHeaders()->get('X-Metadata-order')->getBodyAsString());
        $this->assertSame('1', $email->getHeaders()->get('X-Probe')->getBodyAsString(), 'callback using() dijalankan');
        $this->assertStringContainsString('Halo dari builder', $email->getHtmlBody(), 'Content::builder() merender CEmail_Builder');
    }

    public function testHeadersMessageIdReferencesAndText() {
        (new MailableEnvelopeStyleMailable())->send($this->mailer);
        $headers = $this->lastEmail()->getHeaders();

        $this->assertSame(['abc123@x.test'], $headers->get('Message-ID')->getIds());
        $this->assertSame('<ref1@x.test> <ref2@x.test>', $headers->get('References')->getBodyAsString());
        $this->assertSame('ok', $headers->get('X-Campaign')->getBodyAsString());
    }

    public function testAttachmentsFromEnvelopeStyleMailable() {
        $mailable = new MailableEnvelopeStyleMailable();
        $mailable->send($this->mailer);

        $this->assertCount(1, $mailable->rawAttachments, 'Attachment::fromData menjadi lampiran data');
        $this->assertSame('a.txt', $mailable->rawAttachments[0]['name']);
        $this->assertSame('/tmp/lampiran-uji.txt', $mailable->attachments[0]['file'], 'path string langsung dilampirkan');
    }

    public function testRenderThenSendDoesNotDuplicate() {
        $mailable = new MailableEnvelopeStyleMailable();
        $mailable->render();
        $mailable->send($this->mailer);
        $mailable->send($this->mailer);
        $email = $this->lastEmail();

        $this->assertCount(1, $email->getTo());
        $this->assertCount(1, $email->getHeaders()->all('X-Tag'));
        $this->assertCount(1, $email->getHeaders()->all('X-Campaign'));
    }

    public function testBuildAndEnvelopeAreMerged() {
        (new MailableEnvelopeLegacyAndEnvelopeMailable())->send($this->mailer);
        $email = $this->lastEmail();

        $this->assertSame(['lama@x.test', 'baru@x.test'], array_map(function ($address) {
            return $address->getAddress();
        }, $email->getTo()));
        $this->assertSame('Gabungan', $email->getSubject());
        $this->assertSame('<p>lama</p>', $email->getHtmlBody());
    }

    public function testMethodsNamedLikeDefinitionsButReturningOtherTypesAreIgnored() {
        (new MailableEnvelopeForeignMethodsMailable())->send($this->mailer);
        $email = $this->lastEmail();

        $this->assertSame('Tanpa envelope', $email->getSubject());
        $this->assertSame('a@x.test', $email->getTo()[0]->getAddress());
        $this->assertSame('<p>x</p>', $email->getHtmlBody());
    }

    public function testEnvelopeAddressForms() {
        $envelope = (new CEmail_Mailable_Envelope())
            ->to(['x@x.test', ['email' => 'y@x.test', 'name' => 'Y']])
            ->to(new CEmail_Mailable_Address('z@x.test', 'Z'));

        $this->assertCount(3, $envelope->to);
        $this->assertTrue($envelope->hasTo('y@x.test', 'Y'));
        $this->assertTrue($envelope->hasTo('z@x.test'));
        $this->assertFalse($envelope->hasTo('z@x.test', 'Lain'));
        $this->assertFalse($envelope->isFrom('x@x.test'));
        $this->assertTrue($envelope->from('f@x.test', 'F')->isFrom('f@x.test', 'F'));
        $this->assertTrue($envelope->subject('S')->hasSubject('S'));
        $this->assertTrue($envelope->metadata('k', 'v')->hasMetadata('k', 'v'));
        $this->assertFalse($envelope->hasMetadata('k', 'lain'));
    }

    public function testEnvelopeConstructorAttributes() {
        $envelope = new CEmail_Mailable_Envelope([
            'from' => 'f@x.test',
            'to' => ['a@x.test'],
            'cc' => 'c@x.test',
            'subject' => 'S',
            'tags' => ['t1', 't2'],
            'metadata' => ['k' => 'v'],
        ]);

        $this->assertSame('f@x.test', $envelope->from->address);
        $this->assertTrue($envelope->hasTo('a@x.test'));
        $this->assertTrue($envelope->hasCc('c@x.test'));
        $this->assertSame(['t1', 't2'], $envelope->tags);
        $this->assertTrue($envelope->hasMetadata('k', 'v'));
    }

    public function testContentFluentAndWith() {
        $content = (new CEmail_Mailable_Content(['view' => 'a.b', 'with' => ['x' => 1]]))
            ->text('a.text')->markdown('a.md')->html('a.html')->htmlString('<b>x</b>')->with('y', 2)->with(['z' => 3]);

        $this->assertSame('a.b', $content->view);
        $this->assertSame('a.text', $content->text);
        $this->assertSame('a.md', $content->markdown);
        $this->assertSame('a.html', $content->html);
        $this->assertSame('<b>x</b>', $content->htmlString);
        $this->assertSame(['x' => 1, 'y' => 2, 'z' => 3], $content->with);
    }

    public function testContentTemplateRendersSharedTemplate() {
        $template = CEmail::template(['app_name' => 'Toko']);
        $template->bodySection()->addColumn()->addText()->add('Isi dari template');
        $content = (new CEmail_Mailable_Content())->template($template);

        $this->assertStringContainsString('Isi dari template', $content->htmlString);
        $this->assertStringContainsString('Toko', $content->htmlString);
    }

    public function testHeadersReferencesString() {
        $headers = (new CEmail_Mailable_Headers())->messageId('m@x.test')->references(['a@x', '<b@x>'])->text('X-A', '1')->text(['X-B' => '2']);

        $this->assertSame('<a@x> <b@x>', $headers->referencesString());
        $this->assertSame(['X-A' => '1', 'X-B' => '2'], $headers->text);
    }

    public function testAssertHasRecipientsSubjectTagMetadata() {
        $mailable = new MailableEnvelopeStyleMailable();

        $mailable->assertFrom('app@x.test', 'Aplikasi')
            ->assertHasTo('a@x.test')
            ->assertHasTo('a@x.test', 'Si A')
            ->assertHasCc('c@x.test')
            ->assertHasBcc('b@x.test')
            ->assertHasReplyTo('r@x.test')
            ->assertHasSubject('Judul Uji')
            ->assertHasTag('welcome')
            ->assertHasMetadata('order', '42');
    }

    /**
     * @dataProvider failingAssertionProvider
     *
     * @param callable $assertion
     */
    public function testAssertionsFailWhenNotMatching($assertion) {
        $message = null;
        try {
            $assertion(new MailableEnvelopeStyleMailable());
        } catch (Throwable $e) {
            $message = $e->getMessage();
        }

        $this->assertNotNull($message, 'assertion harus gagal');
        $this->assertMatchesRegularExpression('/Did not (see|find)|was not from/', $message);
    }

    /**
     * @return array
     */
    public function failingAssertionProvider() {
        return [
            'to' => [function ($m) {
                $m->assertHasTo('tidak@ada.test');
            }],
            'cc' => [function ($m) {
                $m->assertHasCc('tidak@ada.test');
            }],
            'bcc' => [function ($m) {
                $m->assertHasBcc('tidak@ada.test');
            }],
            'replyTo' => [function ($m) {
                $m->assertHasReplyTo('tidak@ada.test');
            }],
            'from' => [function ($m) {
                $m->assertFrom('tidak@ada.test');
            }],
            'subject' => [function ($m) {
                $m->assertHasSubject('Lain');
            }],
            'tag' => [function ($m) {
                $m->assertHasTag('lain');
            }],
            'metadata' => [function ($m) {
                $m->assertHasMetadata('order', '0');
            }],
            'attachment' => [function ($m) {
                $m->assertHasAttachment('/tmp/tidak-ada.txt');
            }],
        ];
    }

    public function testAssertHasAttachmentVariants() {
        $mailable = new CEmail_Mailable();
        $mailable->attach('/tmp/a.txt', ['as' => 'a.txt'])
            ->attachData('DATA', 'd.txt', ['mime' => 'text/plain'])
            ->attachFromStorageDisk('local', 'dir/f.txt', 'f.txt');

        $mailable->assertHasAttachment('/tmp/a.txt', ['as' => 'a.txt'])
            ->assertHasAttachedData('DATA', 'd.txt', ['mime' => 'text/plain'])
            ->assertHasAttachmentFromStorageDisk('local', 'dir/f.txt', 'f.txt');
        $this->assertTrue($mailable->hasAttachment(CEmail_Attachment::fromPath('/tmp/a.txt')->as('a.txt')));
        $this->assertTrue($mailable->hasAttachment(CEmail_Attachment::fromData(function () {
            return 'DATA';
        }, 'd.txt')->withMime('text/plain')));
        $this->assertFalse($mailable->hasAttachmentFromStorage('dir/f.txt', 'f.txt'), 'disk berbeda (null vs local)');
    }

    public function testAttachMany() {
        $mailable = new CEmail_Mailable();
        $mailable->attachMany(['/tmp/a.txt', '/tmp/b.txt' => ['as' => 'b2.txt'], CEmail_Attachment::fromData(function () {
            return 'X';
        }, 'x.txt')]);

        $this->assertSame('/tmp/a.txt', $mailable->attachments[0]['file']);
        $this->assertSame(['as' => 'b2.txt'], $mailable->attachments[1]['options']);
        $this->assertCount(1, $mailable->rawAttachments);
    }

    public function testFacadeToCcBccReturnPendingMail() {
        $this->assertInstanceOf(CEmail_PendingMail::class, CEmail::to('a@x.test'));
        $this->assertInstanceOf(CEmail_PendingMail::class, CEmail::cc('a@x.test'));
        $this->assertInstanceOf(CEmail_PendingMail::class, CEmail::bcc('a@x.test'));
    }

    public function testNonProductionToRedirectsEveryRecipient() {
        $this->assertFalse(CF::isProduction(), 'suite berjalan di lingkungan non-produksi');
        $originalTo = CConfig::repository()->get('email.non_production_to');
        CConfig::repository()->set('email.mailers.uji_redirect', ['transport' => 'array']);
        CConfig::repository()->set('email.non_production_to', ['address' => 'dev@x.test', 'name' => 'Dev']);

        try {
            $manager = new CEmail_MailManager();
            (new MailableEnvelopeStyleMailable())->mailer('uji_redirect')->send($manager);
            $email = $manager->mailer('uji_redirect')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

            $this->assertCount(1, $email->getTo());
            $this->assertSame('dev@x.test', $email->getTo()[0]->getAddress());
            $this->assertCount(0, $email->getCc());
            $this->assertCount(0, $email->getBcc());
        } finally {
            CConfig::repository()->set('email.non_production_to', $originalTo);
            CConfig::repository()->set('email.mailers.uji_redirect', null);
        }
    }

    public function testNonProductionToWithoutAddressDoesNotRedirect() {
        CConfig::repository()->set('email.mailers.uji_noredirect', ['transport' => 'array']);
        $manager = new CEmail_MailManager();
        (new MailableEnvelopeStyleMailable())->mailer('uji_noredirect')->send($manager);
        $email = $manager->mailer('uji_noredirect')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        $this->assertSame('a@x.test', $email->getTo()[0]->getAddress());
        $this->assertCount(1, $email->getCc());
        CConfig::repository()->set('email.mailers.uji_noredirect', null);
    }
}
