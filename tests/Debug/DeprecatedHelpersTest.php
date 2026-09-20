<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/DeprecatedCollectorTest.php';

/**
 * Helper lama (clang, ccfg, crequest, cdbutils, …) melapor ke kolektor deprecated hanya bila dipanggil
 * langsung dari kode di luar system/; pemakaian internal framework tidak dilaporkan.
 */
class DeprecatedHelpersTest extends TestCase {
    /** @var UjiDebug_DeprecatedCollector */
    protected $collector;

    /** @var array */
    protected $originalConfig = [];

    protected function setUp(): void {
        foreach (['collector.deprecated', 'collector.deprecatedPush', 'collector.deprecatedLimit'] as $key) {
            $this->originalConfig[$key] = CConfig::repository()->get($key);
        }
        CConfig::repository()->set('collector.deprecated', true);
        CConfig::repository()->set('collector.deprecatedPush', false);
        CConfig::repository()->set('collector.deprecatedLimit', 50);
        $this->collector = new UjiDebug_DeprecatedCollector();
        $property = new ReflectionProperty(CDebug_CollectorManager::class, 'deprecated');
        $property->setAccessible(true);
        $property->setValue(CDebug::collector(), $this->collector);
    }

    protected function tearDown(): void {
        foreach ($this->originalConfig as $key => $value) {
            CConfig::repository()->set($key, $value);
        }
        $property = new ReflectionProperty(CDebug_CollectorManager::class, 'deprecated');
        $property->setAccessible(true);
        $property->setValue(CDebug::collector(), null);
    }

    /**
     * Semua kelas helper yang @deprecated di level kelas, beserta penggantinya.
     *
     * @return array
     */
    public static function deprecatedHelperClasses() {
        return [
            'cdownload' => 'c::response()->download()', 'clang' => 'c::__', 'cphp' => 'CFile', 'cnav' => 'CApp_Navigation_Helper',
            'crouter' => 'c::router()', 'ctemp' => 'CTemporary', 'cmail' => 'CEmail', 'crole' => 'c::app()->role()',
            'clog' => 'c::log()', 'ccfg' => 'CApp_Config', 'cvalid' => 'CValidation', 'cfs' => 'CFile', 'cuser' => 'c::app()->user()',
            'ctransform' => 'c::transform', 'csess' => 'c::session()', 'crequest' => 'c::request()', 'cobj' => 'c::get', 'cmailapi' => 'CEmail',
        ];
    }

    public function testEveryPublicMethodOfADeprecatedHelperClassReportsItself() {
        foreach (array_keys(static::deprecatedHelperClasses()) as $class) {
            $source = file_get_contents(SYSPATH . 'helpers' . DS . $class . EXT);
            $this->assertStringContainsString('@deprecated', $source, $class);
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                $body = implode('', array_slice(file($method->getFileName()), $method->getStartLine(), 2));
                $this->assertStringContainsString('CF::deprecated(__METHOD__', $body, $class . '::' . $method->getName() . ' belum melapor ke kolektor deprecated');
            }
        }
    }

    public function testHelperCalledFromAppCodeIsReportedWithReplacementAndCaller() {
        $this->assertSame('Hello', clang::__('Hello'));
        ccfg::get('app_name');
        $this->assertIsBool(crequest::is_ajax());
        $this->assertIsString(cphp::string_value('a'));

        $apis = array_column($this->collector->stored, 'api');
        $this->assertContains('clang::__', $apis);
        $this->assertContains('ccfg::get', $apis);
        $this->assertContains('crequest::is_ajax', $apis);
        $this->assertContains('cphp::string_value', $apis);

        $byApi = array_column($this->collector->stored, null, 'api');
        $this->assertSame('c::__', $byApi['clang::__']['replacement']);
        $this->assertSame('1.6', $byApi['clang::__']['since']);
        $this->assertSame('CApp_Config::get()', $byApi['ccfg::get']['replacement']);
        $this->assertSame('c::request()', $byApi['crequest::is_ajax']['replacement']);
        $this->assertSame('CFile::phpValue', $byApi['cphp::string_value']['replacement']);
        $this->assertSame('1.2', $byApi['cphp::string_value']['since']);
        foreach ($this->collector->stored as $entry) {
            $this->assertSame(__FILE__, $entry['file'], $entry['api'] . ': pemanggil = berkas test ini');
        }
    }

    public function testHelperCalledFromInsideTheFrameworkIsNotReported() {
        // clang::getlang() (helper, di dalam system/) memanggil clang::defaultlang() → ccfg::get(): keduanya pemakaian internal
        clang::getlang();
        $apis = array_column($this->collector->stored, 'api');
        $this->assertSame(['clang::getlang'], $apis, 'hanya panggilan langsung dari luar system/ yang dilaporkan, rantai internalnya tidak');

        // c::__ (jalur modern) tidak menyentuh helper deprecated sama sekali
        $this->collector->flush();
        $this->collector->stored = [];
        c::__('Hello');
        $this->assertSame([], $this->collector->stored);
    }

    public function testDeprecatedMethodsOfLiveHelpersOnlyReportThemselves() {
        $this->assertSame('a&amp;b', chtml::specialchars('a&b'));
        $this->assertSame(['chtml::specialchars'], array_column($this->collector->stored, 'api'));
    }

    public function testCfDeprecatedWithAppCallerOnlyFalseStillReportsThroughFrameworkLayers() {
        UjiDebug_LegacyApi::oldWay();
        $this->assertSame(['UjiDebug_LegacyApi::oldWay'], array_column($this->collector->stored, 'api'), 'perilaku lama CF::deprecated() tanpa appCallerOnly tidak berubah');
    }
}
