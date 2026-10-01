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
            'decimal' => 'string',
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

    public static function getFields($table, $prefix = '', $model = null) {
        $db = c::db();
        $result = $db->getSchemaManager()->listTableColumns($table);

        if (empty($result)) {
            throw new Exception('table ' . $table . ' not found');
        }
        $properties = [];
        $modelInstance = $model !== null ? static::getModelInstanceByName($prefix, $model) : static::getModelInstance($prefix, $table);
        $excludedFields = static::getInheritedAuditFields($modelInstance);

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
            $isCast = array_key_exists($field, $casts);
            $dates = $modelInstance ? $modelInstance->getDates() : [];
            if (!$isCast && in_array($type, ['date', 'datetime'], true) && !in_array($field, $dates, true)) {
                //di luar $dates/casts CF mengembalikan string mentah dari database, bukan Carbon
                $type = 'string';
            }
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
     * Kolom audit yang sudah dideklarasikan `@property` oleh kelas induk model (mis. docblock TBModel) tidak perlu
     * diulang di tiap model; model yang kelas induknya tidak mendeklarasikannya tetap mendapat anotasinya.
     *
     * @param null|CModel $modelInstance
     *
     * @return string[]
     */
    public static function getInheritedAuditFields($modelInstance) {
        $auditFields = ['created', 'createdby', 'updated', 'updatedby', 'status'];
        if ($modelInstance == null) {
            return $auditFields;
        }

        $declared = [];
        $parent = (new ReflectionClass($modelInstance))->getParentClass();
        while ($parent) {
            $docComment = (string) $parent->getDocComment();
            foreach ($auditFields as $field) {
                if (preg_match('/@property(?:-read|-write)?\s+\S+\s+\$' . $field . '\b/', $docComment) === 1) {
                    $declared[$field] = true;
                }
            }
            $parent = $parent->getParentClass();
        }

        return array_keys($declared);
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

        if (preg_match('/@return\s+(.+)/', $docComment, $matches) !== 1) {
            return null;
        }

        $type = static::firstTypeToken($matches[1]);
        if (strlen($type) == 0 || in_array($type, ['mixed', 'void', '$this'])) {
            return null;
        }

        return $type;
    }

    /**
     * Token pertama dari teks berformat `tipe sisa`, dengan spasi di dalam <>, () atau {} tetap bagian dari tipe
     * (`array<string, mixed> $x` -> `array<string, mixed>`).
     *
     * @param string $text
     *
     * @return string
     */
    public static function firstTypeToken($text) {
        $text = ltrim($text);
        $depth = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === '<' || $char === '(' || $char === '{') {
                $depth++;
            } elseif ($char === '>' || $char === ')' || $char === '}') {
                $depth = max(0, $depth - 1);
            } elseif ($depth === 0 && ctype_space($char)) {
                return substr($text, 0, $i);
            }
        }

        return rtrim($text);
    }

    /**
     * Tulis blok properti ke docblock kelas model: baris `@property*` yang ada diganti di tempat baris pertamanya,
     * teks lain di docblock tetap, docblock tanpa `@property` ditambahi sebelum penutupnya, dan kelas tanpa docblock
     * mendapat docblock baru. Mengembalikan null bila tidak ada deklarasi kelas.
     *
     * @param string $content         isi berkas model
     * @param string $propertiesBlock baris ` * @property ...` dipisah "\n", kosong berarti hanya menghapus yang lama
     *
     * @return null|string
     */
    public static function classDocblock($content) {
        if (preg_match('/^(?:abstract\s+|final\s+)?class\s+\w+/mi', $content, $classMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $before = substr($content, 0, $classMatch[0][1]);
        if (preg_match('#/\*\*(?:(?!\*/).)*\*/\s*\z#s', $before, $docMatch) !== 1) {
            return null;
        }

        return rtrim($docMatch[0]);
    }

    /**
     * Baris `@property*` (tanpa awalan ` * `) pada docblock kelas saja; docblock lain di berkas diabaikan.
     *
     * @param string $content isi berkas model
     *
     * @return string[]
     */
    public static function classDocblockPropertyLines($content) {
        $docblock = static::classDocblock($content);
        if ($docblock === null) {
            return [];
        }
        preg_match_all('/^\s*\*\s*(@property(?:-read|-write)?\s.*)$/m', $docblock, $matches);

        return $matches[1];
    }

    /**
     * Tulis blok properti ke docblock kelas model (lihat applyPropertiesToDocblockInner).
     *
     * @param string $content
     * @param string $propertiesBlock
     *
     * @return null|string
     */
    public static function applyPropertiesToDocblock($content, $propertiesBlock) {
        return static::applyPropertiesToClassDocblock($content, $propertiesBlock);
    }

    protected static function applyPropertiesToClassDocblock($content, $propertiesBlock) {
        if (preg_match('/^(?:abstract\s+|final\s+)?class\s+\w+/mi', $content, $classMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $newline = strpos($content, "\r\n") !== false ? "\r\n" : "\n";
        $propertyLines = $propertiesBlock === '' ? [] : explode("\n", $propertiesBlock);
        $classOffset = $classMatch[0][1];
        $before = substr($content, 0, $classOffset);
        $rest = substr($content, $classOffset);

        if (preg_match('#/\*\*(?:(?!\*/).)*\*/\s*\z#s', $before, $docMatch, PREG_OFFSET_CAPTURE) !== 1) {
            if (count($propertyLines) === 0) {
                return $content;
            }

            return $before . '/**' . $newline . implode($newline, $propertyLines) . $newline . ' */' . $newline . $rest;
        }

        $docRaw = $docMatch[0][0];
        $prefix = substr($before, 0, $docMatch[0][1]);
        $docTrimmed = rtrim($docRaw);
        $trailing = substr($docRaw, strlen($docTrimmed));

        $lines = preg_split('/\r?\n/', $docTrimmed);
        if (count($lines) === 1) {
            $inner = trim(substr($docTrimmed, 3, -2));
            $lines = ['/**'];
            if ($inner !== '') {
                $lines[] = ' * ' . $inner;
            }
            $lines[] = ' */';
        }

        $out = [];
        $inserted = false;
        foreach ($lines as $line) {
            if (preg_match('/^\s*\*\s*@property(?:-read|-write)?\s/', $line) === 1) {
                if (!$inserted) {
                    $out = array_merge($out, $propertyLines);
                    $inserted = true;
                }

                continue;
            }
            $out[] = $line;
        }
        if (!$inserted && count($propertyLines) > 0) {
            $closing = array_pop($out);
            $out = array_merge($out, $propertyLines, [$closing]);
        }

        return $prefix . implode($newline, $out) . $trailing . $rest;
    }

    /**
     * Pecah baris `@property tipe $var deskripsi` menjadi bagian-bagiannya; spasi di dalam <>, () atau {} tetap
     * bagian dari tipe.
     *
     * @param string $line
     *
     * @return array [tag, type, var, desc]
     */
    public static function parsePropertyLine($line) {
        $line = trim(preg_replace('/\s+/', ' ', $line));
        $tag = static::firstTypeToken($line);
        $rest = ltrim(substr($line, strlen($tag)));
        $type = static::firstTypeToken($rest);
        $rest = ltrim(substr($rest, strlen($type)));
        $var = static::firstTypeToken($rest);
        $desc = ltrim(substr($rest, strlen($var)));

        return [$tag, $type, $var, $desc];
    }

    /**
     * Satu anotasi per variabel; yang pertama menang. Docblock yang sudah terlanjur menggandakan properti sembuh sendiri.
     *
     * @param array $properties daftar ['var' => '$nama', ...]
     *
     * @return array
     */
    public static function uniqueByVariable(array $properties) {
        $seen = [];
        $result = [];
        foreach ($properties as $property) {
            $var = carr::get($property, 'var');
            if ($var !== null && $var !== '' && isset($seen[$var])) {
                continue;
            }
            $seen[$var] = true;
            $result[] = $property;
        }

        return $result;
    }

    /**
     * Buang properti yang `$isMissing`-nya true dan indeks ulang hasilnya supaya posisi array sama dengan array_column().
     *
     * @param array    $properties
     * @param callable $isMissing  menerima satu properti, true bila harus dibuang
     *
     * @return array
     */
    public static function withoutProperties(array $properties, callable $isMissing) {
        return array_values(array_filter($properties, function ($property) use ($isMissing) {
            return !$isMissing($property);
        }));
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

    /**
     * @param string $prefix
     * @param string $model  nama model tanpa prefiks
     *
     * @return null|CModel
     */
    public static function getModelInstanceByName($prefix, $model) {
        $modelClass = $prefix . 'Model_' . $model;

        return class_exists($modelClass) ? new $modelClass() : null;
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
