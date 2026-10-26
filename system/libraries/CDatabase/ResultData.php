<?php

class CDatabase_ResultData implements ArrayAccess, Iterator, Countable {
    /**
     * @var PDOStatement
     */
    protected $data;

    protected $currentRow = 0;

    protected $totalRows = 0;

    protected $fetchType = PDO::FETCH_OBJ;

    protected $rows = [
        PDO::FETCH_OBJ => [],
        PDO::FETCH_ASSOC => [],
    ];

    /**
     * Baris SELECT yang di-fetch di depan (array asosiatif) untuk driver selain MySQL.
     *
     * @var null|array
     */
    protected $buffered = null;

    /**
     * @param bool $bufferRows fetch SELECT rows up front: rowCount() of a SELECT is only
     *                         reliable on MySQL (SQLite gives the last INSERT/UPDATE count)
     */
    public function __construct(PDOStatement $data, $bufferRows = false) {
        $this->data = $data;

        $this->currentRow = 0;
        if ($bufferRows && $data->columnCount() > 0) {
            $this->buffered = $data->fetchAll(PDO::FETCH_ASSOC);
            $this->totalRows = count($this->buffered);
        } else {
            $this->totalRows = $data->rowCount();
        }
    }

    /**
     * @param int $offset
     * @param int $fetch
     *
     * @return mixed
     */
    protected function bufferedRow($offset, $fetch) {
        $row = $this->buffered[$offset];
        if ($fetch == PDO::FETCH_ASSOC) {
            return $row;
        }
        if ($fetch == PDO::FETCH_NUM) {
            return array_values($row);
        }

        return (object) $row;
    }

    public function resultArray($object = null, $type = 'stdClass') {
        if (is_string($object)) {
            $fetch = $object;
        } elseif (is_bool($object)) {
            if ($object === true) {
                $fetch = PDO::FETCH_OBJ;

                // NOTE - The class set by $type must be defined before fetching the result,
                // autoloading is disabled to save a lot of stupid overhead.
                $type = (is_string($type) and CF::autoLoad($type)) ? $type : 'stdClass';
            } else {
                $fetch = PDO::FETCH_ASSOC;
            }
        } else {
            // Use the default config values
            $fetch = $this->fetchType;

            if ($fetch == PDO::FETCH_CLASS) {
                $type = (is_string($type) and CF::autoLoad($type)) ? $type : 'stdClass';
            }
        }

        if ($this->totalRows && $this->buffered !== null) {
            $all = [];
            foreach (array_keys($this->buffered) as $offset) {
                $all[] = $this->bufferedRow($offset, $fetch);
            }

            return $all;
        }

        if ($this->totalRows) {
            // Reset the pointer location to make sure things work properly
            $this->seek(0);
            if ($fetch == PDO::FETCH_CLASS) {
                $this->data->fetchAll($fetch, $type);
            }

            $all = $this->data->fetchAll($fetch);

            return $all;
        }

        return [];
    }

    public function result($object = true) {
        $this->fetchType = $object ? PDO::FETCH_OBJ : PDO::FETCH_ASSOC;

        return $this;
    }

    #[\ReturnTypeWillChange]
    public function count() {
        return $this->totalRows;
    }

    /**
     * ArrayAccess: offsetExists.
     *
     * @param mixed $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) {
        if ($offset >= 0 && $this->totalRows > 0) {
            return $offset < $this->totalRows;
        }

        return false;
    }

    /**
     * ArrayAccess: offsetGet.
     *
     * @param mixed $offset
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        if (!$this->seek($offset)) {
            return false;
        }
        if (isset($this->rows[$this->fetchType][$offset])) {
            return $this->rows[$this->fetchType][$offset];
        }
        if ($this->buffered !== null) {
            return $this->rows[$this->fetchType][$offset] = $this->bufferedRow($offset, $this->fetchType);
        }
        // Return the row by calling the defined fetching callback
        $this->rows[$this->fetchType][$offset] = $this->data->fetch($this->fetchType, PDO::FETCH_ORI_ABS, $offset);

        return $this->rows[$this->fetchType][$offset];
        //return carr::get($this->data, $offset);
    }

    #[\ReturnTypeWillChange]
    public function seek($offset) {
        if ($this->offsetExists($offset)) {
            $this->currentRow = $offset;

            return true;
        }

        return false;
    }

    /**
     * ArrayAccess: offsetSet.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @throws CDatabase_Exception
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) {
        throw new CDatabase_Exception('Query results are read only');
    }

    /**
     * ArrayAccess: offsetUnset.
     *
     * @param mixed $offset
     *
     * @throws CDatabase_Exception
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) {
        throw new CDatabase_Exception('Query results are read only');
    }

    /**
     * Iterator: current.
     */
    #[\ReturnTypeWillChange]
    public function current() {
        return $this->offsetGet($this->currentRow);
    }

    /**
     * Iterator: key.
     */
    #[\ReturnTypeWillChange]
    public function key() {
        return $this->currentRow;
    }

    /**
     * Iterator: next.
     */
    #[\ReturnTypeWillChange]
    public function next() {
        ++$this->currentRow;

        return $this;
    }

    /**
     * Iterator: prev.
     */
    public function prev() {
        --$this->currentRow;

        return $this;
    }

    /**
     * Iterator: rewind.
     */
    #[\ReturnTypeWillChange]
    public function rewind() {
        $this->currentRow = 0;

        return $this;
    }

    /**
     * Iterator: valid.
     */
    #[\ReturnTypeWillChange]
    public function valid() {
        return $this->offsetExists($this->currentRow);
    }

    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null) {
        return $this->resultArray(false);
    }

    public function listFields() {
        $field_names = [];
        cdbg::dd($this->data->fetchColumn());
        while ($field = $this->data->fetchColumn()) {
            $field_names[] = $field->name;
        }

        return $field_names;
    }
}
