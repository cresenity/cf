<?php

use Rubix\ML\Estimator;

class CML_Rubix_ResultFiller_ClustererFiller implements CML_Contract_ResultFillerInterface {
    public static function predict(string $modelPath, array $data, Estimator $estimator) {
        // Pakai $estimator yang sudah dilatih di memori - jangan reload dari $modelPath, itu
        // cuma masuk akal untuk Filesystem lokal dan gagal untuk persister lain (mis.
        // CML_Persister_DiskPersister) yang tidak pernah dilewatkan ke sini.
        $clusters = CML_Adapter_RubixAdapter::predict($modelPath, $data, $estimator);

        for ($i = 0, $iMax = count($data); $i < $iMax; $i++) {
            $data[$i]['cluster_nr'] = $clusters[$i];
        }

        usort(
            $data,
            function ($a, $b) {
                return $a['cluster_nr'] <=> $b['cluster_nr'];
            }
        );

        return $data;
    }
}
