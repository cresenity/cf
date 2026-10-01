<?php

interface CExporter_Concern_WithPrintArea {
    /**
     * Rentang yang dicetak, mis. 'A1:F50'.
     *
     * @return string
     */
    public function printArea();
}
