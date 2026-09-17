<?php

use PHPStan\Type\Type;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\TypeCombinator;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\PropertiesClassReflectionExtension;

/**
 * Properti model yang tidak ditulis sebagai `@property`.
 *
 * Tanpa ini PHPStan melapor `Access to an undefined property` untuk tiga bentuk
 * yang sah dan lazim: relasi yang dibaca sebagai properti (`$model->customer`),
 * accessor (`$model->full_name` dari `getFullNameAttribute()`), dan kolom tabel
 * (`$model->network_id`). Semuanya diturunkan dari kode dan skema DB, bukan dari
 * anotasi - anotasi `@property` di CF dirawat tidak merata, dan salah lapor yang
 * banyak justru membuat orang mematikan pemeriksaannya.
 *
 * Anotasi tetap menang: bila `@property` ada, ekstensi ini mundur.
 *
 * Satu hal yang berbeda dari Larastan dan menentukan bentuk kodenya: relasi di
 * Laravel punya template `TResult`, sehingga hasil sebuah relasi dapat dibaca
 * langsung dari tipenya. CF tidak punya - jadi jamak atau tunggalnya diputuskan
 * dari kelas relasinya, dan daftar di MANY_RELATION diturunkan dari isi
 * `getResults()` masing-masing: yang memanggil `->get()`/`newCollection()`
 * jamak, yang memanggil `->first()` tunggal.
 *
 * @internal
 */
final class CQC_Phpstan_Service_Property_ModelPropertyExtension implements PropertiesClassReflectionExtension {
    /**
     * Relasi yang hasilnya koleksi. Selebihnya satu model atau null.
     *
     * @var string[]
     */
    const MANY_RELATION = [
        CModel_Relation_HasMany::class,
        CModel_Relation_HasManyThrough::class,
        CModel_Relation_HasManyDeep::class,
        CModel_Relation_BelongsToMany::class,
        CModel_Relation_MorphMany::class,
        CModel_Relation_MorphToMany::class,
    ];

    /**
     * @var array<string, CQC_Phpstan_Service_Property_SchemaTable>
     */
    private $tables = [];

    /**
     * @var string
     */
    private $dateClass;

    /**
     * @var TypeStringResolver
     */
    private $stringResolver;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var CQC_Phpstan_Service_RelationParserHelper
     */
    private $relationParserHelper;

    /**
     * @var CQC_Phpstan_Service_Property_DatabaseSchemaHelper
     */
    private $schemaHelper;

    public function __construct(
        TypeStringResolver $stringResolver,
        ReflectionProvider $reflectionProvider,
        CQC_Phpstan_Service_RelationParserHelper $relationParserHelper,
        CQC_Phpstan_Service_Property_DatabaseSchemaHelper $schemaHelper
    ) {
        $this->stringResolver = $stringResolver;
        $this->reflectionProvider = $reflectionProvider;
        $this->relationParserHelper = $relationParserHelper;
        $this->schemaHelper = $schemaHelper;
    }

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool {
        if (!$classReflection->isSubclassOf(CModel::class)) {
            return false;
        }

        if ($classReflection->isAbstract()) {
            return false;
        }

        //anotasi yang ditulis tangan tetap menang
        if (CQC_Phpstan_Reflection_ReflectionHelper::hasPropertyTag($classReflection, $propertyName)) {
            return false;
        }

        if ($this->hasAttribute($classReflection, $propertyName)) {
            return true;
        }

        if ($this->findRelationMethod($classReflection, $propertyName) !== null) {
            return true;
        }

        if ($propertyName == 'pivot') {
            //TODO: check for belongsToMany relation
            return true;
        }

        return $this->findColumn($classReflection, $propertyName) !== null;
    }

    public function getProperty(
        ClassReflection $classReflection,
        string $propertyName
    ): PropertyReflection {
        if ($this->hasAttribute($classReflection, $propertyName)) {
            $type = $this->attributeType($classReflection, $propertyName);

            return new CQC_Phpstan_Service_Property_ModelProperty($classReflection, $type, $type);
        }

        $relationMethod = $this->findRelationMethod($classReflection, $propertyName);
        if ($relationMethod !== null) {
            //hanya dibaca: menulis ke properti relasi tidak menyimpan apa pun
            return new CQC_Phpstan_Service_Property_ModelProperty(
                $classReflection,
                $this->relationResultType($relationMethod),
                new NeverType(),
                false
            );
        }

        if ($propertyName == 'pivot') {
            return new CQC_Phpstan_Service_Property_PivotProperty(
                $classReflection,
            );
        }

        $column = $this->findColumn($classReflection, $propertyName);
        if ($column !== null) {
            return $this->columnProperty($classReflection, $column);
        }

        return new CQC_Phpstan_Service_Property_ModelProperty(
            $classReflection,
            new StringType(),
            new StringType()
        );
    }

    /**
     * Instance model tanpa konstruktor, untuk membaca tabel/kunci/casts-nya.
     *
     * @return null|CModel
     */
    private function modelInstance(ClassReflection $classReflection) {
        try {
            $instance = $classReflection->getNativeReflection()->newInstanceWithoutConstructor();
        } catch (ReflectionException $e) {
            return null;
        }

        return $instance instanceof CModel ? $instance : null;
    }

    /**
     * Kolom tabel yang namanya sama dengan properti ini, bila ada.
     *
     * @return null|CQC_Phpstan_Service_Property_SchemaColumn
     */
    private function findColumn(ClassReflection $classReflection, string $propertyName) {
        $modelInstance = $this->modelInstance($classReflection);
        if ($modelInstance === null) {
            return null;
        }

        return $this->schemaHelper->column($modelInstance, $propertyName);
    }

    /**
     * Tipe baca/tulis sebuah kolom: casts dan `$dates` model menang atas tipe
     * kolomnya, dan kolom tanggal/json boleh ditulis sebagai string.
     */
    private function columnProperty(ClassReflection $classReflection, CQC_Phpstan_Service_Property_SchemaColumn $column): PropertyReflection {
        $modelInstance = $this->modelInstance($classReflection);
        $casts = CModel_Console_PropertiesHelper::sanitizeCastType($modelInstance->getCasts());
        $cast = isset($casts[$column->name]) ? $casts[$column->name] : null;
        $isDate = in_array($column->name, $this->getModelDateColumns($modelInstance), true)
            || in_array($cast, ['date', 'datetime', 'custom_datetime', 'immutable_date', 'immutable_datetime'], true);

        if ($column->name === $modelInstance->getKeyName()) {
            $keyType = $this->stringResolver->resolve($modelInstance->getKeyType() === 'string' ? 'string' : 'int');

            return new CQC_Phpstan_Service_Property_ModelProperty($classReflection, $keyType, $keyType);
        }

        if ($isDate) {
            $readable = $this->stringResolver->resolve($this->getDateClass());
            $writable = TypeCombinator::union($readable, new StringType(), new ObjectType(DateTimeInterface::class));
        } else {
            $sqlType = $column->readableType === 'boolean' ? 'int' : $column->readableType;
            $isDateColumn = in_array($sqlType, ['date', 'datetime', 'timestamp', 'time', 'year'], true);
            //kolom tanggal di luar $dates/casts dibaca apa adanya: string
            $typeString = $cast === null && $isDateColumn ? 'string' : CModel_Console_PropertiesHelper::getType($cast !== null ? $cast : $sqlType);
            $readable = $this->stringResolver->resolve($typeString);
            $writable = $readable;

            if (in_array($cast, ['array', 'json', 'object', 'collection'], true)) {
                //nilai json lazim ditulis sebagai hasil json_encode()
                $writable = TypeCombinator::union($readable, new StringType());
            } elseif (in_array($cast, ['bool', 'boolean'], true)) {
                $writable = TypeCombinator::union($readable, new IntegerType());
            } elseif ($isDateColumn) {
                //tetap boleh diisi objek tanggal
                $writable = TypeCombinator::union($readable, $this->stringResolver->resolve($this->getDateClass()), new ObjectType(DateTimeInterface::class));
            }
        }

        if ($column->nullable) {
            $readable = TypeCombinator::addNull($readable);
            $writable = TypeCombinator::addNull($writable);
        }

        return new CQC_Phpstan_Service_Property_ModelProperty($classReflection, $readable, $writable);
    }

    private function getDateClass(): string {
        if (!$this->dateClass) {
            $this->dateClass = '\CCarbon|\Carbon\Carbon';
        }

        return $this->dateClass;
    }

    /**
     * @return string[]
     *
     * @phpstan-return array<int, string>
     */
    private function getModelDateColumns(CModel $modelInstance): array {
        $dateColumns = $modelInstance->getDates();

        if (method_exists($modelInstance, 'getDeletedAtColumn')) {
            $dateColumns[] = $modelInstance->getDeletedAtColumn();
        }

        return $dateColumns;
    }

    private function hasAttribute(ClassReflection $classReflection, string $propertyName): bool {
        if ($classReflection->hasNativeMethod('get' . cstr::studly($propertyName) . 'Attribute')) {
            return true;
        }

        $camelCase = cstr::camel($propertyName);

        if ($classReflection->hasNativeMethod($camelCase)) {
            $methodReflection = $classReflection->getNativeMethod($camelCase);

            if ($methodReflection->isPublic() || $methodReflection->isPrivate()) {
                return false;
            }

            $returnType = $methodReflection->getOnlyVariant()->getReturnType();

            if (!(new ObjectType(CModel_Casts_Attribute::class))->isSuperTypeOf($returnType)->yes()) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Tipe yang dibaca dari sebuah accessor.
     */
    private function attributeType(ClassReflection $classReflection, string $propertyName): Type {
        $getter = 'get' . cstr::studly($propertyName) . 'Attribute';

        if ($classReflection->hasNativeMethod($getter)) {
            return $classReflection->getNativeMethod($getter)->getOnlyVariant()->getReturnType();
        }

        //gaya CModel_Casts_Attribute: yang dikembalikan pembungkusnya, bukan
        //nilainya, dan CF belum memberinya template - jadi berhenti di sini
        //alih-alih mengarang tipe yang lebih sempit daripada yang diketahui
        return new MixedType();
    }

    /**
     * Method relasi yang namanya cocok dengan properti ini, bila ada.
     *
     * @return null|ExtendedMethodReflection
     */
    private function findRelationMethod(ClassReflection $classReflection, string $propertyName) {
        $methodName = $propertyName;

        if (!$classReflection->hasNativeMethod($methodName)) {
            //`$model->other_list` menunjuk method `otherList()`
            $methodName = cstr::camel($propertyName);

            if (strlen($methodName) == 0 || !$classReflection->hasNativeMethod($methodName)) {
                return null;
            }
        }

        $methodReflection = $classReflection->getNativeMethod($methodName);

        //relasi selalu publik; yang tersembunyi biasanya accessor gaya baru
        if (!$methodReflection->isPublic()) {
            return null;
        }

        $returnType = $methodReflection->getOnlyVariant()->getReturnType();

        if (!(new ObjectType(CModel_Relation::class))->isSuperTypeOf($returnType)->yes()) {
            return null;
        }

        return $methodReflection;
    }

    /**
     * Hasil sebuah relasi bila dibaca sebagai properti.
     */
    private function relationResultType(ExtendedMethodReflection $methodReflection): Type {
        $returnType = $methodReflection->getOnlyVariant()->getReturnType();

        //model terkait diturunkan dari badan methodnya - tipe kembalian yang
        //ditulis tangan hampir selalu tanpa generik (`@return CModel_Relation_HasMany`)
        $relatedModel = $this->relationParserHelper->findRelatedModelInRelationMethod($methodReflection);

        $relatedType = $relatedModel !== null
            ? new ObjectType($relatedModel)
            : new ObjectType(CModel::class);

        foreach (static::MANY_RELATION as $manyRelation) {
            if ((new ObjectType($manyRelation))->isSuperTypeOf($returnType)->yes()) {
                return new GenericObjectType(CModel_Collection::class, [
                    new IntegerType(),
                    $relatedType,
                ]);
            }
        }

        //relasi tunggal boleh kosong - baris induknya bisa saja sudah terhapus
        return TypeCombinator::addNull($relatedType);
    }
}
