<?php
use PHPUnit\Framework\TestCase;

/**
 * Kelas CFormInput* (deprecated sejak 1.2, pengganti CElement_FormInput_*) sudah dihapus dan tidak
 * boleh dirujuk lagi dari system/.
 */
class RemovedLegacyFormInputTest extends TestCase {
    public function testLegacyFormInputClassesAreGone() {
        $this->assertSame([], glob(SYSPATH . 'libraries' . DS . 'CFormInput*.php'));
        foreach (['CFormInput', 'CFormInputText', 'CFormInputHidden', 'CFormInputSelectSearch', 'CFormInputCheckbox', 'CFormInputCurrency', 'CFormInputLabel', 'CFormInputSelect'] as $class) {
            $this->assertFalse(class_exists($class), $class . ' masih bisa di-autoload');
        }
        $this->assertTrue(class_exists('CElement_FormInput_Hidden'), 'penggantinya tetap ada');
        $this->assertFileDoesNotExist(SYSPATH . 'libraries' . DS . 'CPHPInfo.php');
        $this->assertFalse(class_exists('CPHPInfo'), 'CPHPInfo (deprecated 2.0) sudah dihapus, pakai CServer::phpInfo()');
        $this->assertTrue(class_exists('CServer_PhpInfo'));
        $this->assertTrue(class_exists('CElement_FormInput_SelectSearch'));
    }

    public function testNothingInSystemReferencesThemAnymore() {
        $offenders = [];
        foreach (['libraries', 'views', 'core', 'config', 'data'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SYSPATH . $dir)) as $file) {
                if (!in_array($file->getExtension(), ['php']) || strpos($file->getPathname(), 'graphify') !== false) {
                    continue;
                }
                if (preg_match('/(?<![A-Za-z0-9_])(CFormInput[A-Za-z0-9_]*|CPHPInfo)\b/', file_get_contents($file->getPathname()))) {
                    $offenders[] = str_replace(SYSPATH, '', $file->getPathname());
                }
            }
        }
        $this->assertSame([], $offenders);
    }
}
