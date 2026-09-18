<?php

use PHPUnit\Framework\TestCase;

/**
 * Registrasi modul asset (CManager_Asset_Module): defineModule, registerModule menarik requirements
 * lebih dulu dan idempoten, modul tak dikenal melempar, requirements() rata, unregister, container
 * js/css per tipe, dan CManager::asset() menggabungkan modul runtime ke daftar URL.
 */
class AssetModuleRegistryTest extends TestCase {
    /**
     * @var CManager_Asset_Module
     */
    protected $module;

    protected function setUp(): void {
        CManager::asset()->reset();
        $this->module = CManager::asset()->module();
        $this->module->defineModule('uji-dasar', ['js' => ['uji/dasar.js'], 'css' => ['uji/dasar.css']]);
        $this->module->defineModule('uji-tengah', ['requirements' => ['uji-dasar'], 'js' => ['uji/tengah.js']]);
        $this->module->defineModule('uji-atas', ['requirements' => ['uji-tengah', 'uji-dasar'], 'js' => ['uji/atas.js'], 'css' => ['uji/atas.css']]);
    }

    protected function tearDown(): void {
        CManager::asset()->reset();
    }

    public function testDefineModuleMakesItKnownAndReplacesAnExistingDefinition() {
        $all = $this->module->allModules();

        $this->assertArrayHasKey('uji-dasar', $all);
        $this->assertArrayHasKey('jquery', $all, 'modul bawaan system/data/assets-module.php tetap ada');
        $this->module->defineModule('uji-dasar', ['js' => ['uji/lain.js']]);
        $this->assertSame(['js' => ['uji/lain.js']], $this->module->allModules()['uji-dasar']);
    }

    public function testRegisterModulePullsRequirementsFirstInDependencyOrder() {
        $this->module->registerModule(CManager_Asset_Module::MODULE_TYPE_RUNTIME, 'uji-atas');

        $this->assertSame(['uji-dasar', 'uji-tengah', 'uji-atas'], array_values($this->module->getRuntimeModules()));
        $this->assertTrue($this->module->isRegisteredModule('uji-tengah'));
        $this->assertFalse($this->module->isRegisteredModule('jquery'));
        $this->assertSame([], $this->module->getThemeModules());
    }

    public function testRegisteringTwiceOrViaRequirementsKeepsOneEntry() {
        $this->module->registerModules(CManager_Asset_Module::MODULE_TYPE_RUNTIME, ['uji-dasar', 'uji-atas', 'uji-dasar']);
        $this->module->registerRunTimeModules('uji-tengah');

        $this->assertSame(['uji-dasar', 'uji-tengah', 'uji-atas'], array_values($this->module->getRuntimeModules()));
    }

    public function testUnknownModuleThrows() {
        $this->expectException(CManager_Exception::class);
        $this->expectExceptionMessage('uji-tidak-ada');

        $this->module->registerModule(CManager_Asset_Module::MODULE_TYPE_RUNTIME, 'uji-tidak-ada');
    }

    public function testRequirementsAreFlattenedRecursively() {
        $this->assertSame(['uji-dasar', 'uji-tengah', 'uji-dasar'], $this->module->requirements('uji-atas'), 'urutan: dependensi terdalam dulu; duplikat tidak dihilangkan di sini');
        $this->assertSame([], $this->module->requirements('uji-dasar'));
        $this->assertSame([], $this->module->requirements('tidak-ada'));
    }

    public function testUnregisterModuleRemovesOnlyThatEntry() {
        $this->module->registerRunTimeModules('uji-atas');

        $this->assertTrue($this->module->unregisterModule(CManager_Asset_Module::MODULE_TYPE_RUNTIME, 'uji-tengah'));
        $this->assertFalse($this->module->unregisterModule(CManager_Asset_Module::MODULE_TYPE_RUNTIME, 'uji-tengah'), 'kedua kali sudah tidak ada');
        $this->assertSame(['uji-dasar', 'uji-atas'], array_values($this->module->getRuntimeModules()));
    }

    public function testRuntimeAndThemeRegistrationsAreKeptApart() {
        $this->module->registerRunTimeModules('uji-dasar');
        $this->module->registerThemeModules('uji-tengah');

        $this->assertSame(['uji-dasar'], array_values($this->module->getRuntimeModules()));
        $this->assertSame(['uji-dasar', 'uji-tengah'], array_values($this->module->getThemeModules()), 'requirements masuk ke tipe yang sama');
        $this->assertTrue($this->module->isRegisteredModule('uji-tengah'));
    }

    public function testContainerCollectsJsAndCssOfRegisteredModulesInOrder() {
        $this->module->registerRunTimeModules('uji-atas');

        $container = $this->module->getRunTimeContainer();
        $this->assertInstanceOf(CManager_Asset_Container_RunTime::class, $container);
        $this->assertCount(3, $container->jsFiles());
        $this->assertCount(2, $container->cssFiles());
        $this->assertInstanceOf(CManager_Asset_File_JsFile::class, $container->jsFiles()[0]);
        $property = new ReflectionProperty(CManager_Asset_FileAbstract::class, 'script');
        $property->setAccessible(true);
        $this->assertSame(['uji/dasar.js', 'uji/tengah.js', 'uji/atas.js'], array_map(function ($file) use ($property) {
            return $property->getValue($file);
        }, $container->jsFiles()), 'urutan dependensi dipertahankan; path baru diresolusi saat URL diminta');
        $this->assertSame([], $this->module->getThemeContainer()->jsFiles());
    }

    public function testResetForgetsRegistrationsButNotDefinitions() {
        $this->module->registerRunTimeModules('uji-atas');
        $this->module->reset();

        $this->assertSame([], $this->module->getRuntimeModules());
        $this->assertArrayHasKey('uji-atas', $this->module->allModules());
    }

    public function testAssetManagerMergesModuleFilesIntoTheUrlLists() {
        CManager::asset()->reset();
        $this->module->defineModule('uji-cdn', ['js' => ['https://cdn.contoh.test/lib.js'], 'css' => ['https://cdn.contoh.test/lib.css']]);
        CManager::registerModule('uji-cdn');

        $this->assertContains('https://cdn.contoh.test/lib.js', CManager::asset()->getAllJsFileUrl(), 'berkas remote dipakai apa adanya');
        $this->assertContains('https://cdn.contoh.test/lib.css', CManager::asset()->getAllCssFileUrl());
        $this->assertTrue(CManager::isRegisteredModule('uji-cdn'));
    }

    public function testRegisteringALocalJsFileBuildsABaseUrlWithVersion() {
        CManager::asset()->runTime()->registerJsFile('capp.js');

        $urls = CManager::asset()->runTime()->getAllJsFileUrl();
        $this->assertCount(1, $urls);
        $this->assertStringStartsWith(curl::base() . 'media/js/capp.js', $urls[0]);
    }

    public function testRegisteringAMissingLocalJsFileThrowsWhenResolvingTheUrl() {
        CManager::asset()->runTime()->registerJsFile('uji/tidak-ada.js');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File not exists, uji/tidak-ada.js');
        CManager::asset()->runTime()->getAllJsFileUrl();
    }
}
