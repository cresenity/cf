<?php

/**
 * Description of BenchmarkTrait
 */
trait CProfiler_Trait_PopulateCookiesTrait {
    /**
     * Benchmark times and memory usage from the Benchmark library.
     *
     * @return void
     */
    public static function createCookiesTable() {
        $table = new CProfiler_TableRenderer();

        $table->addColumn('kp-name');
        $table->addColumn();

        $table->addRow(['Cookies', 'Value'], 'kp-title', 'background-color: #FFF4D7');

        $cookies = CHTTP::request()->cookies;
        $i = 0;
        foreach ($cookies as $name => $value) {
            $data = [$name, $value];
            $class = $i % 2 == 0 ? '' : 'kp-altrow';

            $table->addRow($data, $class);
            $i++;
        }

        return $table;
    }
}
