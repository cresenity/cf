<?php

/**
 * Description of BenchmarkTrait
 */
trait CProfiler_Trait_PopulateSessionTrait {
    /**
     * Benchmark times and memory usage from the Benchmark library.
     *
     * @return void
     */
    public static function createSessionTable() {
        $table = new CProfiler_TableRenderer();

        $table->addColumn('kp-name');
        $table->addColumn();

        $table->addRow(['Session', 'Value'], 'kp-title', 'background-color: #CCE8FB');
        $sessions = c::session()->all();
        $i = 0;
        foreach ($sessions as $name => $value) {
            $data = [$name, $value];
            $class = $i % 2 == 0 ? '' : 'kp-altrow';

            $table->addRow($data, $class);
            $i++;
        }

        return $table;
    }
}
