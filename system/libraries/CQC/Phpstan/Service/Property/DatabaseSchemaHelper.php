<?php

/**
 * Kolom tabel model, dibaca dari skema database yang sedang tersambung.
 *
 * CF tidak memakai berkas migrasi, jadi padanan pembaca migrasinya adalah skema
 * DB hidup - sumber yang sama dengan `model:update`. Tanpa koneksi, hasilnya
 * kosong dan analisis berjalan seperti sebelumnya.
 *
 * @internal
 */
final class CQC_Phpstan_Service_Property_DatabaseSchemaHelper {
    /**
     * @var array<string, null|CQC_Phpstan_Service_Property_SchemaTable>
     */
    private $tables = [];

    /**
     * @var null|bool
     */
    private $available;

    /**
     * @param CModel $model
     *
     * @return null|CQC_Phpstan_Service_Property_SchemaTable
     */
    public function table(CModel $model) {
        if ($this->available === false) {
            return null;
        }

        $tableName = $model->getTable();
        $key = (string) $model->getConnectionName() . '.' . $tableName;

        if (array_key_exists($key, $this->tables)) {
            return $this->tables[$key];
        }

        try {
            $columns = $model->getConnection()->getSchemaManager()->listTableColumns($tableName);
        } catch (Throwable $e) {
            //tabel yang tidak ada hanya mengosongkan tabel itu; koneksi yang
            //tidak bisa dibuka sama sekali mematikan sumber ini untuk sisa analisis
            if ($this->available === null) {
                $this->available = false;

                return null;
            }

            return $this->tables[$key] = null;
        }

        $this->available = true;

        if (empty($columns)) {
            return $this->tables[$key] = null;
        }

        $table = new CQC_Phpstan_Service_Property_SchemaTable($tableName);
        foreach ($columns as $name => $column) {
            /** @var CDatabase_Schema_Column $column */
            $table->setColumn(new CQC_Phpstan_Service_Property_SchemaColumn(
                trim((string) $name, '`'),
                $column->getType()->getName(),
                !$column->getNotnull()
            ));
        }

        return $this->tables[$key] = $table;
    }

    /**
     * @param CModel $model
     * @param string $columnName
     *
     * @return null|CQC_Phpstan_Service_Property_SchemaColumn
     */
    public function column(CModel $model, $columnName) {
        $table = $this->table($model);
        if ($table === null) {
            return null;
        }

        return isset($table->columns[$columnName]) ? $table->columns[$columnName] : null;
    }
}
