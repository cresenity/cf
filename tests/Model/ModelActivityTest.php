<?php

use PHPUnit\Framework\TestCase;

class ModelActivityTestListener {
    /**
     * @var array
     */
    public static $calls = [];

    /**
     * @param string $message
     * @param array  $data
     *
     * @return void
     */
    public static function populate($message, $data) {
        static::$calls[] = [$message, $data];
    }
}

/**
 * CModel_Activity sebuah singleton yang memasang pendengarnya pada dispatcher
 * global, jadi sisa satu test terbawa ke test berikutnya kalau tidak dibersihkan
 * - dan justru keadaan itu yang dulu menyembunyikan penumpukan pendengarnya.
 */
class ModelActivityTest extends TestCase {
    protected function setUp() {
        ModelActivityTestListener::$calls = [];
        CEvent::dispatcher()->forget('OnActivity');
        CModel_Activity::instance()->cancel();
    }

    protected function tearDown() {
        CEvent::dispatcher()->forget('OnActivity');
        CModel_Activity::instance()->cancel();
    }

    /**
     * Satu siklus utuh, seperti yang dilakukan CApp_Trait_LogActivity.
     *
     * @param string $message
     * @param int    $key
     *
     * @return void
     */
    protected function runCycle($message, $key = 1) {
        $activity = CModel_Activity::instance();
        $activity->setMessage($message);
        $activity->setListener([ModelActivityTestListener::class, 'populate']);
        $activity->start();
        $activity->addData('tabel_uji', $key, 'create', [], ['nama' => 'a'], []);
        $activity->stop();
    }

    public function testOneCycleCallsTheListenerOnce() {
        $this->runCycle('Siklus Satu');

        $this->assertCount(1, ModelActivityTestListener::$calls);
    }

    /**
     * Inti perbaikannya. Dulu pendengarnya menumpuk pada dispatcher global,
     * sehingga siklus kedua menulis dua kali dan ketiga tiga kali.
     *
     * @return void
     */
    public function testEachCycleStillCallsTheListenerOnlyOnce() {
        $this->runCycle('Siklus Satu', 1);
        $this->assertCount(1, ModelActivityTestListener::$calls, 'siklus pertama');

        $this->runCycle('Siklus Dua', 2);
        $this->assertCount(2, ModelActivityTestListener::$calls, 'siklus kedua menulis dua kali');

        $this->runCycle('Siklus Tiga', 3);
        $this->assertCount(3, ModelActivityTestListener::$calls, 'siklus ketiga menulis tiga kali');
    }

    public function testSettingTheListenerTwiceKeepsOnlyTheLastOne() {
        $activity = CModel_Activity::instance();
        $activity->setMessage('Dipasang Dua Kali');
        $activity->setListener([ModelActivityTestListener::class, 'populate']);
        $activity->setListener([ModelActivityTestListener::class, 'populate']);
        $activity->start();
        $activity->stop();

        $this->assertCount(1, ModelActivityTestListener::$calls);
    }

    public function testStopPassesTheMessageAndTheCollectedData() {
        $this->runCycle('Buat Sesuatu', 77);

        list($message, $data) = ModelActivityTestListener::$calls[0];
        $this->assertSame('Buat Sesuatu', $message);
        $this->assertCount(1, $data);
        $this->assertSame('tabel_uji', $data[0]['table']);
        $this->assertSame(77, $data[0]['key']);
        $this->assertSame('create', $data[0]['type']);
    }

    public function testStartDropsDataLeftFromThePreviousCycle() {
        $this->runCycle('Siklus Satu', 1);
        $this->runCycle('Siklus Dua', 2);

        list($message, $data) = ModelActivityTestListener::$calls[1];
        $this->assertSame('Siklus Dua', $message);
        $this->assertCount(1, $data, 'data siklus sebelumnya ikut terbawa');
        $this->assertSame(2, $data[0]['key']);
    }

    public function testCancelDropsEverythingWithoutDispatching() {
        $activity = CModel_Activity::instance();
        $activity->setMessage('Dibatalkan');
        $activity->setListener([ModelActivityTestListener::class, 'populate']);
        $activity->start();
        $activity->addData('tabel_uji', 1, 'create', [], [], []);
        $activity->cancel();

        $this->assertCount(0, ModelActivityTestListener::$calls);
        $this->assertFalse((bool) $activity->isStarted());
    }

    public function testIsStartedFollowsTheCycle() {
        $activity = CModel_Activity::instance();
        $this->assertFalse((bool) $activity->isStarted());

        $activity->setMessage('Berjalan');
        $activity->setListener([ModelActivityTestListener::class, 'populate']);
        $activity->start();
        $this->assertTrue($activity->isStarted());

        $activity->stop();
        $this->assertFalse((bool) $activity->isStarted());
    }
}
