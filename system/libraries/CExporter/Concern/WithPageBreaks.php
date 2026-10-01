<?php

interface CExporter_Concern_WithPageBreaks {
    /**
     * Sel tempat pemisah halaman baris ditaruh, mis. ['A20', 'A40'].
     *
     * @return string[]
     */
    public function pageBreaks();
}
