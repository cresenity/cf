<?php
use PHPUnit\Framework\TestCase;

/**
 * Port MailMailableTest (bagian tanpa view Blade) + MailManager: penerima/from/subject/
 * lampiran/tag/metadata/priority/callback pada CEmail_Mailable, pengiriman lewat mailer array,
 * assert helper (assertSeeInHtml), dan resolusi mailer dari config email.mailers.
 */
class MailableAndManagerTest extends TestCase {
    /** @var CEmail_Transport_ArrayTransport */
    protected $transport;

    /** @var CEmail_Mailer */
    protected $mailer;

    /** @var array */
    protected $originalMailers;

    /** @var string */
    protected $originalDefault;

    protected function setUp(): void {
        $this->transport = new CEmail_Transport_ArrayTransport();
        $this->mailer = new CEmail_Mailer('array', $this->transport);
        $this->originalMailers = CConfig::repository()->get('email.mailers');
        $this->originalDefault = CConfig::repository()->get('email.default');
    }

    protected function tearDown(): void {
        CConfig::repository()->set('email.mailers', $this->originalMailers);
        // setDefaultDriver() menulis ke config global, jadi dipulihkan
        CConfig::repository()->set('email.default', $this->originalDefault);
    }

    /**
     * @return CEmail_Mailable
     */
    protected function mailable() {
        return new UjiEmail_FluentMailable();
    }

    public function testMailableSetsRecipientsCorrectly() {
        $mailable = $this->mailable();
        $mailable->to('taylor@laravel.com');
        $this->assertSame([['name' => null, 'address' => 'taylor@laravel.com']], $mailable->to);
        $this->assertTrue($mailable->hasTo('taylor@laravel.com'));

        $mailable = $this->mailable();
        $mailable->to('taylor@laravel.com', 'Taylor Otwell');
        $this->assertSame([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']], $mailable->to);
        $this->assertTrue($mailable->hasTo('taylor@laravel.com', 'Taylor Otwell'));
        $this->assertTrue($mailable->hasTo('taylor@laravel.com'), 'tanpa nama tetap cocok');
        $this->assertFalse($mailable->hasTo('taylor@laravel.com', 'Nama Lain'), 'nama berbeda tidak cocok');

        $mailable = $this->mailable();
        $mailable->to(['taylor@laravel.com']);
        $this->assertSame([['name' => null, 'address' => 'taylor@laravel.com']], $mailable->to);

        $mailable = $this->mailable();
        $mailable->to([['name' => 'Taylor Otwell', 'email' => 'taylor@laravel.com']]);
        $this->assertSame([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']], $mailable->to);

        $mailable = $this->mailable();
        $mailable->to(new UjiEmail_Recipient());
        $this->assertSame([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']], $mailable->to, 'objek dengan email/name');

        $mailable = $this->mailable();
        $mailable->to(c::collect([new UjiEmail_Recipient()]));
        $this->assertSame([['name' => 'Taylor Otwell', 'address' => 'taylor@laravel.com']], $mailable->to, 'koleksi objek');

        $mailable = $this->mailable();
        $mailable->to(c::collect([new UjiEmail_Recipient(), new UjiEmail_Recipient()]));
        $this->assertCount(1, $mailable->to, 'penerima ganda dirapatkan');
    }

    public function testMailableSetsCcBccReplyToAndFrom() {
        $mailable = $this->mailable();
        $mailable->cc('cc@laravel.com', 'CC')->bcc(['bcc@laravel.com'])->replyTo('reply@laravel.com')->from('from@laravel.com', 'From');
        $this->assertTrue($mailable->hasCc('cc@laravel.com', 'CC'));
        $this->assertTrue($mailable->hasBcc('bcc@laravel.com'));
        $this->assertTrue($mailable->hasReplyTo('reply@laravel.com'));
        $this->assertTrue($mailable->hasFrom('from@laravel.com', 'From'));
        $this->assertFalse($mailable->hasFrom('lain@laravel.com'));
        $this->assertFalse($mailable->hasTo(''), 'alamat kosong = false');
    }

    public function testMailableSubjectAndHasSubject() {
        $mailable = $this->mailable();
        $this->assertSame($mailable, $mailable->subject('Judul'));
        $this->assertTrue($mailable->hasSubject('Judul'));
        $this->assertFalse($mailable->hasSubject('Lain'));
    }

    public function testMailableWithAndViewData() {
        $mailable = $this->mailable();
        $mailable->with('a', 1)->with(['b' => 2, 'c' => 3]);
        $data = $mailable->buildViewData();
        $this->assertSame(1, $data['a']);
        $this->assertSame(2, $data['b']);
        $this->assertSame(3, $data['c']);
        $this->assertArrayHasKey('publicProperty', $data, 'properti publik ikut jadi data view');
        $this->assertSame('nilai', $data['publicProperty']);
    }

    public function testAttachmentsAreDeduplicated() {
        $mailable = $this->mailable();
        $mailable->attach('/tmp/a.txt', ['as' => 'a.txt'])->attach('/tmp/a.txt', ['as' => 'a.txt'])->attach('/tmp/b.txt');
        $this->assertCount(2, $mailable->attachments);
        $mailable->attachData('data', 'x.txt')->attachData('data', 'x.txt')->attachData('lain', 'x.txt');
        $this->assertCount(2, $mailable->rawAttachments, 'kunci = nama + data');
    }

    public function testSendingThroughTheMailerBuildsTheWholeMessage() {
        $mailable = new UjiEmail_FluentMailable();
        $mailable->to('taylor@laravel.com', 'Taylor')->cc('cc@laravel.com')->from('noreply@cresenity.com', 'Cresenity')
            ->subject('Laporan')->html('<h1>Isi</h1>')->priority(1)->tag('laporan')->metadata('order_id', '42')
            ->attachData('csv,data', 'laporan.csv', ['mime' => 'text/csv'])
            ->withSymfonyMessage(function ($message) {
                $message->getHeaders()->addTextHeader('X-Uji', 'ya');
            });
        $this->mailer->send($mailable);
        $email = $this->transport->messages()->last()->getOriginalMessage();
        $this->assertSame('<h1>Isi</h1>', $email->getHtmlBody());
        $this->assertSame('Laporan', $email->getSubject());
        $this->assertSame('Taylor', $email->getTo()[0]->getName());
        $this->assertSame('cc@laravel.com', $email->getCc()[0]->getAddress());
        $this->assertSame('Cresenity', $email->getFrom()[0]->getName());
        $this->assertSame(1, $email->getPriority());
        $this->assertSame('ya', $email->getHeaders()->get('X-Uji')->getBodyAsString());
        $this->assertSame('laporan', $email->getHeaders()->get('X-Tag')->getBodyAsString());
        $this->assertSame('42', $email->getHeaders()->get('X-Metadata-order_id')->getBodyAsString());
        $this->assertSame('laporan.csv', $email->getAttachments()[0]->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
    }

    public function testDefaultSubjectIsDerivedFromTheClassName() {
        $mailable = new UjiEmail_FluentMailable();
        $mailable->to('a@b.co')->from('x@y.co')->html('x');
        $this->mailer->send($mailable);
        $this->assertSame('Fluent Mailable', $this->transport->messages()->last()->getOriginalMessage()->getSubject(), 'nama kelas tanpa prefiks CF (c::classBasename memotong di underscore)');
    }

    public function testMailableSelectsItsOwnMailerFromAFactory() {
        CConfig::repository()->set('email.mailers.uji_array', ['transport' => 'array']);
        $manager = new CEmail_MailManager();
        $mailable = (new UjiEmail_FluentMailable())->mailer('uji_array')->to('a@b.co')->from('x@y.co')->html('<p>via factory</p>');
        $mailable->send($manager);
        $messages = $manager->mailer('uji_array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('<p>via factory</p>', $messages->last()->getOriginalMessage()->getHtmlBody());
    }

    public function testAssertSeeInHtmlHelpers() {
        $mailable = (new UjiEmail_FluentMailable())->from('x@y.co')->html('<p>Halo <b>Dunia</b></p>');
        $mailable->assertSeeInHtml('Halo')->assertSeeInHtml('<b>Dunia</b>')->assertDontSeeInHtml('Tidak ada')->assertSeeInOrderInHtml(['Halo', 'Dunia']);
        try {
            $mailable->assertSeeInHtml('hilang');
            $this->fail('harus gagal');
        } catch (Throwable $e) {
            $this->assertStringContainsString('Did not see expected text [hilang]', $e->getMessage());
        }
    }

    public function testMailableIsMacroableAndConditionable() {
        UjiEmail_FluentMailable::macro('ujiPrefixSubject', function ($prefix) {
            return $this->subject($prefix . ' ' . $this->subject);
        });
        $mailable = (new UjiEmail_FluentMailable())->subject('Judul')->ujiPrefixSubject('[Uji]');
        $this->assertTrue($mailable->hasSubject('[Uji] Judul'));
        $mailable->when(false, function ($m) {
            $m->subject('tidak');
        })->unless(false, function ($m) {
            $m->cc('c@d.co');
        });
        $this->assertTrue($mailable->hasSubject('[Uji] Judul'));
        $this->assertTrue($mailable->hasCc('c@d.co'));
    }

    // ---- MailManager ----

    public function testManagerResolvesArrayAndLogTransportsFromConfig() {
        CConfig::repository()->set('email.mailers.uji_array', ['transport' => 'array']);
        CConfig::repository()->set('email.mailers.uji_log', ['transport' => 'log']);
        $manager = new CEmail_MailManager();
        $array = $manager->mailer('uji_array');
        $this->assertInstanceOf(CEmail_Mailer::class, $array);
        $this->assertInstanceOf(CEmail_Transport_ArrayTransport::class, $array->getSymfonyTransport());
        $this->assertSame($array, $manager->mailer('uji_array'), 'di-memoize');
        $this->assertInstanceOf(CEmail_Transport_LogTransport::class, $manager->mailer('uji_log')->getSymfonyTransport());
        $this->assertSame($manager->mailer('uji_array'), $manager->driver('uji_array'));
    }

    public function testManagerAppliesGlobalFromFromConfig() {
        CConfig::repository()->set('email.mailers.uji_from', ['transport' => 'array', 'from' => ['address' => 'global@cresenity.com', 'name' => 'Global']]);
        $manager = new CEmail_MailManager();
        $mailer = $manager->mailer('uji_from');
        $mailer->raw('x', function ($message) {
            $message->to('a@b.co');
        });
        $from = $mailer->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getFrom()[0];
        $this->assertSame('global@cresenity.com', $from->getAddress());
        $this->assertSame('Global', $from->getName());
    }

    public function testManagerUnknownMailerAndTransportThrow() {
        $manager = new CEmail_MailManager();
        try {
            $manager->mailer('tidak-ada');
            $this->fail('harus melempar');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Mailer [tidak-ada] is not defined.', $e->getMessage());
        }
        CConfig::repository()->set('email.mailers.uji_bad', ['transport' => 'alien']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported mail transport [alien].');
        $manager->mailer('uji_bad');
    }

    public function testManagerExtendPurgeAndDefaultDriver() {
        CConfig::repository()->set('email.mailers.uji_custom', ['transport' => 'uji']);
        $manager = new CEmail_MailManager();
        $custom = new CEmail_Transport_ArrayTransport();
        $manager->extend('uji', function ($config) use ($custom) {
            return $custom;
        });
        $this->assertSame($custom, $manager->mailer('uji_custom')->getSymfonyTransport());
        $first = $manager->mailer('uji_custom');
        $manager->purge('uji_custom');
        $this->assertNotSame($first, $manager->mailer('uji_custom'));
        $manager->setDefaultDriver('uji_custom');
        $this->assertSame('uji_custom', $manager->getDefaultDriver());
        $this->assertSame($manager->mailer('uji_custom'), $manager->mailer(), 'tanpa nama = default');
        $manager->forgetMailers();
        $this->assertNotSame($first, $manager->mailer('uji_custom'));
    }

    public function testFacadeMailerUsesTheDefaultManager() {
        $this->assertInstanceOf(CEmail_MailManager::class, CEmail::manager());
        $this->assertInstanceOf(CEmail_Mailer::class, CEmail::mailer());
    }
}

class UjiEmail_Recipient {
    public $email = 'taylor@laravel.com';

    public $name = 'Taylor Otwell';
}

class UjiEmail_FluentMailable extends CEmail_Mailable {
    /** @var string */
    public $publicProperty = 'nilai';

    public function build() {
    }
}
