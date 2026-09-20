<?php
use PHPUnit\Framework\TestCase;

/**
 * Aset yang dirujuk framework (font avatar, blockly, debugbar, font chart) harus ada di system/media,
 * bukan lagi di modules/cresenity/media yang deprecated.
 */
class FrameworkAssetsInSystemMediaTest extends TestCase {
    public function testPathConstantsPointToSystemMedia() {
        $this->assertSame(DOCROOT . 'system/media', CConstant::CRESENITY_MEDIA_PATH);
        $this->assertSame(DOCROOT . 'system/media/font', CConstant::CRESENITY_FONT_PATH);
        $this->assertSame(DOCROOT . 'system/media/img', CConstant::CRESENITY_IMAGE_PATH);
        $this->assertDirectoryExists(CConstant::CRESENITY_FONT_PATH);
        $this->assertDirectoryExists(CConstant::CRESENITY_IMAGE_PATH);
    }

    public function testAvatarFontsExistInSystemMedia() {
        $this->assertFileExists(CConstant::CRESENITY_FONT_PATH . '/opensans/OpenSans-Regular.ttf');
        foreach (['Arabic', 'Armenian', 'Bengali', 'Georgian', 'Hebrew', 'Mongolian', 'Thai', 'Tibetan'] as $script) {
            $this->assertFileExists(CConstant::CRESENITY_FONT_PATH . '/notosans/script/Noto-' . $script . '-Regular.ttf', 'font ' . $script . ' hilang');
        }
        $this->assertFileExists(CConstant::CRESENITY_FONT_PATH . '/notosans/script/Noto-CJKJP-Regular.otf');
        $this->assertFileExists(CConstant::CRESENITY_FONT_PATH . '/pf_arma_five.ttf', 'font chart pf_arma_five');
    }

    public function testAvatarRendersWithDefaultAndScriptFonts() {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
            $this->markTestSkipped('GD/FreeType tidak tersedia');
        }
        // findFontFile() jatuh ke font GD bawaan (int 1) bila berkas tidak ada — itu yang tidak boleh terjadi
        $findFontFile = new ReflectionMethod(CImage_Avatar_Engine_Initials::class, 'findFontFile');
        $findFontFile->setAccessible(true);

        $latin = (new CImage_Avatar_Engine_Initials())->name('Hery Setiawan');
        $this->assertSame(CConstant::CRESENITY_FONT_PATH . '/opensans/OpenSans-Regular.ttf', $findFontFile->invoke($latin));
        $this->assertStringStartsWith("\x89PNG", (string) CImage::avatar()->api()->setName('Hery Setiawan')->setSize(32)->render());

        $arabic = (new CImage_Avatar_Engine_Initials())->name('محمد علي')->autoFont(true);
        $this->assertSame(CConstant::CRESENITY_FONT_PATH . '/notosans/script/Noto-Arabic-Regular.ttf', $findFontFile->invoke($arabic), 'inisial Arab memakai font Noto Arabic');
        $this->assertStringStartsWith("\x89PNG", (string) CImage::avatar()->api()->setName('محمد علي')->setSize(32)->render());
    }

    public function testBlocklyAssetModuleResolvesToSystemMedia() {
        $modules = include SYSPATH . 'data' . DS . 'assets-module.php';
        $runtime = CManager::asset()->runTime();
        foreach ($modules['blockly']['js'] as $file) {
            $path = $runtime->fullpathJsFile($file);
            $this->assertFileExists($path, $file . ' tidak ditemukan');
            $this->assertStringStartsWith(SYSPATH . 'media' . DS . 'js' . DS, $path, $file . ' harus dilayani dari system/media/js');
        }
        $this->assertDirectoryExists(SYSPATH . 'media/js/blockly/media', 'folder media (sprite) blockly ikut pindah');
        $this->assertDirectoryDoesNotExist(DOCROOT . 'modules/cresenity/media/js/blockly');
    }

    public function testBlocklyComponentMediaFolderPointsToSystemMedia() {
        $blockly = new CElement_Component_Blockly('blocklyTest');
        $js = str_replace('\\/', '/', $blockly->js());
        $this->assertStringContainsString('system/media/js/blockly/media/', $js);
        $this->assertStringNotContainsString('modules/cresenity', $js);
    }

    public function testDebugBarHeadUrlsNoLongerPointToModules() {
        $renderer = new CDebug_DebugBar_Renderer(CDebug::bar());
        $head = $renderer->renderHead();
        $this->assertStringContainsString('media/css/debug/debugbar.css', $head);
        $this->assertStringContainsString('media/js/debug/debugbar.js', $head);
        $this->assertStringNotContainsString('modules/cresenity', $head);
        preg_match_all("/(?:href|src)='([^']+)'/", $head, $matches);
        foreach ($matches[1] as $url) {
            $relative = ltrim(parse_url($url, PHP_URL_PATH), '/');
            $this->assertFileExists(DOCROOT . $relative, 'berkas debugbar ' . $relative . ' tidak ada');
        }
    }

    public function testChartFontLoadsFromSystemMedia() {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD tidak tersedia');
        }
        $image = new CImage_Chart_Image(100, 100, new CImage_Chart_Data());
        $image->setFontProperties(['fontName' => CConstant::CRESENITY_FONT_PATH . '/pf_arma_five.ttf', 'fontSize' => 6]);
        $this->assertStringEndsWith('pf_arma_five.ttf', $image->fontName);
    }
}
