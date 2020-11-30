<?php

/**
 * Description of EngineAbstract
 *
 * @author Hery
 */
abstract class CView_EngineAbstract {

    /**
     * The view that was last to be rendered.
     *
     * @var string
     */
    protected $lastRendered;

    /**
     * Get the last view that was rendered.
     *
     * @return string
     */
    public function getLastRendered() {
        return $this->lastRendered;
    }

}
