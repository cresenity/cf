<?php
use PHPUnit\Framework\TestCase;

/**
 * createExportAction() and createDownloadProgressAction() used to build their CAjax_Method
 * without setExpiration()/enableAuth(), unlike createDownloadUrl() - so the stored method
 * had expiration null and auth false, and CAjax_Method::executeEngine() skipped both checks
 * (the expiration guard is `if ($expiration && ...)`, checkAuth() returns true for a falsy
 * auth). Anyone who obtained or guessed the /cresenity/ajax/<id> URL could run the export with
 * no login and no time limit (#-13858). Both now apply the same defaults createDownloadUrl()
 * does; these tests read the stored method back the same way Controller_Cresenity::ajax() does.
 */
class DataTableExportActionAuthTest extends TestCase {
    /**
     * @param string $url
     *
     * @return CAjax_Method
     */
    protected function storedMethodFor($url) {
        $path = parse_url(html_entity_decode($url), PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', $path)));
        $methodId = end($segments);

        $json = CTemporary::disk()->get(CAjax::temporaryFile($methodId));

        return CAjax::createMethod($json)->setArgs([$methodId]);
    }

    /**
     * @return CElement_Component_DataTable
     */
    protected function table() {
        $table = new CElement_Component_DataTable('tbl_' . uniqid());
        $table->setDataFromArray([['id' => 1, 'name' => 'Merah']]);
        $table->addColumn('name')->setLabel('Nama');

        return $table;
    }

    /**
     * Action keeps its link protected with no getter, so read it back off the rendered
     * markup, the same way a browser would get hold of it.
     */
    protected function ajaxUrlIn($rendered) {
        preg_match('#cresenity/ajax/[A-Za-z0-9]+#', $rendered, $m);
        $this->assertNotEmpty($m, 'ajax url not found in rendered output: ' . $rendered);

        return $m[0];
    }

    protected function urlFromExportAction(array $options = []) {
        return $this->ajaxUrlIn($this->table()->createExportAction($options)->html());
    }

    protected function urlFromDownloadProgressAction(array $options = []) {
        $action = $this->table()->createDownloadProgressAction(array_merge(['filename' => 'export.xlsx'], $options));

        return $this->ajaxUrlIn($action->html() . $action->js());
    }

    public function testExportActionStoresAnExpirationAndAuthLikeCreateDownloadUrl() {
        $reference = $this->storedMethodFor($this->table()->createDownloadUrl([]));
        $method = $this->storedMethodFor($this->urlFromExportAction());

        $this->assertNotNull($method->getExpiration());
        $this->assertGreaterThan(CCarbon::now()->getTimestamp(), $method->getExpiration());
        $this->assertNotEmpty($method->auth);
        $this->assertSame($reference->auth, $method->auth);
    }

    public function testDownloadProgressActionStoresAnExpirationAndAuthLikeCreateDownloadUrl() {
        $reference = $this->storedMethodFor($this->table()->createDownloadUrl([]));
        $method = $this->storedMethodFor($this->urlFromDownloadProgressAction());

        $this->assertNotNull($method->getExpiration());
        $this->assertGreaterThan(CCarbon::now()->getTimestamp(), $method->getExpiration());
        $this->assertNotEmpty($method->auth);
        $this->assertSame($reference->auth, $method->auth);
    }

    public function testExplicitOptionsOverrideTheDefaults() {
        $expiration = CCarbon::now()->addHours(2)->getTimestamp();

        $method = $this->storedMethodFor($this->urlFromExportAction(['expiration' => $expiration, 'auth' => false]));
        $this->assertSame($expiration, $method->getExpiration());
        $this->assertEmpty($method->auth);

        $method = $this->storedMethodFor($this->urlFromDownloadProgressAction(['expiration' => $expiration, 'auth' => false]));
        $this->assertSame($expiration, $method->getExpiration());
        $this->assertEmpty($method->auth);
    }

    /**
     * The progress url that CAjax_Engine_DataTable_ExporterProcessor_Query hands back after
     * the click used to be a brand-new open method as well; it now inherits the expiration and
     * auth of the export method that produced it.
     */
    public function testProgressUrlInheritsExpirationAndAuthFromTheExportMethod() {
        $expiration = CCarbon::now()->addHours(3)->getTimestamp();
        // The 'null' connection (special-cased in CQueue_Manager::getConfig()) discards the
        // queued export job: the default 'database' connection needs a DB this suite doesn't
        // have, 'sync' would really run the export and drop files, and the queue side isn't
        // what's under test here - only the progress url the engine hands back.
        $method = $this->storedMethodFor($this->urlFromDownloadProgressAction([
            'expiration' => $expiration,
            'auth' => false,
            'disk' => 'local-temp',
            'queueConnection' => 'null',
        ]));

        $response = $method->executeEngine();
        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('progressUrl', carr::get($payload, 'data', []), $response->getContent());

        $progress = $this->storedMethodFor($payload['data']['progressUrl']);
        $this->assertSame('DataTableExporterProgress', $progress->getType());
        $this->assertSame($expiration, $progress->getExpiration());
        $this->assertSame($method->auth, $progress->auth);
    }

    /**
     * End to end through the same executeEngine() the controller calls: an expired export
     * link is refused before any engine runs, which the old expiration-less method never was.
     */
    public function testAnExpiredExportLinkIsRefusedBeforeTheEngineRuns() {
        $method = $this->storedMethodFor($this->urlFromExportAction([
            'expiration' => CCarbon::now()->subMinute()->getTimestamp(),
            'auth' => false,
        ]));

        $this->expectException(CAjax_Exception_ExpiredAjaxException::class);
        $method->executeEngine();
    }
}
