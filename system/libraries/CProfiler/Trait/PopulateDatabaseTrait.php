<?php

/**
 * Description of PopulateDatabaseTrait
 */
trait CProfiler_Trait_PopulateDatabaseTrait {
    /**
     * Database query benchmarks.
     *
     * @return void
     */
    public static function createDatabaseTable() {
        $table = new CProfiler_TableRenderer();

        $table->addColumn();
        $table->addColumn('kp-column kp-data');
        $table->addColumn('kp-column kp-data');
        $table->addRow(['Queries', 'Time', 'Rows'], 'kp-title', 'background-color: #E0FFE0');

        $queries = CDatabase::$benchmarks;

        $totalTime = $totalRows = 0;
        $i = 0;
        foreach ($queries as $query) {
            $data = [$query['query'] . ' - ' . $query['caller'], number_format($query['time'], 3), $query['rows']];
            //$data = array($query['query'], number_format($query['time'], 3), $query['rows']);
            $class = $i % 2 == 0 ? '' : 'kp-altrow';
            $table->addRow($data, $class);
            $totalTime += $query['time'];
            $totalRows += $query['rows'];
            $i++;
        }

        $data = ['Total: ' . count($queries), number_format($totalTime, 3), $totalRows];
        $table->addRow($data, 'kp-totalrow');
        return $table;
    }
}
