<?php

/**
 * Description of CQC_QCAbstract
 */
abstract class CQC_QCAbstract {

    /**
     * @var string
     */
    private $name = null;

    public function getName() {
        if ($this->name == null) {
            return carr::last(explode('_', get_called_class()));
        }
        return $this->name;
    }

    public function toString() {
        return $this->getName();
    }

}
