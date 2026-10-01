<?php

use PhpOffice\PhpSpreadsheet\Style\Color;

interface CExporter_Concern_WithTabColor {
    /**
     * @return string|Color warna ARGB (mis. 'FF1A347B') atau Color
     */
    public function tabColor();
}
