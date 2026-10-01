<?php
use CModel_Console_PropertiesHelper as Helper;

class CConsole_Command_Model_ModelUpdateCommand extends CConsole_Command_AppCommand {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model:update {table? : Nama tabel} {--all : Perbarui semua model aplikasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update model properties';

    /**
     * @var string
     */
    private $currentTable;

    /**
     * @var string
     */
    private $currentModel;

    public function handle() {
        if ($this->option('all')) {
            return $this->updateAll();
        }

        $table = (string) $this->argument('table');
        if ($table === '') {
            $this->error('Isi nama tabel, atau pakai --all untuk semua model.');

            return CConsole::FAILURE_EXIT;
        }

        $table = Helper::getTable($table);

        return $this->updateModel(Helper::getModel($table), $table);
    }

    /**
     * Perbarui semua model aplikasi; nama tabel dibaca dari model, bukan ditebak dari nama berkas.
     *
     * @return int
     */
    private function updateAll() {
        $modelPath = c::fixPath(CF::appDir()) . 'default' . DS . 'libraries' . DS . $this->prefix . 'Model' . DS;
        $files = CFile::isDirectory($modelPath) ? glob($modelPath . '*' . EXT) : [];
        sort($files);

        $updated = 0;
        $skipped = [];
        $failed = [];
        foreach ($files as $file) {
            $model = basename($file, EXT);
            $class = $this->prefix . 'Model_' . $model;
            try {
                if (!class_exists($class) || !(new ReflectionClass($class))->isInstantiable()) {
                    $skipped[] = $model;

                    continue;
                }
                $table = (new $class())->getTable();
                if ($this->updateModel($model, $table) === CConsole::FAILURE_EXIT) {
                    $failed[] = $model;

                    continue;
                }
                $updated++;
            } catch (Throwable $e) {
                $failed[] = $model . ' (' . $e->getMessage() . ')';
            }
        }

        $this->info($updated . ' model diperbarui, ' . count($skipped) . ' dilewati, ' . count($failed) . ' gagal.');
        foreach ($skipped as $model) {
            $this->line('dilewati: ' . $model);
        }
        foreach ($failed as $model) {
            $this->error('gagal: ' . $model);
        }

        return count($failed) > 0 ? CConsole::FAILURE_EXIT : 0;
    }

    /**
     * @param string $model nama model tanpa prefiks, mis. ItemCart
     * @param string $table
     *
     * @return int
     */
    private function updateModel($model, $table) {
        $this->currentModel = $model;
        $this->currentTable = $table;
        $this->info('Updating ' . $model . ' model...');

        $modelPath = c::fixPath(CF::appDir()) . 'default' . DS . 'libraries' . DS . $this->prefix . 'Model' . DS;
        if (!CFile::isDirectory($modelPath)) {
            CFile::makeDirectory($modelPath);
        }

        $modelFile = $modelPath . $model . EXT;

        if (!file_exists($modelFile)) {
            $this->warn('Model ' . $model . ' is not exist, please create it first using "make:model" command');

            return CConsole::FAILURE_EXIT;
        }

        $content = CFile::get($modelFile);
        $updated = Helper::applyPropertiesToDocblock($content, $this->getUpdatedProperties());
        if ($updated === null) {
            $this->warn('Model ' . $model . ' tidak punya deklarasi class pada ' . $modelFile);

            return CConsole::FAILURE_EXIT;
        }

        if ($updated !== $content) {
            CFile::put($modelFile, $updated);
        }

        $this->info($model . 'Model updated on ' . $modelFile);

        return 0;
    }

    private function getTable() {
        return $this->currentTable;
    }

    private function getCurrentProperties() {
        $modelPath = c::fixPath(CF::appDir()) . 'default' . DS . 'libraries' . DS . $this->prefix . 'Model' . DS;
        $modelFile = $modelPath . $this->currentModel . EXT;
        $content = CFile::get($modelFile);
        $result = [];
        foreach (Helper::classDocblockPropertyLines($content) as $line) {
            list($tag, $type, $var, $desc) = Helper::parsePropertyLine($line);
            $result[] = [
                'prop' => $tag,
                'type' => $type,
                'var' => $var,
                'field' => str_replace('$', '', $var),
                'desc' => $desc,
            ];
        }

        return $result;
    }

    /**
     * @param array $properties
     *
     * @return array
     */
    public function updateFieldProperties($properties) {
        $fields = Helper::getFields($this->getTable(), $this->prefix, $this->currentModel);
        $currentPropertyFields = array_column($properties, 'field');
        foreach ($fields as $field => $fieldProperty) {
            $type = Helper::getGenericTypeForFieldProperty($fieldProperty);
            $i = array_search($field, $currentPropertyFields);
            if ($i === false) {
                $properties[] = [
                    'prop' => $this->getTable() . '_id' === $field ? '@property-read' : '@property',
                    'type' => $type,
                    'var' => '$' . $field,
                    'field' => $field,
                    'desc' => '',
                ];
            } else {
                $prop = carr::get($properties, $i);
                $propType = c::get($prop, 'type');

                if ($propType !== $type) {
                    $properties[$i]['type'] = $type;
                }
            }
        }
        while (true) {
            $missingIndex = $this->getMissingPropertyIndex($properties);
            if ($missingIndex !== false) {
                unset($properties[$missingIndex]);
            } else {
                break;
            }
        }

        return $properties;
    }

    /**
     * @param array $properties
     *
     * @return int|false
     */
    private function getMissingPropertyIndex($properties) {
        $fieldsKey = c::collect(Helper::getFields($this->getTable(), $this->prefix, $this->currentModel))->keys()->toArray();
        $classMethods = get_class_methods($this->prefix . 'Model_' . $this->currentModel);
        foreach ($properties as $index => $property) {
            $field = carr::get($property, 'field');
            $i = array_search($field, $fieldsKey);
            if ($i === false && !in_array($field, $classMethods)) {
                if (!cstr::endsWith($field, '_count')) {
                    return $index;
                }
            }
        }

        return false;
    }

    /**
     * @param array $properties
     *
     * @return array
     */
    public function updateFieldRelation($properties) {
        $compared = [];

        $methods = Helper::getRelationMethods($this->prefix . 'Model_' . $this->currentModel);
        $fields = carr::pluck($methods, 'method');
        $currentPropertyFields = array_column($properties, 'field');

        foreach ($methods as $methodIndex => $method) {
            $field = carr::get($method, 'method');
            $i = array_search($field, $currentPropertyFields);
            if ($i === false) {
                $properties[] = [
                    'prop' => '@property-read',
                    'type' => carr::get($method, 'type'),
                    'var' => '$' . $field,
                    'field' => $field,
                    'isRelation' => true,
                    'desc' => '',
                ];
            } else {
                $prop = carr::get($properties, $i);
                $propType = c::get($prop, 'type');

                if ($propType !== carr::get($method, 'type')) {
                    $properties[$i]['type'] = carr::get($method, 'type');
                }
            }
        }

        return $properties;
    }

    public function getUpdatedProperties() {
        $properties = [];

        $currentProperties = Helper::uniqueByVariable($this->getCurrentProperties());
        $currentProperties = $this->updateFieldProperties($currentProperties);
        $currentProperties = $this->updateFieldRelation($currentProperties);
        //`CModel_Collection|__FUNCTION__[]` adalah sisa generator lama untuk morphTo; tipe yang benar model atau null
        foreach ($currentProperties as $index => $property) {
            if (strpos((string) carr::get($property, 'type'), '__FUNCTION__') !== false) {
                $currentProperties[$index]['type'] = 'null|CModel';
            }
        }

        $propLength = 0;
        $typeLength = 0;
        $varLength = 0;

        foreach ($currentProperties as $property) {
            if ($propLength < strlen(carr::get($property, 'prop'))) {
                $propLength = strlen(carr::get($property, 'prop'));
            }
            if ($typeLength < strlen(carr::get($property, 'type'))) {
                $typeLength = strlen(carr::get($property, 'type'));
            }
            if ($varLength < strlen(carr::get($property, 'var'))) {
                $varLength = strlen(carr::get($property, 'var'));
            }
        }
        foreach ($currentProperties as $property) {
            $prop = ' * ';
            $prop .= cstr::padRight(carr::get($property, 'prop'), $propLength);
            $prop .= ' ' . cstr::padRight(carr::get($property, 'type'), $typeLength);
            if (carr::get($property, 'desc')) {
                $prop .= ' ' . cstr::padRight(carr::get($property, 'var'), $varLength);
                $prop .= ' ' . carr::get($property, 'desc');
            } else {
                $prop .= ' ' . carr::get($property, 'var');
            }
            $properties[] = $prop;
        }

        $result = implode("\n", $properties);

        return $result;
    }
}
