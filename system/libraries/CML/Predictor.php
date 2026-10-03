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
}
