<?php

class CQC_Testing_AbstractCommand extends CConsole_Command {
    /**
     * Draw a line in console.
     *
     * @param int $len
     */
    public function drawLine($len = 80) {
        if (is_string($len)) {
            $len = strlen($len);
        }

        $this->line(str_repeat('-', max($len, 80)));
    }
}
