<?php

class CML_Trainer {
    public function train(CML_DataTrain $dataTrain) {
        $data = $dataTrain->getData();

        $adapter = CML_Manager::instance()->createRubixAdapter();
        $estimator = CML_Rubix::createEstimator($dataTrain->getEstimator(), $dataTrain->getEstimatorParameters());
        $modelRepository = CML_Manager::instance()->getModelRepository($dataTrain->getModelPath());
        $path = $modelRepository->file($dataTrain->getModelFile());
        $disk = $dataTrain->getDisk();
        $persister = $disk !== null ? new CML_Persister_DiskPersister($disk, $path) : null;

        return $adapter->train(
            $path,
            $data,
            $dataTrain->getDataIndexWithLabel(),
            $estimator,
            $dataTrain->getTransformers(),
            1,
            $persister,
        );
    }
}
