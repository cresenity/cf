<?php

interface CExporter_Concern_WithFreezePane {
    /**
     * Sel pertama yang ikut bergulir, mis. 'A2' membekukan baris pertama.
     *
     * @return string
     */
    public function freezePane();
}
