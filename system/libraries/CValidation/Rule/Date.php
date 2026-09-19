<?php

/**
 * Fluent builder for a date rule string, e.g. `date|after:today`.
 */
class CValidation_Rule_Date {
    use CTrait_Conditionable;
    use CTrait_Macroable;

    /**
     * @var null|string
     */
    protected $format = null;

    /**
     * @var array
     */
    protected $constraints = [];

    /**
     * @param string $format
     *
     * @return $this
     */
    public function format($format) {
        $this->format = $format;

        return $this;
    }

    /**
     * @return $this
     */
    public function beforeToday() {
        return $this->before('today');
    }

    /**
     * @return $this
     */
    public function afterToday() {
        return $this->after('today');
    }

    /**
     * @return $this
     */
    public function todayOrBefore() {
        return $this->beforeOrEqual('today');
    }

    /**
     * @return $this
     */
    public function todayOrAfter() {
        return $this->afterOrEqual('today');
    }

    /**
     * @return $this
     */
    public function past() {
        return $this->before('now');
    }

    /**
     * @return $this
     */
    public function future() {
        return $this->after('now');
    }

    /**
     * @return $this
     */
    public function nowOrPast() {
        return $this->beforeOrEqual('now');
    }

    /**
     * @return $this
     */
    public function nowOrFuture() {
        return $this->afterOrEqual('now');
    }

    /**
     * @param \DateTimeInterface|string $date
     *
     * @return $this
     */
    public function before($date) {
        return $this->addRule('before:' . $this->formatDate($date));
    }

    /**
     * @param \DateTimeInterface|string $date
     *
     * @return $this
     */
    public function after($date) {
        return $this->addRule('after:' . $this->formatDate($date));
    }

    /**
     * @param \DateTimeInterface|string $date
     *
     * @return $this
     */
    public function beforeOrEqual($date) {
        return $this->addRule('before_or_equal:' . $this->formatDate($date));
    }

    /**
     * @param \DateTimeInterface|string $date
     *
     * @return $this
     */
    public function afterOrEqual($date) {
        return $this->addRule('after_or_equal:' . $this->formatDate($date));
    }

    /**
     * @param \DateTimeInterface|string $from
     * @param \DateTimeInterface|string $to
     *
     * @return $this
     */
    public function between($from, $to) {
        return $this->after($from)->before($to);
    }

    /**
     * @param \DateTimeInterface|string $from
     * @param \DateTimeInterface|string $to
     *
     * @return $this
     */
    public function betweenOrEqual($from, $to) {
        return $this->afterOrEqual($from)->beforeOrEqual($to);
    }

    /**
     * @return string
     */
    public function __toString() {
        return implode('|', array_merge(
            [$this->format === null ? 'date' : 'date_format:' . $this->format],
            $this->constraints
        ));
    }

    /**
     * @param array|string $rules
     *
     * @return $this
     */
    protected function addRule($rules) {
        $this->constraints = array_merge($this->constraints, carr::wrap($rules));

        return $this;
    }

    /**
     * @param \DateTimeInterface|string $date
     *
     * @return string
     */
    protected function formatDate($date) {
        return $date instanceof DateTimeInterface
            ? $date->format($this->format ?? 'Y-m-d')
            : $date;
    }
}
