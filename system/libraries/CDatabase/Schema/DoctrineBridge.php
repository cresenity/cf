<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Konversi dua arah antara objek skema CF (`CDatabase_Schema_*`) dan `Doctrine\DBAL\Schema\*`
 * asli, dipakai `CDatabase_Schema_Manager_Doctrine`/`CDatabase_Platform_Doctrine`.
 *
 * @internal
 */
final class CDatabase_Schema_DoctrineBridge {
    /**
     * @return CDatabase_Schema_Column
     */
    public static function columnFromDoctrine(\Doctrine\DBAL\Schema\Column $column) {
        $cfColumn = new CDatabase_Schema_Column($column->getName(), CDatabase_Type::getType($column->getType()->getName()), [
            'length' => $column->getLength(),
            'precision' => $column->getPrecision(),
            'scale' => $column->getScale(),
            'unsigned' => $column->getUnsigned(),
            'fixed' => $column->getFixed(),
            'notnull' => $column->getNotnull(),
            'default' => $column->getDefault(),
            'autoincrement' => $column->getAutoincrement(),
            'comment' => $column->getComment() !== '' ? $column->getComment() : null,
            'platformOptions' => $column->getPlatformOptions(),
            'customSchemaOptions' => $column->getCustomSchemaOptions(),
        ]);

        return $cfColumn;
    }

    /**
     * @return \Doctrine\DBAL\Schema\Column
     */
    public static function columnToDoctrine(CDatabase_Schema_Column $column) {
        return new \Doctrine\DBAL\Schema\Column($column->getName(), \Doctrine\DBAL\Types\Type::getType($column->getType()->getName()), [
            'length' => $column->getLength(),
            'precision' => $column->getPrecision(),
            'scale' => $column->getScale(),
            'unsigned' => $column->getUnsigned(),
            'fixed' => $column->getFixed(),
            'notnull' => $column->getNotnull(),
            'default' => $column->getDefault(),
            'autoincrement' => $column->getAutoincrement(),
            'comment' => $column->getComment(),
            'platformOptions' => $column->getPlatformOptions(),
            'customSchemaOptions' => $column->getCustomSchemaOptions(),
        ]);
    }

    /**
     * @return CDatabase_Schema_Index
     */
    public static function indexFromDoctrine(\Doctrine\DBAL\Schema\Index $index) {
        return new CDatabase_Schema_Index($index->getName(), $index->getUnquotedColumns(), $index->isUnique(), $index->isPrimary(), $index->getFlags(), $index->getOptions());
    }

    /**
     * @return \Doctrine\DBAL\Schema\Index
     */
    public static function indexToDoctrine(CDatabase_Schema_Index $index) {
        return new \Doctrine\DBAL\Schema\Index($index->getName(), $index->getUnquotedColumns(), $index->isUnique(), $index->isPrimary(), $index->getFlags(), $index->getOptions());
    }

    /**
     * @return CDatabase_Schema_ForeignKeyConstraint
     */
    public static function foreignKeyFromDoctrine(\Doctrine\DBAL\Schema\ForeignKeyConstraint $fk) {
        return new CDatabase_Schema_ForeignKeyConstraint($fk->getUnquotedLocalColumns(), $fk->getForeignTableName(), $fk->getUnquotedForeignColumns(), $fk->getName(), $fk->getOptions());
    }

    /**
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint
     */
    public static function foreignKeyToDoctrine(CDatabase_Schema_ForeignKeyConstraint $fk) {
        return new \Doctrine\DBAL\Schema\ForeignKeyConstraint($fk->getUnquotedLocalColumns(), $fk->getForeignTableName(), $fk->getUnquotedForeignColumns(), $fk->getName(), $fk->getOptions());
    }

    /**
     * @return CDatabase_Schema_Table
     */
    public static function tableFromDoctrine(\Doctrine\DBAL\Schema\Table $table) {
        $columns = array_map([self::class, 'columnFromDoctrine'], array_values($table->getColumns()));
        $indexes = array_map([self::class, 'indexFromDoctrine'], array_values($table->getIndexes()));
        $fks = array_map([self::class, 'foreignKeyFromDoctrine'], array_values($table->getForeignKeys()));

        return new CDatabase_Schema_Table($table->getName(), $columns, $indexes, $fks, false, $table->getOptions());
    }

    /**
     * @return \Doctrine\DBAL\Schema\Table
     */
    public static function tableToDoctrine(CDatabase_Schema_Table $table) {
        $columns = array_map([self::class, 'columnToDoctrine'], array_values($table->getColumns()));
        $doctrineTable = new \Doctrine\DBAL\Schema\Table($table->getName(), $columns);

        foreach ($table->getIndexes() as $index) {
            if ($index->isPrimary()) {
                $doctrineTable->setPrimaryKey($index->getUnquotedColumns(), $index->getName());
            } elseif ($index->isUnique()) {
                $doctrineTable->addUniqueIndex($index->getUnquotedColumns(), $index->getName());
            } else {
                $doctrineTable->addIndex($index->getUnquotedColumns(), $index->getName(), $index->getFlags(), $index->getOptions());
            }
        }

        foreach ($table->getForeignKeys() as $fk) {
            $doctrineTable->addForeignKeyConstraint($fk->getForeignTableName(), $fk->getUnquotedLocalColumns(), $fk->getUnquotedForeignColumns(), $fk->getOptions(), $fk->getName());
        }

        return $doctrineTable;
    }

    /**
     * Diff CF (dibangun `ChangeColumn::compile()`/`CDatabase_Fixer`) -> diff Doctrine, supaya
     * bisa diumpankan ke `getAlterTableSQL()` milik platform Doctrine asli.
     *
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    public static function tableDiffToDoctrine(CDatabase_Schema_Table_Diff $diff) {
        $changedColumns = [];
        foreach ($diff->changedColumns as $columnDiff) {
            /** @var CDatabase_Schema_Column_Diff $columnDiff */
            $changedColumns[] = new \Doctrine\DBAL\Schema\ColumnDiff(
                $columnDiff->oldColumnName,
                self::columnToDoctrine($columnDiff->column),
                $columnDiff->changedProperties,
                $columnDiff->fromColumn ? self::columnToDoctrine($columnDiff->fromColumn) : null
            );
        }

        $renamedColumns = [];
        foreach ($diff->renamedColumns as $oldName => $column) {
            $renamedColumns[$oldName] = self::columnToDoctrine($column);
        }

        $doctrineDiff = new \Doctrine\DBAL\Schema\TableDiff(
            $diff->name,
            array_map([self::class, 'columnToDoctrine'], $diff->addedColumns),
            $changedColumns,
            array_map([self::class, 'columnToDoctrine'], $diff->removedColumns),
            array_map([self::class, 'indexToDoctrine'], $diff->addedIndexes),
            array_map([self::class, 'indexToDoctrine'], $diff->changedIndexes),
            array_map([self::class, 'indexToDoctrine'], $diff->removedIndexes),
            $diff->fromTable instanceof CDatabase_Schema_Table ? self::tableToDoctrine($diff->fromTable) : null,
            array_map([self::class, 'foreignKeyToDoctrine'], $diff->addedForeignKeys),
            array_map([self::class, 'foreignKeyToDoctrine'], $diff->changedForeignKeys),
            $diff->removedForeignKeys,
            $renamedColumns
        );
        $doctrineDiff->newName = $diff->newName;

        return $doctrineDiff;
    }
}
