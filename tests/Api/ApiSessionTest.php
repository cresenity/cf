<?php

use PHPUnit\Framework\TestCase;

/**
 * CApi_Session + CApi_SessionFactory + driver File/Null: pembuatan id, penyimpanan berkas per
 * tanggal/jam, baca-tulis data, dan sesi yang tidak ada.
 */
class ApiSessionTest extends TestCase {
    /**
     * @var array
     */
    private $options = ['driver' => CApi::SESSION_DRIVER_FILE, 'group' => 'cfapitest'];

    protected function tearDown(): void {
        $base = DOCROOT . 'application/' . CF::appCode() . '/default/sessions/cfapitest';
        if (is_dir($base)) {
            CFile::deleteDirectory($base);
        }
    }

    public function testCreateSessionWritesAFileAndReturnsALoadedSession() {
        $session = CApi::createSession($this->options);

        $this->assertInstanceOf(CApi_Session::class, $session);
        $this->assertSame(14 + 13, strlen($session->sessionId()), 'YmdHis (14) + uniqid (13)');
        $this->assertStringStartsWith(date('Ymd'), $session->getId());
        $this->assertTrue($session->exists());
        $this->assertSame(['sessionId' => $session->sessionId()], $session->data());

        $path = $session->driver()->getFilePath($session->sessionId());
        $this->assertFileExists($path);
        $this->assertStringContainsString('/sessions/cfapitest/' . date('Ymd') . '/' . date('H') . '/', $path);
        $this->assertSame(['sessionId' => $session->sessionId()], json_decode(file_get_contents($path), true));
    }

    public function testSetAndGetPersistThroughTheDriver() {
        $session = CApi::createSession($this->options);
        $id = $session->sessionId();

        $this->assertSame($session, $session->set('user', ['id' => 7]));
        $this->assertSame(['id' => 7], $session->get('user'));
        $this->assertSame(7, $session->get('user.id'), 'get() memahami notasi titik');
        $this->assertNull($session->get('missing'));

        $reloaded = CApi::session($id, $this->options);
        $this->assertSame(['id' => 7], $reloaded->get('user'));
        $this->assertSame($id, $reloaded->get('sessionId'));
    }

    public function testSetWithoutSaveIsNotPersistedUntilSave() {
        $session = CApi::createSession($this->options);
        $id = $session->sessionId();

        $session->set('lazy', 'ya', false);
        $this->assertNull(CApi::session($id, $this->options)->get('lazy'));

        $session->save();
        $this->assertSame('ya', CApi::session($id, $this->options)->get('lazy'));
    }

    public function testSetDataReplacesEverythingAndLoadRereads() {
        $session = CApi::createSession($this->options);
        $id = $session->sessionId();

        $session->setData(['only' => 1]);
        $this->assertSame(['only' => 1], $session->data());
        $this->assertFalse($session->exists(), 'tanpa kunci sessionId, exists() false');

        $other = CApi::session($id, $this->options);
        $other->set('from', 'other');
        $this->assertNull($session->get('from'));
        $this->assertSame($session, $session->load());
        $this->assertSame('other', $session->get('from'));
    }

    public function testAnUnknownSessionIdThrows() {
        $this->expectException(CApi_Exception_SessionNotFoundException::class);
        $this->expectExceptionMessage('sessionId 20260101000000tidakada not found');

        CApi::session('20260101000000tidakada', $this->options);
    }

    public function testGetOrCreateSessionCreatesWhenMissing() {
        $id = date('YmdHis') . 'baruabc';
        $session = CApi_SessionFactory::getOrCreateSession($id, $this->options);

        $this->assertSame($id, $session->sessionId());
        $this->assertTrue($session->exists());
        $this->assertTrue($session->driver()->exists($id));

        $session->set('k', 'v');
        $this->assertSame('v', CApi_SessionFactory::getOrCreateSession($id, $this->options)->get('k'), 'yang sudah ada tidak ditimpa');
    }

    public function testFileDriverBasics() {
        $driver = CApi_SessionFactory::createDriver(CApi::SESSION_DRIVER_FILE, ['group' => 'cfapitest']);

        $this->assertInstanceOf(CApi_Session_Driver_FileDriver::class, $driver);
        $this->assertFalse($driver->exists('20260918100000xyz'));
        $this->assertSame([], $driver->read('20260918100000xyz'));

        $driver->write('20260918100000xyz', ['a' => 1]);
        $this->assertTrue($driver->exists('20260918100000xyz'));
        $this->assertSame(['a' => 1], $driver->read('20260918100000xyz'));
        $this->assertStringEndsWith('/sessions/cfapitest/20260918/10/20260918100000xyz.php', $driver->getFilePath('20260918100000xyz'));
        $this->assertTrue($driver->close());
        $this->assertTrue($driver->gc(0));
    }

    public function testNullDriverStoresNothingButAlwaysExists() {
        $driver = CApi_SessionFactory::createDriver(CApi::SESSION_DRIVER_NULL, []);

        $this->assertInstanceOf(CApi_Session_Driver_NullDriver::class, $driver);
        $this->assertTrue($driver->exists('apa-saja'));
        $this->assertTrue($driver->write('apa-saja', ['a' => 1]));
        $this->assertSame([], $driver->read('apa-saja'));

        $session = CApi::createSession(['driver' => CApi::SESSION_DRIVER_NULL]);
        $this->assertSame([], $session->data());
        $this->assertFalse($session->exists());
        $session->set('x', 1);
        $this->assertSame(1, $session->get('x'), 'di memori tetap ada');
    }

    public function testUnknownDriverNameFails() {
        $this->expectException(Error::class);
        CApi_SessionFactory::createDriver('Tidakada', []);
    }
}
