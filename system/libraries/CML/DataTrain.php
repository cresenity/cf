<?php

class CML_DataTrain {
    use CML_Concern_HaveModelTrait;
    use CML_Concern_HaveDataTrait;
    use CML_Concern_HaveEstimatorTrait;

    const ESTIMATOR_TYPE_CLUSTERER = 'clusterer';

    protected $dataIndexWithLabel;

    /**
     * @var array
     */
    protected $transformers = null;

    /**
     * Bagian data yang dipakai untuk melatih (sisanya untuk evaluasi/error analysis) - hanya
     * dipakai untuk classifier/regressor (lihat CML_Adapter_RubixAdapter::train()); clusterer/
     * anomaly detector mengabaikannya karena tidak melakukan train/test split sama sekali.
     * 0.8 (80/20) bawaan - trainPartSize=1 membuat data uji selalu kosong, dan getErrorAnalysis()
     * atas dataset kosong selalu melempar exception.
     *
     * @var float
     */
    protected $trainPartSize = 0.8;

    public function __construct($data) {
        $this->data = $data;
        $this->modelPath = CF::config('ml.ai_model_path_output');
    }

    public function setDataIndexWithLabel($dataIndexWithLabel) {
        $this->dataIndexWithLabel = $dataIndexWithLabel;

        return $this;
    }

    public function setIgnoredAttributes($attributes) {
        $this->ignoredAttributes = $attributes;

        return $this;
    }

    public function getDataIndexWithLabel() {
        return $this->dataIndexWithLabel;
    }

    public function getTransformers() {
        return $this->transformers;
    }

    /**
     * Timpa pipeline transformer otomatis CML_Adapter_RubixAdapter::trainWithoutTest() (yang
     * selalu menambahkan OneHotEncoder begitu ada kolom kategorikal - cocok untuk estimator
     * berbasis jarak/kontinu seperti KMeans/KNN, tapi JUSTRU merusak estimator kategorikal
     * native seperti NaiveBayes karena OneHotEncoder mengubah semua kolom jadi kontinu). Kirim
     * array kosong untuk melewati transformasi apa pun, biarkan data mentah masuk ke estimator.
     *
     * @param array $transformers
     *
     * @return static
     */
    public function setTransformers($transformers) {
        $this->transformers = $transformers;

        return $this;
    }

    /**
     * @param float $trainPartSize lihat docblock properti $trainPartSize
     *
     * @return static
     */
    public function setTrainPartSize($trainPartSize) {
        $this->trainPartSize = $trainPartSize;

        return $this;
    }

    /**
     * @return float
     */
    public function getTrainPartSize() {
        return $this->trainPartSize;
    }

    public function getDataPredict($data = null) {
        return (new CML_DataPredict($data ?: $this->getData()))
            ->setModelFile($this->modelFile, $this->modelPath)
            ->setEstimator($this->estimator, $this->estimatorParameters);
    }
}
