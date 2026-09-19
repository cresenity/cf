<?php
use PHPUnit\Framework\TestCase;

class UjiNotif_LogNotification extends CModel {
    use CNotification_Trait_LogNotificationModelTrait;

    protected $connection = 'uji_notif';
}

/**
 * Message class ala app: execute() mengembalikan satu baris per penerima; channel yang menulis
 * log dan memanggil handler per baris.
 */
class UjiNotif_Message extends CNotification_MethodAbstract {
    public function execute() {
        $rows = [];
        foreach ((array) carr::get($this->options, 'recipients', []) as $recipient) {
            $rows[] = [
                'recipient' => $recipient,
                'subject' => carr::get($this->options, 'subject', 'Subjek'),
                'message' => 'Halo ' . $recipient,
                'refType' => 'uji',
                'refId' => 7,
                'extra' => carr::get($this->options, 'extra'),
            ];
        }

        return $rows;
    }
}

/**
 * CNotification: manager (resolusi channel bawaan/kustom, pemetaan vendor → kelas pesan),
 * CustomChannel dengan handler closure yang menulis log_notification (SQLite in-memory) —
 * status SUCCESS/FAILED, vendor_response, recipient model/koleksi, jalur send() tanpa antrean.
 */
class NotificationChannelTest extends TestCase {
    const CONNECTION = 'uji_notif';

    /** @var CDatabase_Connection */
    protected $db;

    /** @var mixed */
    protected $originalModel;

    protected function setUp(): void {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('butuh ekstensi pdo_sqlite');
        }
        $manager = CDatabase_Manager::instance();
        $manager->purge(static::CONNECTION);
        $manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], static::CONNECTION);
        $this->db = $manager->connection(static::CONNECTION);
        $this->db->statement('create table log_notification (log_notification_id integer primary key autoincrement, org_id integer null, member_id integer null, member_type varchar(50) null, message_class varchar(255) null, vendor varchar(255) null, channel varchar(255) null, notification_status varchar(50) null, is_read integer not null default 0, recipient text null, subject text null, message text null, options text null, error text null, vendor_response text null, ref_id integer null, ref_type varchar(255) null, created datetime null, createdby varchar(255) null, createdip varchar(50) null, updated datetime null, updatedby varchar(255) null, updatedip varchar(50) null, status integer not null default 1, deleted datetime null, deletedby varchar(255) null)');
        $this->originalModel = CConfig::repository()->get('notification.log_notification_model');
        CConfig::repository()->set('notification.log_notification_model', UjiNotif_LogNotification::class);
    }

    protected function tearDown(): void {
        CConfig::repository()->set('notification.log_notification_model', $this->originalModel);
        CDatabase_Manager::instance()->purge(static::CONNECTION);
    }

    /**
     * @return array[]
     */
    protected function logs() {
        return array_map(function ($r) {
            return (array) $r;
        }, $this->db->table('log_notification')->orderBy('log_notification_id')->get()->all());
    }

    // ---- manager ----

    public function testBuiltInChannelsAreResolvedByNameCaseInsensitively() {
        $manager = CNotification::manager();
        $this->assertInstanceOf(CNotification_Channel_EmailChannel::class, $manager->channel('Email'));
        $this->assertInstanceOf(CNotification_Channel_SmsChannel::class, $manager->channel('sms'));
        $this->assertInstanceOf(CNotification_Channel_WhatsappChannel::class, $manager->channel('Whatsapp'));
        $this->assertInstanceOf(CNotification_Channel_DatabaseChannel::class, $manager->channel('database'));
        $this->assertInstanceOf(CNotification_Channel_PushNotificationChannel::class, $manager->channel('PushNotification'));
        $this->assertSame($manager->channel('Email'), $manager->channel('Email'), 'di-memoize per nama');
    }

    public function testUnknownChannelThrows() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Channel Telegram is not available');
        CNotification::manager()->channel('Telegram');
    }

    public function testRegisterAndCustomChannel() {
        $manager = CNotification::manager();
        $custom = $manager->createCustomChannel('Uji', function () {
        });
        $this->assertInstanceOf(CNotification_Channel_CustomChannel::class, $custom);
        $this->assertSame($custom, $manager->channel('Uji'));
        $sms = new CNotification_Channel_SmsChannel(['vendor' => 'zenziva']);
        $this->assertSame($sms, $manager->registerChannel('SmsUji', $sms));
        $this->assertSame($sms, $manager->channel('SmsUji'));
        $this->assertSame('zenziva', $sms->getVendorName(), 'vendor dari config channel');
    }

    public function testCreateMessageMapsVendorsToMessageClasses() {
        $manager = CNotification::manager();
        $this->assertInstanceOf(CNotification_Message_SendGrid::class, $manager->createMessage('sendgrid', ['api_key' => 'x'], ['to' => 'a@b.co']));
        $this->assertInstanceOf(CNotification_Message_Zenziva::class, $manager->createMessage('zenziva'));
        $this->assertInstanceOf(CNotification_Message_Nexmo::class, $manager->createMessage('nexmo'));
        $this->assertInstanceOf(CNotification_Message_Onesignal::class, $manager->createMessage('onesignal'));
        $this->assertInstanceOf(CNotification_Message_Watzap::class, $manager->createMessage('watzap'));
        $this->assertInstanceOf(CNotification_Message_Slack::class, $manager->createMessage('slack'));
    }

    public function testLogNotificationModelComesFromConfig() {
        $manager = CNotification::manager();
        $this->assertSame(UjiNotif_LogNotification::class, $manager->logNotificationModelName());
        $this->assertInstanceOf(UjiNotif_LogNotification::class, $manager->createLogNotificationModel());
        $model = $manager->createLogNotificationModel();
        $this->assertSame('log_notification', $model->getTable());
        $this->assertSame('log_notification_id', $model->getKeyName());
    }

    public function testFacadeShortcuts() {
        $this->assertInstanceOf(CNotification_Manager::class, CNotification::manager());
        $this->assertInstanceOf(CNotification_Channel_EmailChannel::class, CNotification::email());
        $this->assertInstanceOf(CNotification_Channel_SmsChannel::class, CNotification::sms());
        $this->assertInstanceOf(CNotification_Channel_WhatsappChannel::class, CNotification::whatsapp());
        $this->assertInstanceOf(CNotification_Channel_PushNotificationChannel::class, CNotification::pushNotification());
    }

    // ---- channel: kirim tanpa antrean ----

    public function testSendWithoutQueueLogsOneRowPerRecipientAndCallsTheHandler() {
        $handled = [];
        $channel = CNotification::manager()->createCustomChannel('UjiSukses', function ($data, $log) use (&$handled) {
            $handled[] = $data['recipient'];

            return ['status' => 'ok', 'to' => $data['recipient']];
        });
        $result = $channel->sendWithoutQueue(UjiNotif_Message::class, ['recipients' => ['0811', '0822'], 'subject' => 'Tes', 'extra' => ['k' => 'v']]);
        $this->assertInstanceOf(CCollection::class, $result);
        $this->assertCount(2, $result);
        $this->assertSame(['0811', '0822'], $handled);

        $logs = $this->logs();
        $this->assertCount(2, $logs);
        $this->assertSame('SUCCESS', $logs[0]['notification_status']);
        $this->assertSame('UjiSukses', $logs[0]['channel']);
        $this->assertSame(UjiNotif_Message::class, $logs[0]['message_class']);
        $this->assertSame('0811', $logs[0]['recipient']);
        $this->assertSame('Tes', $logs[0]['subject']);
        $this->assertSame('Halo 0811', $logs[0]['message']);
        $this->assertSame('uji', $logs[0]['ref_type']);
        $this->assertEquals(7, $logs[0]['ref_id']);
        $this->assertSame(['status' => 'ok', 'to' => '0811'], json_decode($logs[0]['vendor_response'], true), 'respons vendor disimpan sebagai JSON');
        $this->assertSame(['extra' => ['k' => 'v']], json_decode($logs[0]['options'], true), 'sisa opsi selain kolom baku masuk options');
        $this->assertNull($logs[0]['error']);
        $this->assertEquals(0, $logs[0]['is_read']);
    }

    public function testHandlerExceptionMarksTheRowFailedAndContinues() {
        $channel = CNotification::manager()->createCustomChannel('UjiGagal', function ($data) {
            if ($data['recipient'] === 'rusak') {
                throw new RuntimeException('vendor menolak');
            }

            return 'ok';
        });
        $channel->sendWithoutQueue(UjiNotif_Message::class, ['recipients' => ['baik', 'rusak', 'baik2']]);
        $logs = $this->logs();
        $this->assertSame(['SUCCESS', 'FAILED', 'SUCCESS'], array_column($logs, 'notification_status'), 'satu gagal tidak menghentikan yang lain');
        $this->assertStringStartsWith('[RuntimeException] vendor menolak', $logs[1]['error']);
        $this->assertSame('ok', $logs[0]['vendor_response'], 'respons string disimpan apa adanya');
    }

    public function testRecipientModelAndCollectionAreStoredAsKeys() {
        $channel = CNotification::manager()->createCustomChannel('UjiModel', function () {
            return 'ok';
        });
        $a = new UjiNotif_LogNotification();
        $a->save();
        $b = new UjiNotif_LogNotification();
        $b->save();
        $channel->handleResult(new UjiNotif_Message(), [
            ['recipient' => $a, 'subject' => 's', 'message' => 'm'],
            ['recipient' => new CModel_Collection([$a, $b]), 'subject' => 's', 'message' => 'm'],
            ['recipient' => new CModel_Collection([]), 'subject' => 's', 'message' => 'm'],
        ]);
        $logs = array_slice($this->logs(), 2);
        $this->assertSame([$a->getKey()], json_decode($logs[0]['recipient'], true), 'model tunggal → [id]');
        $this->assertSame([$a->getKey(), $b->getKey()], json_decode($logs[1]['recipient'], true), 'koleksi → daftar id');
        $this->assertSame([], json_decode($logs[2]['recipient'], true), 'koleksi kosong → []');
    }

    public function testMessageHandlerClosureIsWrappedAsSerializableClosure() {
        $channel = new CNotification_Channel_CustomChannel(['channel' => 'X']);
        $this->assertSame($channel, $channel->setMessageHandler(function () {
            return 'dari-closure';
        }));
        $channel->handleResult(new UjiNotif_Message(), [['recipient' => 'r', 'subject' => 's', 'message' => 'm']]);
        $this->assertSame('dari-closure', $this->logs()[0]['vendor_response']);
    }

    public function testSendWithoutQueueConfigDispatchesNowThroughTheSenderJob() {
        $channel = CNotification::manager()->createCustomChannel('UjiLangsung', function () {
            return 'terkirim';
        });
        $channel->send(UjiNotif_Message::class, ['recipients' => ['x']], ['queue' => ['queued' => false]]);
        $logs = $this->logs();
        $this->assertCount(1, $logs);
        $this->assertSame('SUCCESS', $logs[0]['notification_status']);
        $this->assertSame('terkirim', $logs[0]['vendor_response']);
    }

    public function testGetChannelConfigFallsBackToTheGlobalKey() {
        $channel = new CNotification_Channel_CustomChannel(['channel' => 'PushNotification']);
        $original = CConfig::repository()->get('notification');
        CConfig::repository()->set('notification.queue.queued', false);
        CConfig::repository()->set('notification.push_notification.queue.queued', true);
        try {
            $this->assertTrue($channel->getChannelConfig('queue.queued'), 'kunci per channel (snake_case) menang');
            $this->assertFalse((new CNotification_Channel_CustomChannel(['channel' => 'Sms']))->getChannelConfig('queue.queued'), 'channel lain jatuh ke kunci global');
        } finally {
            CConfig::repository()->set('notification', $original);
        }
    }
}
