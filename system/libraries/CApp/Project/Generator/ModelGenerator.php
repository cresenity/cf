<?php

defined('SYSPATH') or die('No direct access allowed.');

class CApp_Project_Generator_ModelGenerator extends CApp_Project_AbstractGenerator {
    /**
     * @var string
     */
    public $fieldsFillable;

    /**
     * @var string
     */
    public $fieldsHidden;

    /**
     * @var string
     */
    public $fieldsCast;

    /**
     * @var string
     */
    public $fieldsDate;

    /**
     * @var CCollection
     */
    public $columns;

    /**
     * CApp_Project_Generator_ModelGenerator constructor.
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function generate($options = []) {
        if (!is_array($options)) {
            $options = [];
        }
        $this->mergeOptions($options);
        $this->fixOptions();
        $table = $this->option('table');
        $prefix = $this->option('prefix');
        if (strlen($table) == 0) {
            throw new Exception('option table is required for model generator');
        }
        // generate the file name for the model based on the table name
        $filename = cstr::studly($table);
        $db = c::db($this->option('database'));
        $schemaManager = $db->getSchemaManager();
        $columns = $schemaManager->listTableColumns($table);
        $columnNames = array_keys($columns);
        $result = '';

        // gather information on it
        $model = [
            'table' => $table,
            'fillable' => $columnNames,
            'guardable' => [],
            'hidden' => [],
            'casts' => [],
        ];

        $descQuery = 'describe ' . $table;
        $resultDesc = $db->select($descQuery);
        $this->columns = new CCollection();
        foreach ($resultDesc as $col) {
            $this->columns->push([
                'field' => $col->Field,
                'type' => $col->Type,
            ]);
        }
        $stub = file_get_contents($this->getStub());

        //reset fields
        $this->resetFields();

        // replace the class name
        $stub = $this->replaceClassName($stub, $prefix, $table);
        // replace the fillable
        $stub = $this->replaceModuleInformation($stub, $model);

        return $stub;
    }

    /**
     * Replaces the class name in the stub.
     *
     * @param string $stub      stub content
     * @param string $tableName the name of the table to make as the class
     * @param mixed  $prefix
     *
     * @return string stub content
     */
    public function replaceClassName($stub, $prefix, $tableName) {
        $stub = str_replace('{{class}}', cstr::studly($tableName), $stub);
        $stub = str_replace('{{prefix}}', cstr::upper($prefix), $stub);
        $stub = str_replace('{{datetime}}', date('F,d Y h:i:s A'), $stub);

        return $stub;
    }

    /**
     * Replaces the module information.
     *
     * @param string $stub             stub content
     * @param array  $modelInformation array (key => value)
     *
     * @return string stub content
     */
    public function replaceModuleInformation($stub, $modelInformation) {
        // replace table
        $stub = str_replace('{{table}}', $modelInformation['table'], $stub);
        // replace fillable
        $this->fieldsHidden = '';
        $this->fieldsFillable = '';
        $this->fieldsCast = '';
        foreach ($modelInformation['fillable'] as $field) {
            // fillable and hidden
            if ($field != $modelInformation['table'] . '_id') {
                $this->fieldsFillable .= (strlen($this->fieldsFillable) > 0 ? ', ' : '') . "'{$field}'";
                $fieldsFiltered = $this->columns->where('field', $field);
                if ($fieldsFiltered) {
                    // check type
                    switch (strtolower($fieldsFiltered->first()['type'])) {
                        case 'timestamp':
                            $this->fieldsDate .= (strlen($this->fieldsDate) > 0 ? ', ' : '') . "'{$field}'";

                            break;
                        case 'datetime':
                            $this->fieldsDate .= (strlen($this->fieldsDate) > 0 ? ', ' : '') . "'{$field}'";

                            break;
                        case 'date':
                            $this->fieldsDate .= (strlen($this->fieldsDate) > 0 ? ', ' : '') . "'{$field}'";

                            break;
                        case 'tinyint(1)':
                            $this->fieldsCast .= (strlen($this->fieldsCast) > 0 ? ', ' : '') . "'{$field}' => 'boolean'";

                            break;
                    }
                }
            } else {
                if ($field != $modelInformation['table'] . '_id' && $field != 'created' && $field != 'updated') {
                    $this->fieldsHidden .= (strlen($this->fieldsHidden) > 0 ? ', ' : '') . "'{$field}'";
                }
            }
        }
        // replace in stub
        $stub = str_replace('{{fillable}}', $this->fieldsFillable, $stub);
        $stub = str_replace('{{hidden}}', $this->fieldsHidden, $stub);
        $stub = str_replace('{{casts}}', $this->fieldsCast, $stub);
        $stub = str_replace('{{dates}}', $this->fieldsDate, $stub);

        return $stub;
    }

    /**
     * Returns the stub to use to generate the class.
     *
     * @return string
     */
    public function getStub() {
        return SYSPATH . 'data/stub/generator/model.stub';
    }

    /**
     * Returns all the options that the user specified.
     *
     * @return void
     */
    protected function fixOptions() {
        // debug
        $this->options['debug'] = ($this->option('debug')) ? true : false;
        // database
        $this->options['database'] = ($this->option('database')) ? $this->option('database') : 'default';
    }

    /**
     * Reset all variables to be filled again when using multiple.
     *
     * @return void
     */
    public function resetFields() {
        $this->fieldsFillable = '';
        $this->fieldsHidden = '';
        $this->fieldsCast = '';
        $this->fieldsDate = '';
    }
}
