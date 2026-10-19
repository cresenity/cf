<?php

use Illuminate\Contracts\Support\Arrayable;

class CExporter_Validator_Failure implements Arrayable, JsonSerializable {
    /**
     * @var int
     */
    protected $row;

    /**
     * @var string
     */
    protected $attribute;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var array
     */
    private $values;

    /**
     * @param int    $row
     * @param string $attribute
     */
    public function __construct($row, $attribute, array $errors, array $values = []) {
        $this->row = $row;
        $this->attribute = $attribute;
        $this->errors = $errors;
        $this->values = $values;
    }

    /**
     * @return int
     */
    public function row() {
        return $this->row;
    }

    /**
     * @return string
     */
    public function attribute() {
        return $this->attribute;
    }

    /**
     * @return array
     */
    public function errors() {
        return $this->errors;
    }

    /**
     * @return array
     */
    public function values() {
        return $this->values;
    }

    /**
     * @return array
     */
    public function toArray() {
        return c::collect($this->errors)->map(function ($message) {
            return c::__('There was an error on row :row. :message', ['row' => $this->row, 'message' => $message]);
        })->all();
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize() {
        return [
            'row' => $this->row(),
            'attribute' => $this->attribute(),
            'errors' => $this->errors(),
            'values' => $this->values(),
        ];
    }
}
