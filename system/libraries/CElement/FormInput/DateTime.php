<?php

defined('SYSPATH') or die('No direct access allowed.');

class CElement_FormInput_DateTime extends CElement_FormInput {
    /**
     * Date/time format string used by the underlying picker plugin, set by
     * concrete subclasses (eg. moment.js-style `YYYY-MM-DD`).
     *
     * @var null|string
     */
    protected $dateTimeFormat;

    /**
     * Normalizes a `DateTimeInterface` value to a plain string before storing it.
     *
     * @param mixed $val
     *
     * @return $this
     */
    public function setValue($val) {
        if ($val instanceof DateTimeInterface) {
            $val = $val->format('Y-m-d H:i:s');
        }

        return parent::setValue($val);
    }
}
