<?php

/**
 * Description of EngineAbstract
 */
abstract class CView_EngineAbstract implements CView_EngineInterface {
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
