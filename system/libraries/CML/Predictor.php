<?php

class CML_Predictor {
    public function predict(CML_DataPredict $dataPredict) {
        $data = $dataPredict->getData();

        $adapter = CML_Manager::instance()->createRubixAdapter();
        $modelRepository = CML_Manager::instance()->getModelRepository($dataPredict->getModelPath());
        $path = $modelRepository->file($dataPredict->getModelFile());
        $disk = $dataPredict->getDisk();
        $persister = $disk !== null ? new CML_Persister_DiskPersister($disk, $path) : null;

        return $adapter->predict(
            $path,
            $data,
            null,
            $persister,
        );
    }

    /**
     * Sama seperti predict(), tapi lewat CML_Adapter_RubixAdapter::probability() - lihat
     * docblock method itu untuk keterbatasannya (hanya estimator Probabilistic).
     *
     * @param CML_DataPredict $dataPredict
     *
     * @return array[]|array
     */
    public function predictProbabilities(CML_DataPredict $dataPredict) {
        $data = $dataPredict->getData();

        $adapter = CML_Manager::instance()->createRubixAdapter();
        $modelRepository = CML_Manager::instance()->getModelRepository($dataPredict->getModelPath());
        $path = $modelRepository->file($dataPredict->getModelFile());
        $disk = $dataPredict->getDisk();
        $persister = $disk !== null ? new CML_Persister_DiskPersister($disk, $path) : null;

        return $adapter->probability(
            $path,
            $data,
            null,
            $persister,
        );
    }
}
