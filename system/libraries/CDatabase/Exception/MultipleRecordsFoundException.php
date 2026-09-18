<?php

class CDatabase_Exception_MultipleRecordsFoundException extends RuntimeException {
    /**
     * @var int
     */
    protected $count;

    /**
     * @param int            $count
     * @param int            $code
     * @param null|Throwable $previous
     */
    public function __construct($count = 0, $code = 0, ?Throwable $previous = null) {
        $this->count = (int) $count;

        parent::__construct($count ? "{$count} records were found." : 'Multiple records were found.', $code, $previous);
    }

    /**
     * Number of records found.
     *
     * @return int
     */
    public function getCount() {
        return $this->count;
    }
}
