<?php
use PHPUnit\Framework\TestCase;

/**
 * Kelas legacy yang sudah dihapus (CFormInput*, CPHPInfo, CTab*, CTable*, CSql) tidak boleh bisa di-autoload
 * atau dirujuk lagi dari system/; penggantinya tetap ada.
 */
class RemovedLegacyClassesTest extends TestCase {
    /** @var string[] */
    const REMOVED = ['CPHPInfo', 'CSql', 'CTab', 'CTabStatic', 'CTabList', 'CTable', 'CTableColumn', 'CTableOptions', 'CTableRow'];

    public function testLegacyFormInputClassesAreGone() {
        $this->assertSame([], glob(SYSPATH . 'libraries' . DS . 'CFormInput*.php'));
        foreach (['CFormInput', 'CFormInputText', 'CFormInputHidden', 'CFormInputSelectSearch', 'CFormInputCheckbox', 'CFormInputCurrency', 'CFormInputLabel', 'CFormInputSelect'] as $class) {
            $this->assertFalse(class_exists($class), $class . ' masih bisa di-autoload');
        }
        $this->assertTrue(class_exists('CElement_FormInput_Hidden'), 'penggantinya tetap ada');
        foreach (static::REMOVED as $class) {
            $this->assertFileDoesNotExist(SYSPATH . 'libraries' . DS . $class . '.php');
            $this->assertFalse(class_exists($class), $class . ' sudah dihapus');
        }
        foreach (['CServer_PhpInfo', 'CParser_Sql', 'CElement_Component_DataTable', 'CElement_Component_DataTable_Column', 'CElement_Component_TableRow', 'CElement_List_TabList'] as $class) {
            $this->assertTrue(class_exists($class), 'pengganti ' . $class . ' tetap ada');
        }
        $this->assertInstanceOf(CElement_Component_TableRow::class, (new CElement_Element_Div())->addRow(), 'addRow() kompat mengembalikan kelas modern');
        $this->assertTrue(class_exists('CElement_FormInput_SelectSearch'));
    }

    public function testNothingInSystemReferencesThemAnymore() {
        $offenders = [];
        foreach (['libraries', 'views', 'core', 'config', 'data'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SYSPATH . $dir)) as $file) {
                if (!in_array($file->getExtension(), ['php']) || strpos($file->getPathname(), 'graphify') !== false) {
                    continue;
                }
                if (preg_match('/(?<![A-Za-z0-9_])(CFormInput[A-Za-z0-9_]*|' . implode('|', static::REMOVED) . ')(?![A-Za-z0-9_])/', file_get_contents($file->getPathname()))) {
                    $offenders[] = str_replace(SYSPATH, '', $file->getPathname());
                }
            }
        }
        $this->assertSame([], $offenders);
    }
}
