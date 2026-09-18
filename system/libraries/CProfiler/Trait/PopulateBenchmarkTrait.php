<?php

/**
 * Description of BenchmarkTrait
 */
trait CProfiler_Trait_PopulateBenchmarkTrait {
    /**
     * Benchmark times and memory usage from the Benchmark library.
     *
     * @return void
     */
    public static function createBenchmarkTable() {
        $table = new CProfiler_TableRenderer();

        $table->addColumn();
        $table->addColumn('kp-column kp-data');
        $table->addColumn('kp-column kp-data');
        $table->addColumn('kp-column kp-data');
        $table->addRow(['Benchmarks', 'Time', 'Count', 'Memory'], 'kp-title', 'background-color: #FFE0E0');

        $benchmarks = CFBenchmark::get(true);

        // Moves the first benchmark (total execution time) to the end of the array
        $benchmarks = array_slice($benchmarks, 1) + array_slice($benchmarks, 0, 1);
        $i = 0;
        foreach ($benchmarks as $name => $benchmark) {
            // Clean unique id from system benchmark names
            $name = ucwords(str_replace(['_', '-'], ' ', str_replace(SYSTEM_BENCHMARK . '_', '', $name)));

            $data = [$name, number_format((int)$benchmark['time'], 3), (int)$benchmark['count'], number_format((float)$benchmark['memory'] / 1024 / 1024, 2) . 'MB'];
            $class = $i % 2 == 0 ? '' : 'kp-altrow';

            if ($name == 'Total Execution') {
                $class = 'kp-totalrow';
            }

            $table->addRow($data, $class);
            $i++;
        }
        return $table;
    }
}
