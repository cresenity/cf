<?php

class CElement_Action_Link extends CElement_Element_A implements CElement_Contract_ActionableInterface {
    /**
     * @param string|null $id
     *
     * @return static
     */
    public static function factory($id = null) {
        // @phpstan-ignore-next-line
        return new static($id);
    }

    /**
     * @param string $id
     *
     * @return void
     */
    public function __construct($id = '') {
        parent::__construct($id);
        $this->classes = [
            'btn',
            'btn-link'
        ];
    }

    /**
     * @return void
     */
    protected function build() {
        parent::build();
    }
}
