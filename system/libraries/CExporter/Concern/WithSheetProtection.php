<?php

interface CExporter_Concern_WithSheetProtection {
    /**
     * Opsi proteksi sheet, kunci mengikuti setter PhpSpreadsheet Protection tanpa awalan "set", mis.
     * ['password' => 'rahasia', 'sort' => true, 'formatCells' => true]; nilai true berarti aksi itu dikunci.
     * Sheet otomatis diproteksi; array kosong berarti proteksi tanpa kata sandi.
     *
     * @return array
     */
    public function sheetProtection();
}
