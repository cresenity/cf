<?php

class CExporter_Exportable_DataTable extends CExporter_Exportable implements CExporter_Concern_FromCollection, CExporter_Concern_WithHeadings, CExporter_Concern_WithMapping {
    protected $table;

    public function __construct(CElement_Component_DataTable $table) {
        $this->table = $table;
    }

    public function collection() {
        $this->table->setAjax(false);
        return $this->table->getCollection();
    }

    public function map($data) {
        $columns = $this->table->getColumns();
        $newRow = [];
        foreach ($columns as $column) {
            $value = carr::get($data, $column->getFieldname());
            foreach ($column->transforms as $trans) {
                if ($trans->getFunction() != 'format_currency') {
                    $value = $trans->execute($value);
                }
                // $col_v = $trans->execute($col_v);
            }
            if (strlen($column->format) > 0) {
                $tempValue = $column->format;
                foreach ($data as $k2 => $v2) {
                    if (strpos($tempValue, '{' . $k2 . '}') !== false) {
                        $tempValue = str_replace('{' . $k2 . '}', $v2, $tempValue);
                    }
                    $value = $tempValue;
                }
            }
            //if have callback
            $exportCallback = $column->determineExportCallback();
            if ($exportCallback != null) {
                $value = CFunction::factory($exportCallback)
                    ->addArg($data)
                    ->addArg($value)
                    ->setRequire($column->determineExportCallbackRequire())
                    ->execute();
                if (is_array($value) && isset($value['html']) && isset($value['js'])) {
                    $value = $value['html'];
                }
            }

            if (($this->table->cellCallbackFunc) != null) {
                $value = CFunction::factory($this->table->cellCallbackFunc)
                    ->addArg($this)
                    ->addArg($column->getFieldname())
                    ->addArg($data)
                    ->addArg($value)
                    ->setRequire($this->table->requires)
                    ->execute();
                if (is_array($value) && isset($value['html']) && isset($value['js'])) {
                    $value = $value['html'];
                }
            }
            $newRow[$column->getFieldname()] = $value;
        }
        return $newRow;
    }

    public function headings() {
        $columns = $this->table->getColumns();
        $heading = [];
        foreach ($columns as $column) {
            $heading[] = $column->determineExportLabel();
        }
        return $heading;
    }
}
