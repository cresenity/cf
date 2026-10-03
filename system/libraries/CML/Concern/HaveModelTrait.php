<?php

trait CML_Concern_HaveModelTrait {
    protected $modelFile;

    protected $modelPath;

    /**
     * Nama disk CF (default/config/storage.php) tempat model disimpan/dibaca, mis. 's3'.
     * Null (bawaan) berarti filesystem lokal lewat Rubix\ML\Persisters\Filesystem, seperti
     * sebelum ini ada - lihat CML_Adapter_RubixAdapter.
     *
     * @var null|string
     */
    protected $disk;

    /**
     * @param string      $filename
     * @param null|string $path
     *
     * @return static
     */
    public function setModelFile($filename, $path = null) {
        if ($path != null) {
            $this->modelPath = $path;
        }
        $this->modelFile = $filename;

        return $this;
    }

    public function getModelPath() {
        return $this->modelPath;
    }

    public function getModelFile() {
        return $this->modelFile;
    }

    /**
     * Pindah penyimpanan model dari filesystem lokal ke disk CF terdaftar (mis. 's3') - lihat
     * CML_Persister_DiskPersister. Modelnya tetap dibaca lewat getModelPath()+getModelFile()
     * (lihat CML_Trainer/CML_Predictor), tapi path itu sekarang key relatif di disk, BUKAN path
     * lokal - pasangkan dengan setModelFile($filename, $path) memakai prefix yang masuk akal
     * untuk disk itu, jangan andalkan modelPath bawaan (DOCROOT.'temp/ml/...', cuma relevan
     * untuk filesystem lokal).
     *
     * @param null|string $disk nama disk terdaftar, atau null untuk filesystem lokal (bawaan)
     *
     * @return static
     */
    public function setDisk($disk) {
        $this->disk = $disk;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getDisk() {
        return $this->disk;
    }
}
