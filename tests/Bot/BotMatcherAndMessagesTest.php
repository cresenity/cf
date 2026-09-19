<?php
use PHPUnit\Framework\TestCase;

class UjiBot_NamedDriver extends CBot_Driver_NullDriver {
    /** @var string */
    public static $driverName = '';

    public static function getName() {
        return static::$driverName;
    }
}

/**
 * CBot bagian murni: pencocokan pola perintah dengan parameter {nama}, filter driver/penerima,
 * Command builder, pesan masuk/keluar, Question + Button, Answer.
 */
class BotMatcherAndMessagesTest extends TestCase {
    /**
     * @param string $text
     *
     * @return CBot_Message_Incoming_IncomingMessage
     */
    protected function incoming($text, $sender = 'user-1', $recipient = 'bot-1') {
        return new CBot_Message_Incoming_IncomingMessage($text, $sender, $recipient);
    }

    /**
     * @param string $name
     *
     * @return CBot_Contract_DriverInterface
     */
    protected function driver($name) {
        UjiBot_NamedDriver::$driverName = $name;

        return new UjiBot_NamedDriver(CHTTP_Request::create('/', 'POST'), []);
    }

    public function testPatternWithNamedParametersCapturesValues() {
        $matcher = new CBot_Message_Matcher();
        $message = $this->incoming('pesan 3 kopi ke Ruang A');
        $this->assertTrue($matcher->isPatternValid($message, CBot_Message_Incoming_Answer::create(''), 'pesan {jumlah} {item} ke {tempat}'));
        $matches = $matcher->getMatches();
        $this->assertSame('3', $matches['jumlah']);
        $this->assertSame('kopi', $matches['item']);
        $this->assertSame('Ruang A', $matches['tempat']);
    }

    public function testPatternMatchingIsCaseInsensitiveAndAnchored() {
        $matcher = new CBot_Message_Matcher();
        $answer = CBot_Message_Incoming_Answer::create('');
        $this->assertTrue($matcher->isPatternValid($this->incoming('HALO'), $answer, 'halo'));
        $this->assertTrue($matcher->isPatternValid($this->incoming('halo '), $answer, 'halo'), 'spasi ekor ditoleransi');
        $this->assertFalse($matcher->isPatternValid($this->incoming('halo semua'), $answer, 'halo'), 'pola diikat penuh');
        $this->assertTrue($matcher->isPatternValid($this->incoming('halo semua'), $answer, 'halo.*'), 'regex tetap boleh');
        $this->assertTrue($matcher->isPatternValid($this->incoming('a/b'), $answer, 'a/b'), 'garis miring di-escape otomatis');
        $this->assertFalse($matcher->isPatternValid($this->incoming('x'), $answer, 'ulang {n,2}'), 'pembatas kuantitas bukan nama parameter');
    }

    public function testAnswerTextIsMatchedWhenMessageTextIsNot() {
        $matcher = new CBot_Message_Matcher();
        $message = $this->incoming('bukan');
        $this->assertTrue($matcher->isPatternValid($message, CBot_Message_Incoming_Answer::create('ya')->setValue('ya'), 'ya'), 'yang dicocokkan adalah value jawaban (bukan text)');
        $this->assertFalse($matcher->isPatternValid($message, CBot_Message_Incoming_Answer::create('ya'), 'ya'), 'Answer::create() hanya mengisi text; value tetap null');
        $arrayAnswer = CBot_Message_Incoming_Answer::create('')->setValue(['a', 'b']);
        $this->assertFalse($matcher->isPatternValid($message, $arrayAnswer, 'ya'), 'jawaban array diperlakukan kosong');
    }

    public function testMatchingMiddlewareCanVetoOrForceAMatch() {
        $matcher = new CBot_Message_Matcher();
        $veto = new class() implements CBot_Contract_Middleware_MatchingInterface {
            public function matching(CBot_Message_Incoming_IncomingMessage $message, $pattern, $regexMatched) {
                return false;
            }
        };
        $force = new class() implements CBot_Contract_Middleware_MatchingInterface {
            public function matching(CBot_Message_Incoming_IncomingMessage $message, $pattern, $regexMatched) {
                return true;
            }
        };
        $answer = CBot_Message_Incoming_Answer::create('');
        $this->assertFalse($matcher->isPatternValid($this->incoming('halo'), $answer, 'halo', [$veto]), 'middleware menolak walau regex cocok');
        $this->assertTrue($matcher->isPatternValid($this->incoming('apa saja'), $answer, 'halo', [$force]), 'middleware memaksa cocok');
        $this->assertFalse($matcher->isPatternValid($this->incoming('halo'), $answer, 'halo', [$force, $veto]), 'semua middleware harus setuju');
    }

    public function testCommandDriverAndRecipientFilters() {
        $matcher = new CBot_Message_Matcher();
        $answer = CBot_Message_Incoming_Answer::create('');
        $command = new CBot_Command('halo', function () {
        });
        $this->assertTrue($matcher->isMessageMatching($this->incoming('halo'), $answer, $command, $this->driver('Web')));

        $command->driver(['Discord']);
        $this->assertFalse($matcher->isMessageMatching($this->incoming('halo'), $answer, $command, $this->driver('Web')), 'driver lain ditolak');
        $this->assertTrue($matcher->isMessageMatching($this->incoming('halo'), $answer, $command, $this->driver('Discord')));

        $command->driver(CBot_Driver_WebDriver::class);
        $this->assertSame(['CBot_Driver_Web'], $command->getDriver()->all(), 'nama kelas driver: akhiran Driver dipangkas (tanpa namespace, prefiks CF tetap)');

        $this->assertFalse($matcher->isMessageMatching($this->incoming('halo'), $answer, $command->driver(null), $this->driver('Web')), 'driver(null) = koleksi kosong, bukan reset → tidak ada driver yang lolos');

        $scoped = (new CBot_Command('halo', function () {
        }))->recipient(['bot-1']);
        $this->assertTrue($matcher->isMessageMatching($this->incoming('halo', 'u', 'bot-1'), $answer, $scoped, $this->driver('Web')));
        $this->assertFalse($matcher->isMessageMatching($this->incoming('halo', 'u', 'bot-2'), $answer, $scoped, $this->driver('Web')), 'penerima tidak terdaftar');
    }

    public function testCommandBuilderAndGroupAttributes() {
        $callback = function () {
            return 'ok';
        };
        $command = new CBot_Command('menu', $callback);
        $this->assertSame('menu', $command->getPattern());
        $this->assertSame($callback, $command->getCallback());
        $this->assertNull($command->getDriver());
        $this->assertNull($command->getRecipients());
        $this->assertFalse($command->shouldStopConversation());
        $this->assertFalse($command->shouldSkipConversation());

        $matching = new class() implements CBot_Contract_Middleware_MatchingInterface {
            public function matching(CBot_Message_Incoming_IncomingMessage $message, $pattern, $regexMatched) {
                return $regexMatched;
            }
        };
        $command->applyGroupAttributes(['middleware' => [$matching, 'bukan-middleware'], 'driver' => 'Web', 'recipient' => 'r1', 'stop_conversation' => true, 'skip_conversation' => true]);
        $this->assertSame([$matching], $command->getMiddleware(), 'hanya objek Matching/Heard yang diterima');
        $this->assertSame(['Web'], $command->getDriver()->all());
        $this->assertSame(['r1'], $command->getRecipients());
        $this->assertTrue($command->shouldStopConversation());
        $this->assertTrue($command->shouldSkipConversation());
        $array = $command->toArray();
        $this->assertSame('menu', $array['pattern']);
        $this->assertSame(['Web'], $array['driver']->all());
    }

    public function testIncomingMessageCarriesExtrasAttachmentsAndIdentifiers() {
        $message = $this->incoming('halo', 'u-7', 'bot-9');
        $this->assertSame('halo', $message->getText());
        $this->assertSame('u-7', $message->getSender());
        $this->assertSame('bot-9', $message->getRecipient());
        $this->assertSame('conversation-' . sha1('u-7') . '-' . sha1('bot-9'), $message->getConversationIdentifier());
        $this->assertSame('conversation-' . sha1('u-7') . '-' . sha1(''), $message->getOriginatedConversationIdentifier(), 'percakapan asal: penerima kosong');
        $withBot = new CBot_Message_Incoming_IncomingMessage('x', 'u-7', 'bot-9', null, 'B1');
        $this->assertSame('conversation-B1' . sha1('u-7') . '-' . sha1('bot-9'), $withBot->getConversationIdentifier(), 'bot_id disisipkan');
        $message->addExtras('lang', 'id');
        $this->assertSame('id', $message->getExtras('lang'));
        $this->assertSame(['lang' => 'id'], $message->getExtras());
        $this->assertNull($message->getExtras('tidak'));
        $this->assertFalse($message->isFromBot());
        $message->setIsFromBot(true);
        $message->setText('diubah');
        $this->assertTrue($message->isFromBot());
        $this->assertSame('diubah', $message->getText());
        $message->setLocation(new CBot_Message_Attachment_Location(-6.2, 106.8));
        $this->assertSame(-6.2, $message->getLocation()->getLatitude());
        $message->setImages([CBot_Message_Attachment_Image::url('https://img/a.png')]);
        $this->assertSame('https://img/a.png', $message->getImages()[0]->getUrl());
    }

    public function testQuestionWithButtonsSerializes() {
        $question = CBot_Message_Outgoing_Question::create('Pilih warna')
            ->fallback('Tidak bisa menampilkan tombol')
            ->callbackId('warna')
            ->addButtons([
                CBot_Message_Outgoing_Action_Button::create('Merah')->value('merah'),
                CBot_Message_Outgoing_Action_Button::create('Biru')->value('biru')->name('btn-biru')->url('https://uji.test')->image('https://img/b.png')->additionalParameters(['x' => 1]),
            ]);
        $array = $question->toArray();
        $this->assertSame('Pilih warna', $array['text']);
        $this->assertSame('Tidak bisa menampilkan tombol', $array['fallback']);
        $this->assertSame('warna', $array['callback_id']);
        $this->assertCount(2, $array['actions']);
        $this->assertSame('Merah', $array['actions'][0]['name'], 'name default = text');
        $this->assertSame('merah', $array['actions'][0]['value']);
        $this->assertSame('button', $array['actions'][0]['type']);
        $this->assertSame('btn-biru', $array['actions'][1]['name']);
        $this->assertSame('https://img/b.png', $array['actions'][1]['image_url']);
        $this->assertSame(['x' => 1], $array['actions'][1]['additional']);
        $this->assertSame($array, json_decode(json_encode($question), true));
        $this->assertCount(2, $question->getButtons());
    }

    public function testOutgoingMessageAndAnswer() {
        $outgoing = CBot_Message_Outgoing_OutgoingMessage::create('Halo')->withAttachment(CBot_Message_Attachment_Image::url('https://img/a.png'));
        $this->assertSame('Halo', $outgoing->getText());
        $this->assertInstanceOf(CBot_Message_Attachment_Image::class, $outgoing->getAttachment());
        $this->assertSame('Ganti', $outgoing->text('Ganti')->getText());

        $answer = CBot_Message_Incoming_Answer::create('ya')->setCallbackId('cb')->setInteractiveReply(true);
        $this->assertSame('ya', (string) $answer);
        $this->assertNull($answer->getValue(), 'value tidak otomatis dari text');
        $this->assertTrue($answer->isInteractiveMessageReply());
        $this->assertSame('cb', $answer->getCallbackId());
        $answer->setValue('nilai-lain');
        $this->assertSame('nilai-lain', $answer->getValue());
        $this->assertSame('ya', $answer->getText());
    }
}
