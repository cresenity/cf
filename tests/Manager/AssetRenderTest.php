<?php

use PHPUnit\Framework\TestCase;

/**
 * CManager_Asset::render(): tag yang dihasilkan per posisi/tipe untuk js/css file, inline, plain,
 * urutan modul → runtime, penyaringan tipe, dan wrapJs().
 */
class AssetRenderTest extends TestCase {
    protected function setUp(): void {
        CManager::asset()->reset();
    }

    protected function tearDown(): void {
        CManager::asset()->reset();
    }

    public function testInlineJsCssAndPlainRenderAtTheirPosition() {
        $runtime = CManager::asset()->runTime();
        $runtime->registerJsInline("console.log('head');", CManager_Asset::POS_HEAD);
        $runtime->registerJsInline("console.log('end');", CManager_Asset::POS_END);
        $runtime->registerCssInline('body{margin:0}', CManager_Asset::POS_HEAD);
        $runtime->registerPlain('<meta name="uji" content="1">', CManager_Asset::POS_HEAD);

        $head = CManager::asset()->render(CManager_Asset::POS_HEAD);
        $this->assertStringContainsString("<script>console.log('head');</script>", $head);
        $this->assertStringContainsString('<style>body{margin:0}</style>', $head);
        $this->assertStringContainsString('<meta name="uji" content="1">', $head);
        $this->assertStringNotContainsString("console.log('end')", $head);

        $end = CManager::asset()->render(CManager_Asset::POS_END);
        $this->assertStringContainsString("<script>console.log('end');</script>", $end);
        $this->assertStringNotContainsString('<style>', $end);
    }

    public function testRenderCanBeLimitedToOneType() {
        $runtime = CManager::asset()->runTime();
        $runtime->registerJsInline('a();', CManager_Asset::POS_HEAD);
        $runtime->registerCssInline('b{}', CManager_Asset::POS_HEAD);

        $onlyCss = CManager::asset()->render(CManager_Asset::POS_HEAD, CManager_Asset::TYPE_CSS);
        $this->assertStringContainsString('<style>b{}</style>', $onlyCss);
        $this->assertStringNotContainsString('a();', $onlyCss);
        $this->assertSame('', CManager::asset()->render(CManager_Asset::POS_BEGIN), 'posisi kosong → string kosong');
    }

    public function testLocalAndRemoteJsFilesRenderAsScriptTags() {
        $runtime = CManager::asset()->runTime();
        $runtime->registerJsFile('https://cdn.contoh.test/lib.js');
        $runtime->registerJsFile('capp.js');
        $runtime->registerCssFile('https://cdn.contoh.test/lib.css');

        $end = CManager::asset()->render(CManager_Asset::POS_END);
        $this->assertStringContainsString('<script src="https://cdn.contoh.test/lib.js"', $end);
        $this->assertStringContainsString('<script src="' . curl::base() . 'media/js/capp.js', $end);
        $head = CManager::asset()->render(CManager_Asset::POS_HEAD);
        $this->assertStringContainsString('<link href="https://cdn.contoh.test/lib.css" rel="stylesheet"', $head);
        $this->assertSame(['https://cdn.contoh.test/lib.css'], CManager::asset()->getAllCssFileUrl());
    }

    public function testModuleFilesComeBeforeRuntimeFiles() {
        CManager::asset()->module()->defineModule('uji-urut', ['js' => ['https://cdn.contoh.test/modul.js']]);
        CManager::registerModule('uji-urut');
        CManager::asset()->runTime()->registerJsFile('https://cdn.contoh.test/runtime.js');

        $urls = CManager::asset()->getAllJsFileUrl();
        $this->assertSame(['https://cdn.contoh.test/modul.js', 'https://cdn.contoh.test/runtime.js'], array_values(array_filter($urls, function ($url) {
            return strpos($url, 'cdn.contoh.test') !== false;
        })));
        $rendered = CManager::asset()->render(CManager_Asset::POS_END, CManager_Asset::TYPE_JS_FILE);
        $this->assertLessThan(strpos($rendered, 'runtime.js'), strpos($rendered, 'modul.js'));
    }

    public function testUnregisterJsAndCssFileRemoveTheRegisteredEntry() {
        $runtime = CManager::asset()->runTime();
        $runtime->registerJsFile('https://cdn.contoh.test/a.js');
        $runtime->registerJsFile('https://cdn.contoh.test/b.js');
        $runtime->registerJsFile('capp.js', CManager_Asset::POS_HEAD);
        $runtime->registerCssFile('https://cdn.contoh.test/a.css');

        $runtime->unregisterJsFile('https://cdn.contoh.test/a.js');
        $runtime->unregisterJsFile('capp.js', CManager_Asset::POS_HEAD);
        $runtime->unregisterCssFiles(['https://cdn.contoh.test/a.css']);

        $this->assertSame(['https://cdn.contoh.test/b.js'], $runtime->getAllJsFileUrl(), 'dulu fatal (allAvailablePos tidak ada di container) dan objek berkas tidak pernah cocok dengan string');
        $this->assertSame([], $runtime->getAllCssFileUrl());
        $runtime->unregisterJsFile('https://cdn.contoh.test/tidak-ada.js');
        $this->assertCount(1, $runtime->getAllJsFileUrl(), 'berkas yang tidak terdaftar diabaikan');
    }

    public function testWrapJsAppendsCompiledJavascriptAndTerminator() {
        $wrapped = CManager::asset()->wrapJs("alert(1);");
        $this->assertStringStartsWith('alert(1);', $wrapped);
        $this->assertStringEndsWith(PHP_EOL . ';' . PHP_EOL, $wrapped);

        $ready = CManager::asset()->wrapJs('alert(2);', true);
        $this->assertStringStartsWith('jQuery(document).ready(function(){alert(2);});', $ready);
    }

    public function testResetClearsEveryContainer() {
        CManager::asset()->runTime()->registerJsInline('x();', CManager_Asset::POS_HEAD);
        CManager::asset()->reset();

        $this->assertSame('', CManager::asset()->render(CManager_Asset::POS_HEAD));
        $this->assertSame([], CManager::asset()->getAllJsFileUrl());
    }
}
