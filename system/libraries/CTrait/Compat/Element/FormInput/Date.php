<?php

defined('SYSPATH') or die('No direct access allowed.');

//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_FormInput_Date {
    /**
     * @param string $placeholder
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_placeholder($placeholder) {
        /** @var CElement_FormInput_Date $this */
        return $this->setPlaceholder($placeholder);
    }

    /**
     * @param string $placeholder
     * @param mixed  $str
     *
     * @return $this
     *
     * @deprecated since version 1.2
     */
    public function set_start_date($str) {
        /** @var CElement_FormInput_Date $this */
        return $this->setStartDate($str);
    }

    /**
     * @param mixed $boolean
     *
     * @deprecated since version 1.2
     */
    public function set_have_button($boolean) {
        /** @var CElement_FormInput_Date $this */
        $this->have_button = $boolean;

        return $this;
    }

    /**
     * @param mixed $str
     *
     * @deprecated since version 1.2
     */
    public function set_end_date($str) {
        /** @var CElement_FormInput_Date $this */
        return $this->setEndDate($str);
    }

    /**
     * @param mixed $day
     *
     * @deprecated since version 1.2
     */
    public function add_disable_day($day) {
        /** @var CElement_FormInput_Date $this */
        return $this->addDisableDaysOfWeek($day);
    }
}
//@codingStandardsIgnoreEnd
