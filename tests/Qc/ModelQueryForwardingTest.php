<?php

use PHPUnit\Framework\TestCase;

/**
 * Forwarding Model -> CModel_Query -> CDatabase_Query_Builder di ekstensi PHPStan
 * bergantung pada dua hal yang mudah bergeser tanpa ada yang gagal keras.
 *
 * Pertama, nama template model yang dibaca ekstensi harus sama dengan yang
 * dideklarasikan `@template` pada CModel_Query. Ekstensi membawa nama Larastan
 * (`TModelClass`) sementara CModel_Query memakai `TModel`, sehingga pencarian
 * template selalu null dan `TBModel::leftJoin()`, `Model::insertGetId()` dan
 * kawan-kawannya dilaporkan tidak ada (#-13849, terukur pada tribelio).
 *
 * Kedua, anotasi `@method` yang ditulis tangan pada CModel/CModel_Query menang
 * atas ekstensi. Yang signature-nya lebih sempit dari method builder aslinya
 * membuat pemanggilan yang sah dilaporkan kelebihan argumen (`whereIn($col,
 * $values, 'or')`).
 *
 * Berkas ekstensi dibaca sebagai teks: kelas CQC_Phpstan bergantung pada
 * PHPStan yang tidak dimuat di suite ini.
 */
class ModelQueryForwardingTest extends TestCase {
    /**
     * @return string
     */
    protected function docroot() {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
    }

    /**
     * @return string
     */
    protected function declaredModelTemplate() {
        $doc = (new ReflectionClass(CModel_Query::class))->getDocComment();
        $this->assertSame(1, preg_match('/@template\s+(\w+)\s+of\s+\\\\?CModel\b/', $doc, $m), 'CModel_Query harus mendeklarasikan @template model');

        return $m[1];
    }

    public function testExtensionsLookUpTheTemplateNameCModelQueryDeclares() {
        $source = file_get_contents($this->docroot() . 'system/libraries/CQC/Phpstan/Service/BuilderHelper.php');
        $this->assertSame(1, preg_match("/const MODEL_TEMPLATE = '(\w+)';/", $source, $m));
        $this->assertSame($this->declaredModelTemplate(), $m[1]);
    }

    public function testNoExtensionStillUsesTheUpstreamTemplateName() {
        $base = $this->docroot() . 'system/libraries/CQC/Phpstan';
        $offenders = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && strpos((string) file_get_contents($file->getPathname()), "'TModelClass'") !== false) {
                $offenders[] = substr($file->getPathname(), strlen($base) + 1);
            }
        }

        $this->assertSame([], $offenders, 'Masih mencari template TModelClass: ' . implode(', ', $offenders));
    }

    public function testCustomMethodReflectionsProvideGetOnlyVariant() {
        foreach (['ModelQueryMethodReflection', 'AnnotationScopeMethodReflection', 'DynamicWhereMethodReflection'] as $class) {
            $source = file_get_contents($this->docroot() . 'system/libraries/CQC/Phpstan/Reflection/' . $class . '.php');
            $this->assertStringContainsString('public function getOnlyVariant()', $source, $class . ' dipanggil getOnlyVariant() oleh ekstensi lain');
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public function annotatedClasses() {
        return [
            'CModel' => [CModel::class],
            'CModel_Query' => [CModel_Query::class],
        ];
    }

    /**
     * @dataProvider annotatedClasses
     *
     * @param string $class
     */
    public function testMethodTagsAreNotNarrowerThanTheRealBuilderMethod($class) {
        $doc = (new ReflectionClass($class))->getDocComment();
        preg_match_all('/@method\s+(?:static\s+)?\S+\s+(\w+)\(([^)]*)\)/', $doc, $tags, PREG_SET_ORDER);
        $this->assertNotEmpty($tags);

        $narrower = [];
        foreach ($tags as $tag) {
            $name = $tag[1];
            $tagCount = trim($tag[2]) === '' ? 0 : count(explode(',', $tag[2]));
            foreach ([CModel_Query::class, CDatabase_Query_Builder::class] as $target) {
                if ($target === $class || !method_exists($target, $name)) {
                    continue;
                }
                $real = (new ReflectionMethod($target, $name))->getNumberOfParameters();
                if ($tagCount < $real) {
                    $narrower[] = $name . ' (' . $tagCount . ' vs ' . $real . ')';
                }

                break;
            }
        }

        $this->assertSame([], $narrower, 'Tag @method lebih sempit dari method aslinya: ' . implode(', ', $narrower));
    }
}
