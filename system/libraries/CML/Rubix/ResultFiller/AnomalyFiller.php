<?php

use Rubix\ML\Estimator;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\AnomalyDetectors\Scoring;

class CML_Rubix_ResultFiller_AnomalyFiller implements CML_Contract_ResultFillerInterface {
    public static function predict($modelPath, array $data, Estimator $estimator): array {
        // Pakai $estimator yang sudah dilatih di memori - jangan reload dari $modelPath, itu
        // cuma masuk akal untuk Filesystem lokal dan gagal untuk persister lain (mis.
        // CML_Persister_DiskPersister) yang tidak pernah dilewatkan ke sini.
        $anomalies = CML_Adapter_RubixAdapter::predict($modelPath, $data, $estimator);

        if ($estimator instanceof Scoring) {
            $scores = $estimator->score(Unlabeled::build($data));
        }

        $can_score = $estimator instanceof Scoring;

        for ($i = 0, $iMax = count($data); $i < $iMax; $i++) {
            $data[$i]['anomaly'] = $anomalies[$i];

            if ($can_score && $scores ?? null) {
                $data[$i]['anomaly_score'] = $scores[$i];
            }
        }

        if ($can_score) {
            usort(
                $data,
                function ($a, $b) {
                    return $b['anomaly_score'] <=> $a['anomaly_score'];
                }
            );
        } else {
            usort(
                $data,
                function ($a, $b) {
                    return $a['anomaly'] <=> $b['anomaly'];
                }
            );
        }

        return $data;
    }
}
