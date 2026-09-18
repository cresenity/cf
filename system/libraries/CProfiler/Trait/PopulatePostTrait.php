<?php

/**
 * Description of BenchmarkTrait
 */
trait CProfiler_Trait_PopulatePostTrait {
    /**
     * Benchmark times and memory usage from the Benchmark library.
     *
     * @return void
     */
    public static function createPostTable() {
        $table = new CProfiler_TableRenderer();

        $table->addColumn('kp-name');
        $table->addColumn();
        $table->addRow(['POST', 'Value'], 'kp-title', 'background-color: #E0E0FF');

        $posts = CHTTP::request()->post();
        $i = 0;
        foreach ($posts as $name => $value) {
            $data = [$name, $value];
            $class = $i % 2 == 0 ? '' : 'kp-altrow';

            $table->addRow($data, $class);
            $i++;
        }

        return $table;
    }
}
