<?php

class CModel_Console_PropertiesHelper {
    public static function getGenericTypeForFieldProperty(array $property) {
        $type = carr::get($property, 'type');
        if (!carr::get($property, 'notnull')) {
            $type = 'null|' . $type;
        }

        return $type;
    }

    public static function getSpatialType($type) {
        $typeConvertion = [
            'multipolygon' => CModel_Spatial_Geometry_MultiPolygon::class,
            'polygon' => CModel_Spatial_Geometry_Polygon::class,
            'point' => CModel_Spatial_Geometry_Point::class,
            'multipoint' => CModel_Spatial_Geometry_MultiPoint::class,
            'linestring' => CModel_Spatial_Geometry_LineString::class,
            'multilinestring' => CModel_Spatial_Geometry_MultiLineString::class,
            'geometrycollection' => CModel_Spatial_Geometry_GeometryCollection::class,
        ];

        return carr::get($typeConvertion, $type);
    }

    public static function getType($type) {
        $typeConvertion = [
            'tinyint' => 'int',
            'smallint' => 'int',
            'mediumint' => 'int',
            'bigint' => 'int',
            'decimal' => 'int',
            'float' => 'float',
            'double' => 'double',
            'double unsigned' => 'double',
            'bit' => 'int',
            'char' => 'string',
            'varchar' => 'string',
            'binary' => 'string',
            'varbinary' => 'string',
            'tinyblob' => 'string',
            'blob' => 'string',
            'mediumblob' => 'string',
            'longblob' => 'string',
            'tinytext' => 'string',
            'text' => 'string',
            'mediumtext' => 'string',
            'longtext' => 'string',
            'enum' => 'string',
            'set' => 'string',
            'date' => 'CCarbon|\Carbon\Carbon',
            'time' => 'string',
            'datetime' => 'CCarbon|\Carbon\Carbon',
            'timestamp' => 'string',
            'year' => 'string',
            'boolean' => 'bool',
            //casts convertion
            'integer' => 'int',
            'array' => 'array',
            'json' => 'array',
        ];

        if ($result = carr::get($typeConvertion, $type)) {
            return $result;
        }
        if ($spatialResult = self::getSpatialType($type)) {
            return $spatialResult;
        }

        return $type;
    }

    public static function getRelationMethods($modelClass) {
        //get relation field
        $reflectionClass = new ReflectionClass($modelClass);
        $blacklistMethods = [
            'belongsTo'
        ];
        $returnTypeClasses = [
            CModel_Relation_BelongsTo::class,
            CModel_Relation_BelongsToThrough::class,
            CModel_Relation_BelongsToMany::class,
            CModel_Relation_HasMany::class,
            CModel_Relation_HasManyThrough::class,
            CModel_Relation_HasManyDeep::class,
            CModel_Relation_MorphMany::class,
            CModel_Relation_MorphOne::class,
            CModel_Relation_HasOne::class,
        ];

        $methods = c::collect($reflectionClass->getMethods())->map(function (ReflectionMethod $method) use ($returnTypeClasses, $blacklistMethods) {
            if (in_array($method->getName(), $blacklistMethods)) {
                //skip when method is blacklisted to be processed
                return false;
            }
            if ($method->getFileName() != $method->getDeclaringClass()->getFileName()) {
                //skip when method is not in same file
                return false;
            }
            $docComment = $method->getDocComment();

            if ($docComment) {
                if (strpos($docComment, '@return') !== false) {
                    if (preg_match('#\@return\s(.+?)\n#ims', $docComment, $matches)) {
                        $matches = array_slice($matches, 1);
                        foreach ($matches as $match) {
                            $returnTypes = explode('|', $match);
                            foreach ($returnTypes as $returnType) {
                                if (cstr::startsWith($returnType, '\\')) {
                                    $returnType = cstr::substr($returnType, 1);
                                }
                                if (in_array($returnType, $returnTypeClasses)) {
                                    list($relationClass, $isWithTrashed) = static::getRelationClass($method);
                                    if ($relationClass && $relationType = static::getRelationType($returnType, $relationClass, $isWithTrashed)) {
                                        return [
                                            'method' => $method->getName(),
                                            'returnType' => $returnType,
                                            'relationClass' => $relationClass,
                                            'type' => $relationType,
                                            'isWithTrashed' => $isWithTrashed,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return false;
        })->filter()->toArray();

        return $methods;
    }

    public static function getRelationType($returnType, $relationClass, $isWithTrashed) {
        $relationType = null;
        if ($returnType == CModel_Relation_BelongsTo::class
            || $returnType == CModel_Relation_BelongsToThrough::class
        ) {
            $relationType = $relationClass;
            if (!$isWithTrashed) {
                $relationType = 'null|' . $relationType;
            }
        }

        if ($returnType == CModel_Relation_BelongsToMany::class
            || $returnType == CModel_Relation_HasMany::class
            || $returnType == CModel_Relation_MorphMany::class
            || $returnType == CModel_Relation_HasManyThrough::class
            || $returnType == CModel_Relation_HasManyDeep::class
        ) {
            $relationType = 'CModel_Collection|' . $relationClass . '[]';
        }
        if ($returnType == CModel_Relation_HasOne::class || $returnType == CModel_Relation_MorphOne::class) {
            $relationType = 'null|' . $relationClass;
        }

        return $relationType;
    }

    public static function getRelationClass(ReflectionMethod $method) {
        $codeSnippet = static::getCodeSnippet($method->getFileName(), $method->getStartLine(), $method->getEndLine());
        $regex = '#\$this->.+?\((.+?)[\,\)]#ims';
        if (preg_match($regex, $codeSnippet, $matches)) {
            $relationClass = trim($matches[1]);
            if (cstr::endsWith($relationClass, '::class')) {
                $relationClass = cstr::substr($relationClass, 0, cstr::len($relationClass) - 7);
            }
            if (cstr::len($relationClass) > 1 && cstr::startsWith($relationClass, '\'') && cstr::endsWith($relationClass, '\'')) {
                $relationClass = cstr::substr($relationClass, 1, cstr::len($relationClass) - 2);
            }

            if (cstr::startsWith($relationClass, [')', '->', '$'])) {
                return [null, null];
            }
            //argumen pertama morphTo() adalah nama relasi (lazim `__FUNCTION__`), bukan kelas
            if (!class_exists($relationClass)) {
                return [null, null];
            }
            $isWithTrashed = strpos($codeSnippet, '->withTrashed') !== false;

            return [$relationClass, $isWithTrashed];
        }

        return [null, null];
    }

    public static function getCodeSnippet($path, $startLine, $endLine) {
        if ($endLine < $startLine) {
            return [];
        }
        $file = new SplFileObject($path);
        $file->seek($startLine - 1);
        $code = [];
        $code[] = $file->current();
        for ($i = $startLine; $i < $endLine; $i++) {
            $file->next();
            $code[] = $file->current();
        }

        return implode(PHP_EOL, $code);
    }

    public static function getFields($table, $prefix = '') {
        $excludedFields = ['created', 'createdby', 'updated', 'updatedby', 'status'];
        $db = c::db();
        $result = $db->getSchemaManager()->listTableColumns($table);

        if (empty($result)) {
            throw new Exception('table ' . $table . ' not found');
        }
        $properties = [];
        $modelInstance = static::getModelInstance($prefix, $table);

        foreach ($result as $key => $column) {
            /** @var CDatabase_Schema_Column $column */
            $field = trim($key, '`');
            $type = $column->getType()->getName();
            if ($type == 'boolean') {
                //when type is boolean, we cast it on int first then cast again when cast defined
                $type = 'int';
            }
            $casts = [];
            if ($modelInstance) {
                $casts = $modelInstance->getCasts();
            }
            $casts = static::sanitizeCastType($casts);
            $type = carr::get($casts, $field, $type);
            $type = static::getType($type);
            $notnull = $column->getNotnull();

            //Accessor menang atas tipe kolom maupun casts: yang diterima
            //pembaca atribut adalah nilai kembalian accessornya, bukan isi
            //kolomnya. Tanpa ini `credentials` yang dibaca sebagai array
            //ditulis `string` - anotasi yang salah, dan anotasi salah lebih
            //buruk daripada tidak ada anotasi sebab ia menang atas ekstensi
            //PHPStan yang menurunkan tipe dari kode.
            $accessorType = static::getAccessorType($modelInstance, $field);
            if ($accessorType !== null) {
                $type = $accessorType;
                //nullability-nya ikut anotasi accessor itu sendiri, jadi
                //jangan ditambahi `null|` lagi dari kolomnya
                $notnull = true;
            }

            if (!in_array($field, $excludedFields)) {
                $properties[$field] = [
                    'type' => $type,
                    'notnull' => $notnull,
                    'default' => $column->getDefault(),
                ];
            }
        }

        return $properties;
    }

    /**
     * Tipe yang dijanjikan accessor sebuah kolom, bila ada.
     *
     * Dibaca dari `@return` accessornya, bukan dari tipe kembalian bawaan -
     * kode CF menargetkan PHP 7.4 dan hampir tidak pernah menuliskannya.
     * `mixed` dan `void` diabaikan: keduanya tidak lebih memberi tahu daripada
     * tipe kolomnya sendiri.
     *
     * @param null|CModel $modelInstance
     * @param string      $field
     *
     * @return null|string
     */
    public static function getAccessorType($modelInstance, $field) {
        if ($modelInstance == null) {
            return null;
        }

        $method = 'get' . cstr::studly($field) . 'Attribute';
        if (!method_exists($modelInstance, $method)) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($modelInstance, $method);
        } catch (ReflectionException $ex) {
            return null;
        }

        $returnType = $reflection->getReturnType();
        if ($returnType != null && method_exists($returnType, 'getName')) {
            $name = $returnType->getName();
            if (!in_array($name, ['mixed', 'void'])) {
                return $returnType->allowsNull() ? 'null|' . $name : $name;
            }
        }

        $docComment = $reflection->getDocComment();
        if ($docComment === false) {
            return null;
        }

        if (preg_match('/@return\s+(\S+)/', $docComment, $matches) !== 1) {
            return null;
        }

        $type = trim($matches[1]);
        if (strlen($type) == 0 || in_array($type, ['mixed', 'void', '$this'])) {
            return null;
        }

        return $type;
    }

    public static function getModel($table) {
        $model = $table;
        switch ($table) {
            case 'users':
                $model = 'user';

                break;
            case 'roles':
                $model = 'role';

                break;
        }

        $temp = explode('_', $model);
        $model = '';
        foreach ($temp as $val) {
            $model .= ucfirst($val);
        }

        return $model;
    }

    public static function sanitizeCastType($casts) {
        foreach ($casts as $key => $cast) {
            if (cstr::startsWith($cast, 'date:')) {
                $casts[$key] = 'datetime';
            }
        }

        return $casts;
    }

    /**
     * @param string $prefix
     * @param string $table
     *
     * @return CModel
     */
    public static function getModelInstance($prefix, $table) {
        $modelClass = static::getModelClass($prefix, $table);

        if (!class_exists($modelClass)) {
            return null;
        }

        return new $modelClass();
    }

    public static function getModelClass($prefix, $table) {
        return  $prefix . 'Model_' . static::getModel($table);
    }

    public static function getTable($table) {
        switch ($table) {
            case 'user':
                $table = 'users';

                break;
            case 'role':
                $table = 'roles';

                break;
        }

        return $table;
    }
}
