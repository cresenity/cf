<?php

use PHPUnit\Framework\TestCase;

class UjiAjaxElement_Resolver {
    public static function kota($provinsi) {
        return [['key' => $provinsi . '-1', 'value' => 'Kota 1 ' . $provinsi]];
    }
}

/**
 * Elemen yang menghasilkan URL ajax saat dirender, lalu URL itu dieksekusi kembali seperti oleh
 * controller cresenity/ajax: listener handler (reload/dialog/ajax), Select dependsOn, TreeView,
 * Calendar, dan housekeeping berkas temp ajax per app.
 */
class AjaxElementRoundTripTest extends TestCase {
    /**
     * @var string[]
     */
    protected $created = [];

    protected function setUp(): void {
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void {
        $disk = CTemporary::disk();
        foreach ($this->created as $file) {
            if ($disk->exists($file)) {
                $disk->delete($file);
            }
        }
        $_GET = [];
        $_POST = [];
    }

    /**
     * @param string $js
     *
     * @return array [id, CAjax_Method]
     */
    protected function methodFromJs($js) {
        $this->assertSame(1, preg_match("#cresenity/ajax/([0-9a-f]{40})#", $js, $m), 'url ajax tidak ditemukan di js: ' . substr($js, 0, 300));
        $id = $m[1];
        $file = CAjax::temporaryFile($id);
        $this->created[] = $file;
        $this->assertTrue(CTemporary::disk()->exists($file), 'makeUrl() menulis berkas method');

        return [$id, CAjax::createMethod(CTemporary::disk()->get($file))->setArgs([$id])];
    }

    /**
     * @param mixed $payload
     *
     * @return array
     */
    protected function payloadArray($payload) {
        if ($payload instanceof Symfony\Component\HttpFoundation\Response) {
            $payload = $payload->getContent();
        }

        return is_string($payload) ? json_decode($payload, true) : $payload;
    }

    public function testReloadHandlerWithContentStoresTheRenderedContentAsJson() {
        $div = CElement_Factory::createElement('div', 'uji_' . uniqid());
        $handler = $div->addListener('click')->addReloadHandler()->setTarget('target');
        $handler->content()->add('isi dimuat ulang');

        $js = $div->js();
        $this->assertStringContainsString('cresenity.reload(', $js);
        $this->assertStringContainsString("selector:'#target'", $js, 'setTarget() menerima id tanpa #');
        list($id, $method) = $this->methodFromJs($js);

        $this->assertSame('AjaxHandler', $method->getType());
        $this->assertIsString($method->getData()['json'], 'konten disimpan sebagai string JSON hasil CRenderable::json()');
        $payload = $this->payloadArray($method->executeEngine());
        $this->assertSame(['html', 'js', 'js_require', 'css_require'], array_keys($payload));
        $this->assertStringContainsString('isi dimuat ulang', $payload['html']);
        $this->assertSame('', trim(base64_decode($payload['js'])), 'js dikirim base64');
    }

    public function testReloadHandlerWithAnExplicitUrlDoesNotCreateAnAjaxMethod() {
        $div = CElement_Factory::createElement('div', 'uji_' . uniqid());
        $div->addListener('click')->addReloadHandler()->setTarget('t')->setUrl('/halaman/khusus');

        $js = $div->js();

        $this->assertStringContainsString("url:'/halaman/khusus'", $js);
        $this->assertStringNotContainsString('cresenity/ajax/', $js);
    }

    public function testReloadHandlerCallbackReceivesTheAppAndItsResultIsReturned() {
        $div = CElement_Factory::createElement('div', 'uji_' . uniqid());
        $div->addListener('click')->addReloadHandler()->setTarget('t')->setCallback(function ($app) {
            $app->addDiv()->add('dari callback ' . get_class($app));

            return $app->toArray();
        });

        list($id, $method) = $this->methodFromJs($div->js());
        $payload = $this->payloadArray($method->executeEngine());

        $this->assertStringContainsString('dari callback CApp', $payload['html'], 'setCallback() pada handler sekarang bertahan lewat berkas method (closure diserialisasi)');
    }

    public function testDialogHandlerJsCarriesTitleAndAjaxUrl() {
        $button = CElement_Factory::createElement('div', 'btn_' . uniqid());
        $handler = $button->addListener('click')->addDialogHandler()->setTitle('Judul Dialog');
        $handler->content()->add('isi dialog');

        $js = $button->js();
        $this->assertStringContainsString("title:'Judul Dialog'", $js);
        list($id, $method) = $this->methodFromJs($js);

        $this->assertStringContainsString('isi dialog', $this->payloadArray($method->executeEngine())['html']);
    }

    public function testSelectDependsOnRendersAPostAjaxMethodThatResolvesOptions() {
        $select = new CElement_FormInput_Select('kota_' . uniqid());
        $select->setDependsOn('#provinsi', [UjiAjaxElement_Resolver::class, 'kota']);

        $js = $select->js();
        $this->assertStringContainsString("method:'post'", $js);
        list($id, $method) = $this->methodFromJs($js);
        $this->assertSame('DependsOn', $method->getType());
        $this->assertSame('post', $method->getMethod());
        $this->assertSame(CElement_FormInput_Select::class, $method->getData()['from']);

        $payload = json_decode($method->executeEngine(['value' => 'JB'])->getContent(), true);
        $this->assertSame([['key' => 'JB-1', 'value' => 'Kota 1 JB']], $payload['data']);
    }

    public function testTreeViewAjaxReturnsTheChildrenTheCallbackAdds() {
        $tree = new CElement_Component_TreeView('tree_' . uniqid());
        $tree->setNodes(function ($parentId, CElement_Component_TreeView_Node $node) {
            $node->addChild('anak dari ' . ($parentId ?: 'root'));
            $node->addChild(['text' => 'punya anak', 'hasChildren' => true]);
        });
        $this->assertTrue($tree->isAjax());

        $url = $tree->createAjaxUrl();
        list($id, $method) = $this->methodFromJs($url);
        $this->assertSame('get', $method->getMethod(), 'jstree memanggil dengan GET');

        $payload = json_decode($method->executeEngine(['id' => 'n7'])->getContent(), true);
        $this->assertSame('anak dari n7', $payload[0]['text']);
        $this->assertFalse($payload[0]['children']);
        $this->assertTrue($payload[1]['children']);
    }

    public function testCalendarAjaxReturnsTheEventsPushedForTheRange() {
        $calendar = new CElement_Component_Calendar('cal_' . uniqid());
        $calendar->setEvents(function ($start, $end, CElement_Component_Calendar_CalendarEvents $events) {
            $events->addData(['title' => 'Rapat', 'start' => $start, 'end' => $end]);
        });

        list($id, $method) = $this->methodFromJs($calendar->createAjaxUrl());
        $this->assertSame('post', $method->getMethod());

        $payload = json_decode($method->executeEngine(['start' => '2026-01-01', 'end' => '2026-01-31'])->getContent(), true);
        $this->assertCount(1, $payload);
        $this->assertSame('Rapat', $payload[0]['title']);
        $this->assertSame('2026-01-01', $payload[0]['start']);
    }

    public function testAjaxFileTempHousekeepingPrunesOldDayFoldersInBothLayouts() {
        $disk = CTemporary::disk();
        $old = date('Ymd', strtotime('-200 days'));
        $today = date('Ymd');
        $files = [
            'ajax/' . $old . '/a/b/c/d/e/' . $old . 'lama.tmp',
            'ajax/' . $today . '/a/b/c/d/e/' . $today . 'baru.tmp',
            'ajax/' . CF::appCode() . '/' . $old . '/a/b/c/d/e/' . $old . 'lama-app.tmp',
            'ajax/' . CF::appCode() . '/' . $today . '/a/b/c/d/e/' . $today . 'baru-app.tmp',
            'ajax/demo_dl_/bukan-tanggal.tmp',
            'ajax/' . CF::appCode() . '/12345678/bukan-tanggal.tmp',
        ];
        foreach ($files as $file) {
            $disk->put($file, '{}');
            $this->created[] = $file;
        }

        $first = CHouseKeeping_FileTemp_AjaxFileTemp::execute(90);
        $second = CHouseKeeping_FileTemp_AjaxFileTemp::execute(90);

        $this->assertTrue($first);
        $this->assertTrue($second, 'satu folder per panggilan: folder lama kedua dihapus di panggilan berikutnya');
        $this->assertFalse($disk->exists($files[0]));
        $this->assertFalse($disk->exists($files[2]));
        $this->assertTrue($disk->exists($files[1]));
        $this->assertTrue($disk->exists($files[3]));
        $this->assertTrue($disk->exists($files[4]), 'folder 8 huruf yang bukan tanggal dilewati, bukan bikin housekeeping mati');
        $this->assertTrue($disk->exists($files[5]), 'delapan digit yang bukan tanggal valid dilewati');
        $disk->deleteDirectory('ajax/demo_dl_');
        $disk->deleteDirectory('ajax/' . CF::appCode() . '/12345678');
    }
}
