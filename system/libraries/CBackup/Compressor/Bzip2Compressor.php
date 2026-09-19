<?php

class CBackup_Compressor_Bzip2Compressor extends CBackup_AbstractCompressor {
    /**
     * @return string
     */
    public function useCommand() {
        return 'bzip2';
    }

    /**
     * @return string
     */
    public function useExtension() {
        return 'bz2';
    }
}
