<?php
use PHPUnit\Framework\TestCase;

/**
 * CEmail::fake() + CEmail_Testing_MailFake: pencatatan kirim/antre, assertion, jalur lama, dan reset.
 */
class MailFakeWelcomeMailable extends CEmail_Mailable {
    public function envelope() {
        return (new CEmail_Mailable_Envelope())->subject('Selamat datang');
    }
}

class MailFakeOtherMailable extends CEmail_Mailable {
}

class MailFakeQueuedMailable extends CEmail_Mailable implements CQueue_ShouldQueueInterface {
}

class MailFakeTest extends TestCase {
    /** @var mixed */
    protected $originalSwitch;

    protected function setUp(): void {
        $this->originalSwitch = CConfig::repository()->get('email.legacy_sender_via_mailer');
    }

    protected function tearDown(): void {
        CEmail::forgetFake();
        CConfig::repository()->set('email.legacy_sender_via_mailer', $this->originalSwitch);
        CEmail_Sender_MailerDriver::forgetMailers();
    }

    /**
     * Pesan kegagalan assertion, atau null bila assertion lulus.
     *
     * @param callable $assertion
     *
     * @return null|string
     */
    protected function failureOf($assertion) {
        try {
            $assertion();
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function testFakeReplacesTheManagerAndForgetRestoresIt() {
        $this->assertFalse(CEmail::hasFake());
        $this->assertInstanceOf(CEmail_MailManager::class, CEmail::manager());

        $fake = CEmail::fake();

        $this->assertTrue(CEmail::hasFake());
        $this->assertSame($fake, CEmail::manager());
        $this->assertSame($fake, CEmail::mailer());
        $this->assertSame($fake, CEmail::mailer('apa-saja'));

        CEmail::forgetFake();

        $this->assertFalse(CEmail::hasFake());
        $this->assertInstanceOf(CEmail_MailManager::class, CEmail::manager());
    }

    public function testSendRecordsMailableWithoutDelivering() {
        $fake = CEmail::fake();
        CEmail::to('a@x.test')->send(new MailFakeWelcomeMailable());

        $this->assertCount(1, $fake->sent(MailFakeWelcomeMailable::class));
        CEmail::assertSent(MailFakeWelcomeMailable::class);
        CEmail::assertSent(MailFakeWelcomeMailable::class, 1);
        CEmail::assertSentCount(1);
        CEmail::assertNotSent(MailFakeOtherMailable::class);
        CEmail::assertNothingQueued();
        CEmail::assertOutgoingCount(1);
    }

    public function testAssertSentWithClosureFiltersOnMailableState() {
        CEmail::fake();
        CEmail::to('a@x.test')->send(new MailFakeWelcomeMailable());

        CEmail::assertSent(function (MailFakeWelcomeMailable $mailable) {
            return $mailable->hasTo('a@x.test') && $mailable->hasSubject('Selamat datang');
        });
        CEmail::assertNotSent(function (MailFakeWelcomeMailable $mailable) {
            return $mailable->hasTo('lain@x.test');
        });
        CEmail::assertSent(MailFakeWelcomeMailable::class, function ($mailable) {
            return $mailable->hasTo('a@x.test');
        });
    }

    public function testAssertSentFailuresHaveClearMessages() {
        CEmail::fake();

        $message = $this->failureOf(function () {
            CEmail::assertSent(MailFakeWelcomeMailable::class);
        });
        $this->assertStringContainsString('The expected [MailFakeWelcomeMailable] mailable was not sent.', $message);

        CEmail::to('a@x.test')->send(new MailFakeWelcomeMailable());
        $message = $this->failureOf(function () {
            CEmail::assertSent(MailFakeWelcomeMailable::class, 3);
        });
        $this->assertStringContainsString('was sent 1 times instead of 3 times', $message);

        $message = $this->failureOf(function () {
            CEmail::assertNotSent(MailFakeWelcomeMailable::class);
        });
        $this->assertStringContainsString('The unexpected [MailFakeWelcomeMailable] mailable was sent.', $message);

        $message = $this->failureOf(function () {
            CEmail::assertNothingSent();
        });
        $this->assertStringContainsString('MailFakeWelcomeMailable', $message);

        $this->assertNotNull($this->failureOf(function () {
            CEmail::assertSentCount(2);
        }));
        $this->assertNotNull($this->failureOf(function () {
            CEmail::assertNothingOutgoing();
        }));
    }

    public function testShouldQueueMailableAndExplicitQueueAreRecordedAsQueued() {
        $fake = CEmail::fake();
        CEmail::to('a@x.test')->send(new MailFakeQueuedMailable());
        CEmail::to('b@x.test')->queue(new MailFakeWelcomeMailable());
        CEmail::to('c@x.test')->later(60, new MailFakeOtherMailable());

        CEmail::assertQueued(MailFakeQueuedMailable::class);
        CEmail::assertQueued(MailFakeWelcomeMailable::class);
        CEmail::assertQueued(MailFakeOtherMailable::class, 1);
        CEmail::assertQueuedCount(3);
        CEmail::assertNothingSent();
        CEmail::assertNotQueued(function (MailFakeWelcomeMailable $mailable) {
            return $mailable->hasTo('lain@x.test');
        });
        $this->assertTrue($fake->hasQueued(MailFakeQueuedMailable::class));
        $this->assertFalse($fake->hasSent(MailFakeQueuedMailable::class));

        $message = $this->failureOf(function () {
            CEmail::assertSent(MailFakeQueuedMailable::class);
        });
        $this->assertStringContainsString('Did you mean to use assertQueued() instead?', $message);
    }

    public function testAssertQueuedFailuresHaveClearMessages() {
        CEmail::fake();
        CEmail::to('a@x.test')->send(new MailFakeWelcomeMailable());

        $message = $this->failureOf(function () {
            CEmail::assertQueued(MailFakeWelcomeMailable::class);
        });
        $this->assertStringContainsString('was not queued.', $message);
        $this->assertStringContainsString('Did you mean to use assertSent() instead?', $message);
        $this->assertNotNull($this->failureOf(function () {
            CEmail::assertQueuedCount(1);
        }));
    }

    public function testNamedMailerIsRecordedOnTheMailable() {
        $fake = CEmail::fake();
        CEmail::mailer('brevo')->to('a@x.test')->send(new MailFakeWelcomeMailable());

        $this->assertSame('brevo', $fake->sent(MailFakeWelcomeMailable::class)->first()->mailer);
    }

    public function testLegacySenderIsCapturedWhenTheSwitchIsOn() {
        CConfig::repository()->set('email.legacy_sender_via_mailer', true);
        CEmail_Sender_MailerDriver::forgetMailers();
        $fake = CEmail::fake();

        $result = CEmail::sender(['driver' => 'null', 'from' => 'kirim@x.test'])->send(
            ['a@x.test', 'b@x.test'],
            'Verifikasi',
            '<p>Halo</p>',
            ['cc' => 'cc@x.test']
        );

        $this->assertNotFalse($result);
        CEmail::assertLegacySent();
        CEmail::assertLegacySent(1);
        CEmail::assertLegacySent(function (CEmail_Mailable_LegacyMailable $mailable) {
            return $mailable->hasTo('a@x.test') && $mailable->hasTo('b@x.test') && $mailable->hasCc('cc@x.test')
                && $mailable->hasSubject('Verifikasi') && $mailable->hasFrom('kirim@x.test')
                && $mailable->getBody() === '<p>Halo</p>' && $mailable->getBodyType() === 'html';
        });
        CEmail::assertNothingSent();
        CEmail::assertOutgoingCount(1);
        $this->assertNotNull($this->failureOf(function () {
            CEmail::assertLegacySent(function ($mailable) {
                return $mailable->hasTo('lain@x.test');
            });
        }));
        $this->assertCount(1, $fake->legacySent());
    }

    public function testPlainTextLegacyTypeIsRecordedAsRaw() {
        CConfig::repository()->set('email.legacy_sender_via_mailer', true);
        CEmail_Sender_MailerDriver::forgetMailers();
        $fake = CEmail::fake();

        CEmail::sender(['driver' => 'null', 'from' => 'kirim@x.test'])->send('a@x.test', 'Teks', 'isi teks', ['type' => 'plain']);

        $legacy = $fake->legacySent()->first();
        $this->assertSame('raw', $legacy->getBodyType());
        $this->assertSame('isi teks', $legacy->getBody());
        $this->assertFalse($legacy->hasAttachments());
    }

    public function testLegacySenderIsNotCapturedWhenTheSwitchIsOffAndMessageExplainsWhy() {
        CConfig::repository()->set('email.legacy_sender_via_mailer', false);
        CEmail_Sender_MailerDriver::forgetMailers();
        CEmail::fake();

        $message = $this->failureOf(function () {
            CEmail::assertLegacySent();
        });

        $this->assertStringContainsString('The expected legacy message was not sent.', $message);
        $this->assertStringContainsString('email.legacy_sender_via_mailer', $message);
    }

    public function testForgetFakeDropsCachedMailersSoLaterSendersAreReal() {
        CConfig::repository()->set('email.legacy_sender_via_mailer', true);
        CEmail_Sender_MailerDriver::forgetMailers();
        CEmail::fake();
        CEmail::sender(['driver' => 'null'])->getDriver()->getMailer();

        CEmail::forgetFake();

        $this->assertInstanceOf(CEmail_Mailer::class, CEmail::sender(['driver' => 'null'])->getDriver()->getMailer());
    }

    public function testAssertionsWithoutFakeAreRejectedClearly() {
        $this->assertFalse(CEmail::hasFake());
        $message = $this->failureOf(function () {
            CEmail::assertNothingSent();
        });

        $this->assertStringContainsString('CEmail::fake()', $message);
    }

    public function testFakeFallsBackToLegacyRecordForViewArrays() {
        $fake = CEmail::fake();
        CEmail::mailer()->send(['html' => new CBase_HtmlString('<b>x</b>')], [], function ($message) {
            $message->to('a@x.test')->subject('View');
        });

        $this->assertCount(1, $fake->legacySent());
        $this->assertTrue($fake->legacySent()->first()->hasSubject('View'));
    }
}
