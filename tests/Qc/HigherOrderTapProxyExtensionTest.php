<?php

use PHPUnit\Framework\TestCase;

/**
 * CQC_Phpstan_Method_HigherOrderTapProxyExtension dan
 * CQC_Phpstan_Service_ReturnType_HigherOrderTapProxyExtension sama-sama
 * bergantung pada dua hal yang tidak boleh diam-diam berubah tanpa ikut
 * memperbaiki ekstensinya: nama parameter template `TClass` di docblock
 * CBase_HigherOrderTapProxy, dan perilaku __call() yang membuang hasil
 * metode lalu mengembalikan $target. Ekstensinya sendiri tidak dites lewat
 * PHPStan di suite ini (CQC_Phpstan bergantung pada PHPStan yang tidak
 * dimuat di sini) - dites lewat teks sumber + perilaku runtime nyata.
 */
class HigherOrderTapProxyExtensionTest extends TestCase {
    public function testCallForwardsToTargetAndReturnsTargetNotTheMethodResult() {
        $target = new class() {
            /**
             * @var int
             */
            public $calls = 0;

            public function addOne($n) {
                $this->calls++;

                return $n + 1;
            }
        };

        $proxy = new CBase_HigherOrderTapProxy($target);
        $returned = $proxy->addOne(41);

        $this->assertSame(1, $target->calls, 'metodenya harus benar-benar dipanggil di $target');
        $this->assertSame($target, $returned, '__call() harus mengembalikan $target, bukan hasil addOne() (42)');
    }

    public function testTapWithoutCallbackReturnsTheProxyWrappingTheSameValue() {
        $value = new stdClass();
        $proxy = c::tap($value);

        $this->assertInstanceOf(CBase_HigherOrderTapProxy::class, $proxy);
        $this->assertSame($value, $proxy->target);
    }

    public function testTapWithCallbackReturnsTheValueItselfNotTheProxy() {
        $value = new stdClass();
        $seen = null;

        $result = c::tap($value, function ($v) use (&$seen) {
            $seen = $v;
        });

        $this->assertSame($value, $seen, 'callback harus menerima $value yang sama');
        $this->assertSame($value, $result, 'tap() dengan callback harus passthrough $value, bukan proxy');
    }

    public function testClassDeclaresTheTClassTemplateTheExtensionsReadBothRelyOnIt() {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/system/libraries/CBase/HigherOrderTapProxy.php');

        $this->assertStringContainsString('@template TClass', $source);
    }

    public function testBothExtensionsPointAtTheSameClassAndTemplateKey() {
        $base = dirname(__DIR__, 2) . '/system/libraries/CQC/Phpstan/';

        $methodSource = (string) file_get_contents($base . 'Method/HigherOrderTapProxyExtension.php');
        $returnTypeSource = (string) file_get_contents($base . 'Service/ReturnType/HigherOrderTapProxyExtension.php');

        $this->assertStringContainsString('CBase_HigherOrderTapProxy::class', $methodSource);
        $this->assertStringContainsString("'TClass'", $methodSource);
        $this->assertStringContainsString('CBase_HigherOrderTapProxy::class', $returnTypeSource);
    }
}
