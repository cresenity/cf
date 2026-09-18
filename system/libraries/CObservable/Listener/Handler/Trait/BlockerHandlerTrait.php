<?php

/**
 * Description of BlockerHandlerTrait
 */
trait CObservable_Listener_Handler_Trait_BlockerHandlerTrait {
    /**
     * @var string
     */
    protected $blocker;

    /**
     * @var string
     */
    protected $blockerType = 'default';

    public function setBlocker($blocker) {
        $this->blocker = $blocker;

        return $this;
    }

    public function getBlockerHtml() {
        $html = $this->blocker;
        return $html;
    }

    public function setBlockerType($blockerType) {
        $this->blockerType = $blockerType;
        return $this;
    }

    public function getBlockerType() {
        return $this->blockerType;
    }
}
