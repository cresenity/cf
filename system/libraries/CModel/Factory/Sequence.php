<?php

class CModel_Factory_Sequence implements Countable {
    /**
     * The count of the sequence items.
     *
     * @var int
     */
    public $count;

    /**
     * The current index of the sequence iteration.
     *
     * @var int
     */
    public $index = 0;

    /**
     * The sequence of return values.
     *
     * @var array
     */
    protected $sequence;

    /**
     * Create a new sequence instance.
     *
     * @param mixed ...$sequence
     *
     * @return void
     */
    public function __construct(...$sequence) {
        $this->sequence = $sequence;
        $this->count = count($sequence);
    }

    /**
     * Get the current count of the sequence items.
     *
     * @return int
     */
    public function count(): int {
        return $this->count;
    }

    /**
     * Get the next value in the sequence.
     *
     * @return mixed
     */
    public function __invoke() {
        return c::tap(c::value($this->sequence[$this->index % $this->count], $this), function () {
            $this->index = $this->index + 1;
        });
    }
}
