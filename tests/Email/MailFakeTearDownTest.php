<?php
/**
 * CTesting_TestCase::tearDown() harus membuang CEmail::fake() agar tidak bocor ke test berikutnya.
 */
class MailFakeTearDownTest extends CTesting_TestCase {
    public function testFirstTestActivatesTheFake() {
        CEmail::fake();

        $this->assertTrue(CEmail::hasFake());
    }

    public function testFakeFromThePreviousTestIsGone() {
        $this->assertFalse(CEmail::hasFake());
        $this->assertInstanceOf(CEmail_MailManager::class, CEmail::manager());
    }
}
