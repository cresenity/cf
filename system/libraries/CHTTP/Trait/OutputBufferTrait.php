<?php

/**
 * Description of OutputBufferTrait.
 */
trait CHTTP_Trait_OutputBufferTrait {
    /**
     * Turns on output buffering.
     *
     * @return bool
     */
    public function startOutputBuffering() {
        return ob_start();
    }

    /**
     * @return string|false
     */
    public function cleanOutputBuffer() {
        return ob_get_clean();
    }

    /**
     * @return int
     */
    public function getOutputBufferLevel() {
        return ob_get_level();
    }

    /**
     * @return bool
     */
    public function endOutputBuffering() {
        return ob_end_clean();
    }

    /**
     * @return void
     */
    public function flushOutputBuffer() {
        flush();
    }
}
