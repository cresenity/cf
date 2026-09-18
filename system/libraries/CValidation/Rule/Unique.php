<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Apr 12, 2019, 8:02:34 PM
 */
class CValidation_Rule_Unique {
    use CValidation_Rule_Trait_DatabaseTrait;

    /**
     * The ID that should be ignored.
     *
     * @var mixed
     */
    protected $ignore;

    /**
     * The name of the ID column.
     *
     * @var string
     */
    protected $idColumn = 'id';

    /**
     * Ignore the given ID during the unique check.
     *
     * @param mixed       $id
     * @param null|string $idColumn
     *
     * @return $this
     */
    public function ignore($id, $idColumn = null) {
        if ($id instanceof CModel) {
            return $this->ignoreModel($id, $idColumn);
        }

        $this->ignore = $id;
        $this->idColumn = $idColumn == null ? 'id' : $idColumn;

        return $this;
    }

    /**
     * Ignore the given model during the unique check.
     *
     * @param \CModel     $model
     * @param null|string $idColumn
     *
     * @return $this
     */
    public function ignoreModel($model, $idColumn = null) {
        $this->idColumn = $idColumn == null ? $model->getKeyName() : $idColumn;
        $this->ignore = $model->{$this->idColumn};

        return $this;
    }

    /**
     * Convert the rule to a validation string.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString() {
        return rtrim(sprintf(
            'unique:%s,%s,%s,%s,%s',
            $this->table,
            $this->column,
            $this->ignore ? '"' . $this->ignore . '"' : 'NULL',
            $this->idColumn,
            $this->formatWheres()
        ), ',');
    }

    public function __sleep() {
        $this->using = c::collect($this->using)->map(function ($item) {
            return c::toSerializableClosure($item);
        })->all();

        return array_keys(get_object_vars($this));
    }

    public function __wakeup() {
        $this->using = c::collect($this->using)->map(function ($item) {
            return c::toCallable($item);
        })->all();
    }
}
