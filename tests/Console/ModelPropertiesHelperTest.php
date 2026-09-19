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
class ModelPropertiesHelperTest extends TestCase {
    public function testGetTypeMapsDatabaseTypesToPhpDocTypes() {
        $this->assertSame('int', CModel_Console_PropertiesHelper::getType('bigint'));
        $this->assertSame('int', CModel_Console_PropertiesHelper::getType('decimal'), 'decimal dipetakan ke int (konvensi generator)');
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
}
