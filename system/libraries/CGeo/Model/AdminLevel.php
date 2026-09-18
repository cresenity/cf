<?php
final class CGeo_Model_AdminLevel {
    /**
     * @var int
     */
    private $level;

    /**
     * @var string
     */
    private $name;

    /**
     * @var null|string
     */
    private $code;

    /**
     * @param int         $level
     * @param string      $name
     * @param null|string $code
     */
    public function __construct($level, $name, $code = null) {
        $this->level = $level;
        $this->name = $name;
        $this->code = $code;
    }

    /**
     * Returns the administrative level.
     *
     * @return int Level number [1,5]
     */
    public function getLevel() {
        return $this->level;
    }

    /**
     * Returns the administrative level name.
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Returns the administrative level short name.
     *
     * @return null|string
     */
    public function getCode() {
        return $this->code;
    }

    /**
     * Returns a string with the administrative level name.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString() {
        return $this->getName();
    }
}
