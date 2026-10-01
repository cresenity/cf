<?php
use PHPUnit\Framework\TestCase;

class UjiProps_Author extends CModel {
    protected $table = 'uji_author';

    /**
     * @return CModel_Relation_HasMany
     */
    public function posts() {
        return $this->hasMany(UjiProps_Post::class, 'author_id');
    }

    /**
     * @return CModel_Relation_HasOne
     */
    public function profile() {
        return $this->hasOne(UjiProps_Post::class, 'author_id');
    }

    /**
     * Tanpa @return: dilewati generator.
     */
    public function tags() {
        return $this->belongsToMany(UjiProps_Post::class);
    }

    /**
     * @return CModel_Relation_MorphTo
     */
    public function owner() {
        return $this->morphTo(__FUNCTION__);
    }

    /**
     * @return CModel_Relation_BelongsTo
     */
    public function keepDeleted() {
        return $this->belongsTo(UjiProps_Post::class, 'post_id')->withTrashed();
    }
}

class UjiProps_Post extends CModel {
    protected $table = 'uji_post';

    /**
     * @return \CModel_Relation_BelongsTo
     */
    public function author() {
        return $this->belongsTo('UjiProps_Author', 'author_id');
    }
}

/**
 * CModel_Console_PropertiesHelper: bagian murni generator docblock model:update.
 */
/**
 * @property string $createdby
 * @property int    $status
 */
class UjiProps_AuditBase extends CModel {
}

class UjiProps_AuditChild extends UjiProps_AuditBase {
}

class UjiProps_NoAuditChild extends CModel {
}

class ModelPropertiesHelperTest extends TestCase {
    public function testGetTypeMapsDatabaseTypesToPhpDocTypes() {
        $this->assertSame('int', CModel_Console_PropertiesHelper::getType('bigint'));
        $this->assertSame('string', CModel_Console_PropertiesHelper::getType('decimal'), 'PDO mengembalikan decimal sebagai string numerik');
        $this->assertSame('string', CModel_Console_PropertiesHelper::getType('varchar'));
        $this->assertSame('CCarbon|\Carbon\Carbon', CModel_Console_PropertiesHelper::getType('datetime'));
        $this->assertSame('string', CModel_Console_PropertiesHelper::getType('timestamp'));
        $this->assertSame('bool', CModel_Console_PropertiesHelper::getType('boolean'));
        $this->assertSame('array', CModel_Console_PropertiesHelper::getType('json'));
        $this->assertSame(CModel_Spatial_Geometry_Point::class, CModel_Console_PropertiesHelper::getType('point'));
        $this->assertSame('tipe_aneh', CModel_Console_PropertiesHelper::getType('tipe_aneh'), 'yang tidak dikenal diteruskan apa adanya');
    }

    public function testSpatialTypes() {
        $this->assertSame(CModel_Spatial_Geometry_MultiPolygon::class, CModel_Console_PropertiesHelper::getSpatialType('multipolygon'));
        $this->assertNull(CModel_Console_PropertiesHelper::getSpatialType('varchar'));
    }

    public function testGenericTypeAddsNullForNullableColumns() {
        $this->assertSame('null|string', CModel_Console_PropertiesHelper::getGenericTypeForFieldProperty(['type' => 'string', 'notnull' => false]));
        $this->assertSame('string', CModel_Console_PropertiesHelper::getGenericTypeForFieldProperty(['type' => 'string', 'notnull' => true]));
        $this->assertSame('null|int', CModel_Console_PropertiesHelper::getGenericTypeForFieldProperty(['type' => 'int']));
    }

    public function testModelAndTableNameConventions() {
        $this->assertSame('User', CModel_Console_PropertiesHelper::getModel('users'));
        $this->assertSame('Role', CModel_Console_PropertiesHelper::getModel('roles'));
        $this->assertSame('SalesOrderItem', CModel_Console_PropertiesHelper::getModel('sales_order_item'));
        $this->assertSame('users', CModel_Console_PropertiesHelper::getTable('user'));
        $this->assertSame('roles', CModel_Console_PropertiesHelper::getTable('role'));
        $this->assertSame('sales_order', CModel_Console_PropertiesHelper::getTable('sales_order'));
        $this->assertSame('SFModel_SalesOrder', CModel_Console_PropertiesHelper::getModelClass('SF', 'sales_order'));
        $this->assertNull(CModel_Console_PropertiesHelper::getModelInstance('SF', 'tidak_ada'));
    }

    public function testSanitizeCastTypeCollapsesDateFormats() {
        $this->assertSame(['a' => 'datetime', 'b' => 'int', 'c' => 'datetime'], CModel_Console_PropertiesHelper::sanitizeCastType(['a' => 'date:Y-m-d', 'b' => 'int', 'c' => 'datetime']));
    }

    public function testRelationTypeByReturnType() {
        $this->assertSame('null|UjiProps_Post', CModel_Console_PropertiesHelper::getRelationType(CModel_Relation_BelongsTo::class, 'UjiProps_Post', false));
        $this->assertSame('UjiProps_Post', CModel_Console_PropertiesHelper::getRelationType(CModel_Relation_BelongsTo::class, 'UjiProps_Post', true), 'withTrashed → tidak pernah null');
        $this->assertSame('CModel_Collection|UjiProps_Post[]', CModel_Console_PropertiesHelper::getRelationType(CModel_Relation_HasMany::class, 'UjiProps_Post', false));
        $this->assertSame('CModel_Collection|UjiProps_Post[]', CModel_Console_PropertiesHelper::getRelationType(CModel_Relation_BelongsToMany::class, 'UjiProps_Post', false));
        $this->assertSame('null|UjiProps_Post', CModel_Console_PropertiesHelper::getRelationType(CModel_Relation_HasOne::class, 'UjiProps_Post', false));
        $this->assertNull(CModel_Console_PropertiesHelper::getRelationType(CModel_Relation_MorphTo::class, 'X', false));
    }

    public function testRelationMethodsAreReadFromDocblocksAndSource() {
        $methods = CModel_Console_PropertiesHelper::getRelationMethods(UjiProps_Author::class);
        $byName = c::collect($methods)->keyBy('method');
        $this->assertSame(['posts', 'profile', 'keepDeleted'], $byName->keys()->all(), 'tanpa @return dilewati; morphTo(__FUNCTION__) bukan kelas → dilewati');
        $this->assertSame('CModel_Collection|UjiProps_Post[]', $byName['posts']['type']);
        $this->assertSame('UjiProps_Post', $byName['posts']['relationClass']);
        $this->assertSame('null|UjiProps_Post', $byName['profile']['type']);
        $this->assertTrue($byName['keepDeleted']['isWithTrashed']);
        $this->assertSame('UjiProps_Post', $byName['keepDeleted']['type']);

        $post = c::collect(CModel_Console_PropertiesHelper::getRelationMethods(UjiProps_Post::class))->keyBy('method');
        $this->assertSame('null|UjiProps_Author', $post['author']['type'], 'nama kelas dalam string dan @return berawalan backslash');
    }

    public function testRelationClassEdgeCases() {
        list($class, $withTrashed) = CModel_Console_PropertiesHelper::getRelationClass(new ReflectionMethod(UjiProps_Author::class, 'owner'));
        $this->assertNull($class, 'argumen pertama morphTo adalah nama relasi');
        list($class, $withTrashed) = CModel_Console_PropertiesHelper::getRelationClass(new ReflectionMethod(UjiProps_Author::class, 'keepDeleted'));
        $this->assertSame('UjiProps_Post', $class);
        $this->assertTrue($withTrashed);
    }

    public function testCodeSnippetReadsLinesInclusive() {
        $method = new ReflectionMethod(UjiProps_Post::class, 'author');
        $snippet = CModel_Console_PropertiesHelper::getCodeSnippet($method->getFileName(), $method->getStartLine(), $method->getEndLine());
        $this->assertStringContainsString('public function author()', $snippet);
        $this->assertStringContainsString("belongsTo('UjiProps_Author'", $snippet);
        $this->assertSame([], CModel_Console_PropertiesHelper::getCodeSnippet(__FILE__, 10, 5), 'rentang terbalik → kosong');
    }

    public function testFirstTypeTokenKeepsSpacesInsideGenerics() {
        $this->assertSame('int', CModel_Console_PropertiesHelper::firstTypeToken('int $x'));
        $this->assertSame('array<string, mixed>', CModel_Console_PropertiesHelper::firstTypeToken('array<string, mixed> $x desc'));
        $this->assertSame('array{a: int, b: string}', CModel_Console_PropertiesHelper::firstTypeToken("  array{a: int, b: string} \$x"));
        $this->assertSame('callable(int, int)', CModel_Console_PropertiesHelper::firstTypeToken('callable(int, int) ok'));
        $this->assertSame('null|CModel_Collection<int, Foo>', CModel_Console_PropertiesHelper::firstTypeToken('null|CModel_Collection<int, Foo> $foos'));
        $this->assertSame('string', CModel_Console_PropertiesHelper::firstTypeToken('string'), 'tanpa spasi: seluruh teks');
    }

    public function testParsePropertyLine() {
        $this->assertSame(['@property', 'int', '$id', ''], CModel_Console_PropertiesHelper::parsePropertyLine('@property int $id'));
        $this->assertSame(['@property-read', 'null|Foo', '$foo', 'relasi ke foo'], CModel_Console_PropertiesHelper::parsePropertyLine('@property-read    null|Foo      $foo   relasi ke foo'));
        $this->assertSame(['@property', 'array<string, mixed>', '$meta', 'data tambahan'], CModel_Console_PropertiesHelper::parsePropertyLine('@property array<string, mixed> $meta data tambahan'), 'generik dengan spasi tidak terpecah');
    }

    public function testApplyPropertiesCreatesDocblockWhenMissing() {
        $content = "<?php\n\nclass TBModel_Foo extends TBModel {\n}\n";
        $result = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, " * @property int \$id\n * @property string \$name");

        $this->assertSame("<?php\n\n/**\n * @property int \$id\n * @property string \$name\n */\nclass TBModel_Foo extends TBModel {\n}\n", $result);
    }

    public function testApplyPropertiesReplacesOldPropertyLinesInPlaceAndKeepsOtherText() {
        $content = "<?php\n\n/**\n * Deskripsi model.\n *\n * @mixin Bar\n * @property int \$old\n * @property-read Foo \$gone\n * @method static x()\n */\nclass TBModel_Foo extends TBModel {\n}\n";
        $result = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, " * @property int \$id");

        $this->assertSame("<?php\n\n/**\n * Deskripsi model.\n *\n * @mixin Bar\n * @property int \$id\n * @method static x()\n */\nclass TBModel_Foo extends TBModel {\n}\n", $result);
    }

    public function testApplyPropertiesAddsToADocblockThatHasNoPropertiesWithoutLosingItsText() {
        $content = "<?php\n\n/**\n * Deskripsi penting.\n *\n * @mixin Bar\n */\nclass TBModel_Foo extends TBModel {\n}\n";
        $result = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, " * @property int \$id");

        $this->assertSame("<?php\n\n/**\n * Deskripsi penting.\n *\n * @mixin Bar\n * @property int \$id\n */\nclass TBModel_Foo extends TBModel {\n}\n", $result);
    }

    public function testApplyPropertiesExpandsAOneLineDocblock() {
        $content = "<?php\n/** Model foo. */\nclass TBModel_Foo extends TBModel {\n}\n";
        $result = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, " * @property int \$id");

        $this->assertSame("<?php\n/**\n * Model foo.\n * @property int \$id\n */\nclass TBModel_Foo extends TBModel {\n}\n", $result);
    }

    public function testApplyPropertiesHandlesAbstractAndFinalClasses() {
        foreach (['abstract class', 'final class'] as $declaration) {
            $content = "<?php\n\n" . $declaration . " TBModel_Foo extends TBModel {\n}\n";
            $result = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, ' * @property int $id');

            $this->assertSame("<?php\n\n/**\n * @property int \$id\n */\n" . $declaration . " TBModel_Foo extends TBModel {\n}\n", $result, $declaration . ' mendapat docblock tepat di atas deklarasinya');
        }
    }

    public function testApplyPropertiesDoesNotTouchEarlierDocblocksOrCodeBetween() {
        $content = "<?php\n\n/** Berkas ini. */\n\nuse Foo\\Bar;\n\n/**\n * @property int \$old\n */\nclass TBModel_Foo extends TBModel {\n    /**\n     * @property int \$dalamMetode\n     */\n    public function a() {\n    }\n}\n";
        $result = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, ' * @property int $id');

        $this->assertStringContainsString("/** Berkas ini. */\n\nuse Foo\\Bar;\n", $result, 'docblock dan kode sebelum docblock kelas utuh');
        $this->assertStringContainsString('@property int $dalamMetode', $result, 'docblock method tidak disentuh');
        $this->assertStringNotContainsString('$old', $result);
        $this->assertSame(1, substr_count($result, '@property int $id'));
    }

    public function testApplyPropertiesIsLiteralForDollarDigitsAndBackslashes() {
        $content = "<?php\n\nclass TBModel_Foo extends TBModel {\n}\n";
        $block = ' * @property null|\\Carbon\\Carbon $3d_secure' . "\n" . ' * @property string $1x';
        $result = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, $block);

        $this->assertStringContainsString(' * @property null|\\Carbon\\Carbon $3d_secure', $result, '$3 bukan referensi balik');
        $this->assertStringContainsString(' * @property string $1x', $result);
    }

    public function testApplyPropertiesKeepsCrlfAndIsIdempotent() {
        $content = "<?php\r\n\r\n/**\r\n * @property int \$old\r\n */\r\nclass TBModel_Foo extends TBModel {\r\n}\r\n";
        $once = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, ' * @property int $id');

        $this->assertStringNotContainsString("\r\r", $once);
        $this->assertSame(substr_count($once, "\r\n"), substr_count($once, "\n"), 'semua baris tetap CRLF');
        $this->assertSame($once, CModel_Console_PropertiesHelper::applyPropertiesToDocblock($once, ' * @property int $id'), 'dijalankan dua kali tidak berubah');
    }

    public function testApplyPropertiesReturnsNullWithoutAClass() {
        $this->assertNull(CModel_Console_PropertiesHelper::applyPropertiesToDocblock("<?php\n// kosong\n", ' * @property int $id'));
    }

    public function testEmptyPropertiesBlockOnlyRemovesOldLines() {
        $content = "<?php\n\n/**\n * Teks.\n * @property int \$old\n */\nclass TBModel_Foo extends TBModel {\n}\n";

        $this->assertSame("<?php\n\n/**\n * Teks.\n */\nclass TBModel_Foo extends TBModel {\n}\n", CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, ''));
        $noDoc = "<?php\n\nclass TBModel_Foo extends TBModel {\n}\n";
        $this->assertSame($noDoc, CModel_Console_PropertiesHelper::applyPropertiesToDocblock($noDoc, ''), 'tanpa docblock dan tanpa properti: tidak ada yang dibuat');
    }

    public function testApplyPropertiesKeepsBlankLinesAroundTheDocblock() {
        $content = "<?php\n\ndefined('SYSPATH') or die('x');\n\n/**\n * @property int \$old\n */\n\n\nclass TBModel_Foo extends TBModel {\n}\n";
        $result = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, ' * @property int $id');

        $this->assertSame("<?php\n\ndefined('SYSPATH') or die('x');\n\n/**\n * @property int \$id\n */\n\n\nclass TBModel_Foo extends TBModel {\n}\n", $result, 'baris kosong antara docblock dan class tidak berubah');
    }

    public function testAuditFieldsAreExcludedOnlyWhenAParentDeclaresThem() {
        $this->assertEqualsCanonicalizing(['createdby', 'status'], CModel_Console_PropertiesHelper::getInheritedAuditFields(new UjiProps_AuditChild()), 'dideklarasikan induk: tidak diulang');
        $this->assertSame([], CModel_Console_PropertiesHelper::getInheritedAuditFields(new UjiProps_NoAuditChild()), 'induk tidak mendeklarasikan: tetap dianotasi di model');
        $this->assertSame(['created', 'createdby', 'updated', 'updatedby', 'status'], CModel_Console_PropertiesHelper::getInheritedAuditFields(null), 'tanpa instans model: perilaku lama');
    }

    public function testClassDocblockPropertyLinesIgnoreOtherDocblocksInTheFile() {
        $content = "<?php\n\n/**\n * Model lama.\n * @property int \$lama\n */\n\n/**\n * Docblock kelas.\n * @property string \$nama desc\n * @property-read null|Foo \$foo\n */\nclass TBModel_Foo extends TBModel {\n    /**\n     * @property int \$dalamMetode\n     */\n    public function a() {\n    }\n}\n";

        $this->assertSame(['@property string $nama desc', '@property-read null|Foo $foo'], CModel_Console_PropertiesHelper::classDocblockPropertyLines($content));
        $this->assertSame([], CModel_Console_PropertiesHelper::classDocblockPropertyLines("<?php\nclass TBModel_Foo extends TBModel {\n}\n"), 'tanpa docblock');
        $this->assertSame([], CModel_Console_PropertiesHelper::classDocblockPropertyLines("<?php\n// tanpa class\n"));
    }

    public function testApplyingTwiceOnAFileWithTwoDocblocksDoesNotDuplicate() {
        $content = "<?php\n\n/**\n * @property int \$lama\n */\n\n/**\n * Docblock kelas.\n */\nclass TBModel_Foo extends TBModel {\n}\n";
        $block = " * @property int \$id\n * @property string \$nama";

        $once = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($content, $block);
        $twice = CModel_Console_PropertiesHelper::applyPropertiesToDocblock($once, implode("\n", array_map(function ($line) {
            return ' * ' . $line;
        }, CModel_Console_PropertiesHelper::classDocblockPropertyLines($once))));

        $this->assertSame($once, $twice, 'membaca lalu menulis ulang properti docblock kelas tidak mengubah apa pun');
        $this->assertSame(1, substr_count($once, '$id'));
        $this->assertStringContainsString('@property int $lama', $once, 'docblock file-level tidak disentuh');
    }

    public function testUniqueByVariableKeepsTheFirstAnnotationOfEachVariable() {
        $result = CModel_Console_PropertiesHelper::uniqueByVariable([
            ['var' => '$id', 'type' => 'int'],
            ['var' => '$type', 'type' => 'null|string'],
            ['var' => '$type', 'type' => 'string'],
            ['var' => '$id', 'type' => 'int'],
            ['var' => '$name', 'type' => 'string'],
        ]);

        $this->assertSame(['$id', '$type', '$name'], array_column($result, 'var'));
        $this->assertSame('null|string', $result[1]['type'], 'yang pertama menang');
        $this->assertSame([], CModel_Console_PropertiesHelper::uniqueByVariable([]));
    }
}
