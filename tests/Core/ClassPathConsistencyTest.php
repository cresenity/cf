<?php
use PHPUnit\Framework\TestCase;

/**
 * Kelas yang nama deklarasinya pernah tidak cocok dengan jalur berkasnya (sehingga tidak
 * bisa di-autoload) kini harus bisa dimuat lewat CF::autoLoad tanpa require manual.
 */
class ClassPathConsistencyTest extends TestCase {
    /**
     * @return array
     */
    public function autoloadableClassProvider() {
        return [
            ['CDatabase_Query_Grammar_MariaDbGrammar'],
            ['CApp_Exception_DriverNotFoundException'],
            ['CQueue_Contract_ShouldBeUniqueUntilProcessingInterface'],
            ['CModel_HasSlug_Exception_InvalidOptionException'],
            ['CModel_HasTranslation_Exception_AttributeIsNotTranslatable'],
            ['CServer_Browsershot_Exception_UnsuccessfulResponse'],
            ['CServer_Browsershot_Exception_ElementNotFound'],
            ['CPeriod_Exception_InvalidDateTimeClass'],
            ['CNavigation_Renderer_BootstrapRenderer'],
            ['CAnalytics_Google_GA4_Client'],
            ['CVendor_Firebase_JWT_Keys_ExpiringKeys'],
            ['CBackup_Monitor_HealthCheckFailure'],
            ['CResources_Exception_ResourceCannotBeUpdated'],
            ['CResources_Exception_ResourceCannotBeDeleted'],
            ['CRunner_FFMpeg_Exporter_PlaylistGeneratorInterface'],
            ['CRunner_FFMpeg_Exporter_Trait_EncryptsHLSSegmentsTrait'],
            ['CApi_Session_DriverInterface'],
            ['CApi_Session_Driver_RedisDriver'],
            ['CDevSuite_Ssh'],
            ['CVendor_SendGrid_Helper_Assert'],
            ['CDatabase_Contract_ConnectionInterface'],
            ['CWebhook_Server_Event_WebhookCallSucceededEvent'],
            ['CWebhook_Server_Event_WebhookCallFailedEvent'],
            ['CWebhook_Server_Event_FinalWebhookCallFailedEvent'],
            ['CVendor_Dropbox_UploadSessionCursor'],
            ['CVendor_Dropbox_Files'],
            ['CJavascript_PhpJs_JsPrinter'],
            ['CRunner_FFMpeg_Exporter_HLSPlaylistGenerator'],
            ['CRunner_FFMpeg_Exporter_HLSExporter'],
            ['CXMPP_BOSH'],
            ['CPDF'],
            ['CModel_MongoDB_Relation_HasOne'],
            ['CModel_MongoDB_Relation_HasMany'],
        ];
    }

    /**
     * @dataProvider autoloadableClassProvider
     *
     * @param string $class
     */
    public function testClassIsAutoloadable($class) {
        $this->assertTrue(
            class_exists($class) || interface_exists($class) || trait_exists($class),
            $class . ' harus bisa dimuat lewat autoload'
        );
    }

    public function testFlysystemAdaptersImplementTheVendoredInterface() {
        foreach ([\League\Flysystem\Ftp\FtpAdapter::class, \League\Flysystem\PhpseclibV3\SftpAdapter::class, \League\Flysystem\PhpseclibV2\SftpAdapter::class, \League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter::class] as $adapter) {
            $this->assertTrue(class_exists($adapter), $adapter . ' harus bisa dimuat (tanda tangan cocok dengan FilesystemAdapter)');
        }
        $this->assertTrue(class_exists(\League\Flysystem\MountManager::class));
    }

    public function testHtmlPageCrawlerFiltersWithoutTheHtml5Library() {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('Crawler yang di-vendor memakai sintaks PHP 8');
        }
        $crawler = new CParser_Dom_HtmlPageCrawler('<div><p class="a">halo</p></div>');
        $this->assertSame('halo', $crawler->filter('p.a')->text());
    }

    public function testSwappedResourceExceptionsCarryTheirOwnFactory() {
        $this->assertTrue(method_exists(CResources_Exception_ResourceCannotBeUpdated::class, 'doesNotBelongToCollection'));
        $this->assertTrue(method_exists(CResources_Exception_ResourceCannotBeDeleted::class, 'doesNotBelongToModel'));
    }

    public function testMariaDbConnectionUsesItsGrammar() {
        $connection = new CDatabase_Connection_Pdo_MariaDbConnection(new PDO('sqlite::memory:'));
        $this->assertInstanceOf(CDatabase_Query_Grammar_MariaDbGrammar::class, $connection->getQueryGrammar());
    }

    public function testWebhookCallEventsExposeCallData() {
        $event = new CWebhook_Server_Event_WebhookCallFailedEvent('post', 'https://uji.test/hook', ['a' => 1], ['X-H' => 'v'], ['m' => 1], ['tag'], 2, null, 'timeout', 'Gagal', 'uuid-1');
        $this->assertInstanceOf(CWebhook_Server_Event_WebhookCallEvent::class, $event);
        $this->assertSame('post', $event->httpVerb);
        $this->assertSame('https://uji.test/hook', $event->webhookUrl);
        $this->assertSame(['a' => 1], $event->payload);
        $this->assertSame(2, $event->attempt);
        $this->assertSame('timeout', $event->errorType);
        $this->assertSame('Gagal', $event->errorMessage);
        $this->assertSame('uuid-1', $event->uuid);
        $this->assertNull($event->transferStats, 'transferStats opsional');
        $this->assertInstanceOf(CWebhook_Server_Event_WebhookCallEvent::class, new CWebhook_Server_Event_WebhookCallSucceededEvent('post', 'u', [], [], [], [], 1, null, null, null, 'x'));
        $this->assertInstanceOf(CWebhook_Server_Event_WebhookCallEvent::class, new CWebhook_Server_Event_FinalWebhookCallFailedEvent('post', 'u', [], [], [], [], 1, null, null, null, 'x'));
    }
}
