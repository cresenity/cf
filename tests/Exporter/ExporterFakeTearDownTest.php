<?php
/**
 * CTesting_TestCase::tearDown() harus membuang CExporter::fake() agar tidak bocor ke test berikutnya.
 */
class ExporterFakeTearDownTest extends CTesting_TestCase {
    public function testFirstTestActivatesTheFake() {
        CExporter::fake();

        $this->assertTrue(CExporter::hasFake());
    }

    public function testFakeFromThePreviousTestIsGone() {
        $this->assertFalse(CExporter::hasFake());
    }
}
