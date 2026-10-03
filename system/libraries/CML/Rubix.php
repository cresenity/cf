<?php

use Rubix\ML\DataType;
use Rubix\ML\Pipeline;
use Rubix\ML\Estimator;
use Rubix\ML\Extractors\CSV;

use Rubix\ML\Loggers\Screen;
use Rubix\ML\PersistentModel;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Classifiers\KDNeighbors;
use Rubix\ML\Extractors\ColumnPicker;
use Rubix\ML\Transformers\Transformer;
use Rubix\ML\Kernels\Distance\Manhattan;
use Rubix\ML\Transformers\OneHotEncoder;
use Rubix\ML\CrossValidation\Metrics\MCC;
use Rubix\ML\CrossValidation\Metrics\FBeta;
use Rubix\ML\Transformers\MinMaxNormalizer;
use Rubix\ML\Regressors\KDNeighborsRegressor;
use Rubix\ML\Transformers\MissingDataImputer;
use Rubix\ML\Transformers\NumericStringConverter;
use Rubix\ML\CrossValidation\Metrics\Informedness;
use Rubix\ML\CrossValidation\Reports\ErrorAnalysis;

class CML_Rubix {
    public static function getEstimatorClassMaps() {
        return [
            'clusterer' => [
                'kmeans' => \Rubix\ML\Clusterers\KMeans::class
            ],
            'classifier' => [
                'naivebayes' => \Rubix\ML\Classifiers\NaiveBayes::class
            ]
        ];
    }

    public static function getDefaultParameters() {
        return [
            'clusterer' => [
                'kmeans' => [
                    'k' => 5,
                    'batchSize' => 128,
                    'epochs' => 1000,
                    'minChange' => 1e-4,
                    'window' => 5,
                    'kernel' => new Manhattan(),
                    'seeder' => null
                ]
            ],
            'classifier' => [
                'naivebayes' => [
                    'priors' => null,
                    'smoothing' => 1.0
                ]
            ]
        ];
    }

    /**
     * @param string $estimator
     * @param array  $parameters
     *
     * @return \Rubix\ML\Estimator
     */
    public static function createEstimator($estimator, $parameters = []) {
        $estimatorClass = carr::get(self::getEstimatorClassMaps(), $estimator);
        if (!$estimatorClass) {
            throw new Exception('Estimator ' . $estimator . ' not found');
        }
        $params = carr::get(self::getDefaultParameters(), $estimator);
        foreach ($params as $k => $v) {
            if (isset($parameters[$k])) {
                $params[$k] = $parameters[$k];
            }
        }

        return c::container()->make($estimatorClass, $params);
    }
}
