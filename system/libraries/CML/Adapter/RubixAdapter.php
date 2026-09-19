<?php
use Rubix\ML\DataType;
use Rubix\ML\Pipeline;
use Rubix\ML\Estimator;
use Rubix\ML\Extractors\CSV;

use Rubix\ML\PersistentModel;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Classifiers\KDNeighbors;
use Rubix\ML\Extractors\ColumnPicker;
use Rubix\ML\Transformers\Transformer;
use Rubix\ML\Transformers\OneHotEncoder;
use Rubix\ML\CrossValidation\Metrics\MCC;
use Rubix\ML\CrossValidation\Metrics\FBeta;
use Rubix\ML\Transformers\MinMaxNormalizer;
use Rubix\ML\Regressors\KDNeighborsRegressor;
use Rubix\ML\Transformers\MissingDataImputer;
use Rubix\ML\Transformers\NumericStringConverter;
use Rubix\ML\CrossValidation\Metrics\Informedness;
use Rubix\ML\CrossValidation\Reports\ErrorAnalysis;

require_once DOCROOT . 'system/vendor/Rubix/ML/functions.php';
require_once DOCROOT . 'system/vendor/Rubix/ML/constants.php';
class CML_Adapter_RubixAdapter extends CML_AdapterAbstract {
    /**
     * @var CML_Rubix
     */
    private static $instance;

    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function train(
        $modelFilename,
        array $data,
        $data_index_w_label = null,
        ?Estimator $estimator_algorithm = null,
        ?array $transformers = null,
        float $trainPartSize = 1
    ) {
        $is_testable = false;
        if (is_null($estimator_algorithm)) {
            $is_testable = true;
        } else {
            if ($estimator_algorithm) {
                $et = $estimator_algorithm->type();

                if ($et->isRegressor() || $et->isClassifier()) {
                    $is_testable = true;
                }
            }
        }

        if ($is_testable) {
            $data_size = sizeof($data);

            if (!$data || !$data_size) {
                throw new CML_Exception_RubixException('Invalid $data provided');
            }

            shuffle($data);

            $train_size = ceil($data_size * $trainPartSize);

            $train_data = array_slice($data, 0, $train_size);
            $test_data = array_slice($data, $train_size, sizeof($data) - 1);

            static::trainWithoutTest($modelFilename, $train_data, $data_index_w_label, $estimator_algorithm, $transformers);

            $report = static::getErrorAnalysis($modelFilename, $test_data, $data_index_w_label);

            return $report;
        } else {
            //clusterer or anomaly estimator, returning data that is enriched with scores, clusters, and anomality rating
            $return_data = static::trainWithoutTest(
                $modelFilename,
                $data,
                $data_index_w_label,
                $estimator_algorithm,
                $transformers,
            );

            return $return_data;
        }
    }

    /**
     * @param array<array>       $data                make array iterable by doing $myiterable = new ArrayObject( ['a','b'] );
     * @param null|Estimator     $estimator_algorithm
     * @param null|Transformer[] $transformers
     * @param mixed              $data_index_w_label  the number/string of the index of the data to be trained
     * @param mixed              $modelFilename
     *
     * @return bool|array[] typically boolean whether the training was successful, otherwise in case of cluster returns $data with extra entry 'cluster_nr'
     */
    public static function trainWithoutTest(
        $modelFilename,
        array $data,
        $data_index_w_label = null,
        ?Estimator $estimator_algorithm = null,
        ?array $transformers = null
    ) {
        ini_set('memory_limit', '-1');

        $logger = new CML_Rubix_Logger('TrainData');

        $logger->info('Starting to train');

        if ($data_index_w_label) {
            list($samples, $labels) = CML_Utils::getLabelsFromSamples($data, $data_index_w_label);
            $dataset = new Labeled($samples, $labels);
        } else {
            $dataset = new Unlabeled($data);
        }

        if (is_null($estimator_algorithm)) {
            $label0 = $labels[0];
            $dt = DataType::detect($label0);

            $needs_regression = false;

            if ($dt->isContinuous()) {
                $needs_regression = true;
            }

            if ($needs_regression) {
                $estimator_algorithm = new KDNeighborsRegressor();
            } else {
                $estimator_algorithm = new KDNeighbors();
            }
        }

        if (is_null($transformers)) {
            $samples = $dataset->samples();
            $row1 = $samples[0];

            $has_categorical = false;

            foreach ($row1 as $feat) {
                $dt = DataType::detect($feat);

                if ($dt->isCategorical()) {
                    $has_categorical = true;

                    break;
                }
            }

            $needs_ohe = false;

            if ($has_categorical) {
                $needs_ohe = true;
            }

            $transformers = array_filter(
                [
                    new NumericStringConverter(),
                    new MissingDataImputer(),
                    $needs_ohe ? new OneHotEncoder() : false,
                    new MinMaxNormalizer(),
                ]
            );
        }
        CML_Utils::createIfNotExistsFolder(dirname($modelFilename));
        $estimator = new PersistentModel(
            new Pipeline(
                $transformers,
                $estimator_algorithm
            ),
            new Filesystem($modelFilename)
        );

        $estimator->train($dataset);

        $estimator->save();
        $logger->info('Finished training');
        $estimatorType = CML_Utils::getEstimatorType($estimator);
        if ($estimatorType === CML_Utils::CLUSTERER) {
            $data = CML_Rubix_ResultFiller_ClustererFiller::predict($modelFilename, $data, $estimator);

            return $data;
        } elseif ($estimatorType === CML_Utils::ANOMALITY) {
            $data = CML_Rubix_ResultFiller_AnomalyFiller::predict($modelFilename, $data, $estimator);

            return $data;
        }

        return $estimator->trained();
    }

    /**
     * @param array[] $input_data 2 dimensional array WIHTOUT label (e.g. without the value you want to predict)
     *
     * @return array|int
     */
    public static function predict(
        string $modelFilename,
        array $input_data,
        ?Estimator $estimator = null
    ) {
        $is_single_dimensional_array = false;
        if (is_array($input_data) && !is_array($input_data[0] ?? null)) {
            $input_data = [$input_data];
            $is_single_dimensional_array = true;
        }

        $logger = new CML_Rubix_Logger('Predict Data');

        $logger->info('Starting prediction');

        $input_data = new Unlabeled($input_data);

        if (is_null($estimator)) {
            $estimator = static::getEstimatorFromFilesystem($modelFilename);
        }

        $prediction = $estimator->predict($input_data);

        if ($is_single_dimensional_array && is_array($prediction)) {
            return $prediction[0];
        } else {
            return $prediction;
        }
    }

    public static function getErrorAnalysis(
        $modelFilename,
        array $samples_w_labels,
        $key_for_labels
    ) {
        list($samples, $labels) = CML_Utils::getLabelsFromSamples($samples_w_labels, $key_for_labels);

        $logger = new CML_Rubix_Logger('ErrorAnalysis');

        $dataset = new Unlabeled($samples);

        $estimator = static::getEstimatorFromFilesystem($modelFilename);

        $logger->info('Starting Error Analysis');

        $predictions = $estimator->predict($dataset);

        if (is_numeric($predictions[0])) {
            $report = new ErrorAnalysis();
            $results = $report->generate($predictions, $labels);
        } else {
            $metric = new FBeta(0.7);
            $fbeta = $metric->score($predictions, $labels);

            $metric = new MCC();
            $mcc = $metric->score($predictions, $labels);

            $metric = new Informedness();
            $informedness = $metric->score($predictions, $labels);

            $results = compact('fbeta', 'mcc', 'informedness');
        }

        return $results;
    }

    public static function getEstimatorFromFilesystem(string $modelFilename): Estimator {
        return PersistentModel::load(new Filesystem($modelFilename));
    }

    public static function fromCsv(string $filename, ?array $columns = null) {
        if (!$filename) {
            throw new Exception('Filename cannot be null or empty or fasly');
        }

        if (is_array($columns)) {
            $data = new ColumnPicker(
                new CSV($filename, true),
                $columns
            );
        } else {
            $data = new CSV($filename, true);
        }

        return iterator_to_array($data);
    }

    public static function toCsv($path, array $data, string $filename) {
        if (!$filename) {
            throw new Exception('Filename cannot be null or empty or fasly');
        }

        CML_Utils::createIfNotExistsFolder($path);
        $csv = new CSV($path . $filename, true);
        $csv->export(new \ArrayObject($data));
    }
}
