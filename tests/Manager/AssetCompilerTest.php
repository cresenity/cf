<?php

use PHPUnit\Framework\TestCase;

/**
 * Covers `CManager_Asset_Compiler::compile()`, which concatenates theme assets
 * into one bundle when `assets.*.compile` is on.
 *
 * The bug this guards against: the destination file was truncated first and
 * then appended to, source by source. Recompilation happens whenever a source
 * is newer than the bundle -- that is, right after every deploy -- so a request
 * arriving during that window could be served an empty or half-written bundle,
 * and two concurrent requests could interleave their writes. The bundle is now
 * built in a temporary file and moved into place in one step.
 */
class Manager_AssetCompilerTest extends TestCase {
    /**
     * @var string
     */
    protected $dir;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cf-asset-compiler-' . uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /**
     * @param string $name
     * @param string $content
     *
     * @return string
     */
    protected function sourceFile($name, $content) {
        $path = $this->dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $content);

        return $path;
    }

    public function testBundlePathIsIdenticalForSameReleaseRegardlessOfFileMtime() {
        $files = ['/srv/a/media/js/a.js', '/srv/a/media/js/b.js'];

        $satu = CManager_Asset_Compiler::bundlePath('js', $files, 'eef78f63e781');
        $dua = CManager_Asset_Compiler::bundlePath('js', $files, 'eef78f63e781');

        $this->assertSame($satu, $dua);
        $this->assertStringContainsString('/eef78f63e781/', $satu);
    }

    public function testBundlePathChangesWithRelease() {
        $files = ['/srv/a/media/js/a.js'];

        $this->assertNotSame(
            CManager_Asset_Compiler::bundlePath('js', $files, 'rilis-lama'),
            CManager_Asset_Compiler::bundlePath('js', $files, 'rilis-baru')
        );
    }

    public function testBundlePathRejectsCharactersThatWouldEscapeTheFolder() {
        $path = CManager_Asset_Compiler::bundlePath('js', ['/srv/a.js'], '../../etc');

        $this->assertStringNotContainsString('..', $path);
        $this->assertStringContainsString('compiled/asset/js/etc/', $path);
    }

    public function testBundleContainsEverySourceInOrder() {
        $first = $this->sourceFile('a.js', 'var a = 1;');
        $second = $this->sourceFile('b.js', 'var b = 2;');
        $out = $this->dir . DIRECTORY_SEPARATOR . 'bundle.js';

        $compiler = new CManager_Asset_Compiler([$first, $second], ['type' => 'js', 'outFile' => $out]);
        $compiler->compile();

        $bundle = file_get_contents($out);
        $this->assertStringContainsString('var a = 1;', $bundle);
        $this->assertStringContainsString('var b = 2;', $bundle);
        $this->assertLessThan(strpos($bundle, 'var b = 2;'), strpos($bundle, 'var a = 1;'));
    }

    public function testNoTemporaryFileIsLeftBehind() {
        $first = $this->sourceFile('a.js', 'var a = 1;');
        $out = $this->dir . DIRECTORY_SEPARATOR . 'bundle.js';

        $compiler = new CManager_Asset_Compiler([$first], ['type' => 'js', 'outFile' => $out]);
        $compiler->compile();

        $this->assertCount(0, glob($this->dir . DIRECTORY_SEPARATOR . '*.tmp'));
    }

    public function testCssImportsFromLaterFilesAreHoistedToTheTop() {
        $first = $this->sourceFile('a.css', 'body { color: red; }');
        $second = $this->sourceFile('b.css', "@import url('https://fonts.googleapis.com/css2?family=Inter:wght@100;400&display=swap');\n.b { margin: 0; }");
        $out = $this->dir . DIRECTORY_SEPARATOR . 'bundle.css';

        $compiler = new CManager_Asset_Compiler([$first, $second], ['type' => 'css', 'outFile' => $out]);
        $compiler->compile();

        $bundle = file_get_contents($out);
        $this->assertSame(0, strpos(ltrim($bundle), '@import'), 'browser mengabaikan @import yang tidak berada di awal stylesheet');
        $this->assertSame(1, substr_count($bundle, '@import'));
        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Inter:wght@100;400&display=swap', $bundle);
        $this->assertLessThan(strpos($bundle, '.b'), strpos($bundle, 'body'));
    }

    public function testCssCharsetIsKeptOnceAtTheVeryStart() {
        $first = $this->sourceFile('a.css', "@charset \"UTF-8\";\nbody { color: red; }");
        $second = $this->sourceFile('b.css', "@charset \"UTF-8\";\n@import url(https://example.com/x.css);\n.b { margin: 0; }");
        $out = $this->dir . DIRECTORY_SEPARATOR . 'bundle.css';

        $compiler = new CManager_Asset_Compiler([$first, $second], ['type' => 'css', 'outFile' => $out]);
        $compiler->compile();

        $bundle = file_get_contents($out);
        $this->assertSame(0, strpos($bundle, '@charset'));
        $this->assertSame(1, substr_count($bundle, '@charset'));
        $this->assertLessThan(strpos($bundle, 'body'), strpos($bundle, '@import'));
    }

    public function testCssImportInsideCommentIsLeftAlone() {
        $first = $this->sourceFile('a.css', "/* contoh: @import url(https://example.com/nope.css); */\nbody { color: red; }");
        $out = $this->dir . DIRECTORY_SEPARATOR . 'bundle.css';

        $compiler = new CManager_Asset_Compiler([$first], ['type' => 'css', 'outFile' => $out]);
        $compiler->compile();

        $bundle = file_get_contents($out);
        $this->assertNotSame(0, strpos(ltrim($bundle), '@import'));
    }

    public function testExistingBundleIsNeverTruncatedWhileRecompiling() {
        $first = $this->sourceFile('a.js', 'var a = 1;');
        $out = $this->dir . DIRECTORY_SEPARATOR . 'bundle.js';

        $compiler = new CManager_Asset_Compiler([$first], ['type' => 'js', 'outFile' => $out]);
        $compiler->compile();
        $lama = file_get_contents($out);

        //sumbernya diubah dan dibuat lebih baru, sehingga bundelnya wajib dibangun ulang
        file_put_contents($first, 'var a = 99;');
        touch($first, time() + 10);

        $compilerBaru = new CManager_Asset_Compiler([$first], ['type' => 'js', 'outFile' => $out]);
        $this->assertTrue($compilerBaru->needToRecompile());
        //isi lama masih utuh sampai detik penggantian
        $this->assertSame($lama, file_get_contents($out));

        $compilerBaru->compile();
        $this->assertStringContainsString('var a = 99;', file_get_contents($out));
    }
}
