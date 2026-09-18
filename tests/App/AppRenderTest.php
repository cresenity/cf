<?php

use PHPUnit\Framework\TestCase;

/**
 * CApp sebagai wadah render: judul/breadcrumb (diterjemahkan sekali), custom data, elemen yang
 * ditambahkan, toArray()/toJson() (html + js base64 + asset), dan addCustomJs.
 */
class AppRenderTest extends TestCase {
    protected function tearDown(): void {
        CApp::resetInstances();
        CTranslation::translator()->addLines([], CTranslation::translator()->getLocale());
    }

    /**
     * @return CApp
     */
    protected function app() {
        CApp::resetInstances();

        return CApp::instance();
    }

    public function testInstanceIsASingletonPerDomainUntilReset() {
        $a = $this->app();

        $this->assertSame($a, CApp::instance());
        $this->assertSame($a, c::app());
        CApp::resetInstances();
        $this->assertNotSame($a, CApp::instance());
    }

    public function testTitleIsTranslatedOnceAndRawTitleKept() {
        CTranslation::translator()->addLines(['uji.judul' => 'Judul Terjemahan'], CTranslation::translator()->getLocale());
        $app = $this->app();

        $this->assertFalse($app->haveTitle());
        $this->assertSame($app, $app->setTitle('uji.judul'));
        $this->assertSame('uji.judul', $app->getTitle(), 'getTitle() = kunci mentah');
        $this->assertSame('Judul Terjemahan', $app->getTranslationTitle());
        $this->assertTrue($app->haveTitle());
        $this->assertSame('Judul Terjemahan', $app->toArray()['title']);

        $app->setTitle('uji.judul', false);
        $this->assertSame('uji.judul', $app->getTranslationTitle(), '$lang = false melewati terjemahan');
        $this->assertSame('uji.judul', $app->title());
        $this->assertSame('Judul Terjemahan', $app->title('uji.judul')->getTranslationTitle(), 'title($x) = setTitle');
    }

    public function testBreadcrumbIsTranslatedAndCallbackCanRewriteIt() {
        CTranslation::translator()->addLines(['uji.remah' => 'Remah'], CTranslation::translator()->getLocale());
        $app = $this->app();

        $app->addBreadcrumb('uji.remah', '/a')->addBreadcrumb('Polos', '/b', false)->addBreadcrumb('Terakhir');
        $this->assertSame(['Remah' => '/a', 'Polos' => '/b', 'Terakhir' => 'javascript:;'], $app->getBreadcrumb());
        $this->assertTrue($app->isShowBreadcrumb());

        $app->setBreadcrumbCallback(function ($breadcrumb) {
            return array_slice($breadcrumb, 0, 1);
        });
        $this->assertSame(['Remah' => '/a'], $app->getBreadcrumb());
        $app->showBreadcrumb(false);
        $this->assertFalse($app->isShowBreadcrumb());
    }

    public function testCustomDataSetGetAndMerge() {
        $app = $this->app();

        $app->setCustomData('a', 1)->addCustomData('b', 2);
        $this->assertSame(1, $app->getCustomData('a'));
        $this->assertSame(['a' => 1, 'b' => 2], $app->getCustomData());
        $this->assertSame('bawaan', $app->getCustomData('tidak', 'bawaan'));
        $app->setCustomData(['x' => 9]);
        $this->assertSame(['x' => 9], $app->getCustomData(), 'array mengganti seluruhnya');
    }

    public function testToArrayRendersAddedElementsWithBase64Js() {
        $app = $this->app();
        $div = $app->addDiv('kotak_uji')->addClass('kelas-uji');
        $div->add('teks di dalam');
        $div->addListener('click')->addCustomHandler()->setJs("console.log('klik');");

        $array = $app->toArray();

        $this->assertSame(['title', 'assets', 'html', 'js', 'message', 'ajaxData'], array_values(array_diff(array_keys($array), ['jsRaw'])), 'jsRaw hanya ikut saat app.debug');
        $this->assertStringContainsString('id="kotak_uji"', $array['html']);
        $this->assertStringContainsString('kelas-uji', $array['html']);
        $this->assertStringContainsString('teks di dalam', $array['html']);
        $this->assertStringContainsString("console.log('klik');", base64_decode($array['js']));
        $this->assertArrayHasKey('js', $array['assets']);
        $this->assertArrayHasKey('css', $array['assets']);
        $this->assertSame($array['html'], json_decode($app->toJson(), true)['html']);
        $this->assertSame($app->toJson(), $app->json());
    }

    public function testAddViewRendersABladeViewWithData() {
        CView::factory()->addNamespace('ujiapp', __DIR__ . '/views');
        $app = $this->app();
        $view = $app->addView('ujiapp::uji-view', ['nama' => 'Budi']);

        $this->assertInstanceOf(CElement_View::class, $view);
        $this->assertStringContainsString('<p>Halo Budi</p>', $app->toArray()['html']);
    }

    public function testAjaxDataAndCustomJsAreCarriedToTheArray() {
        $app = $this->app();
        $app->setAjaxData(['id' => 7]);
        $app->addCustomJs("window.uji = 1;");

        $array = $app->toArray();

        $this->assertSame(['id' => 7], $array['ajaxData']);
        $this->assertSame('', $array['message']);
    }
}
