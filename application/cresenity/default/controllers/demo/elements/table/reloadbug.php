<?php

class Controller_Demo_Elements_Table_Reloadbug extends \Cresenity\Demo\Controller {
    protected static $rows = [
        ['code' => 'AF', 'name' => 'Afghanistan', 'continent' => 'Asia', 'isd' => '93'],
        ['code' => 'AL', 'name' => 'Albania', 'continent' => 'Europe', 'isd' => '355'],
        ['code' => 'DZ', 'name' => 'Algeria', 'continent' => 'Africa', 'isd' => '213'],
    ];

    protected function renderTable($container, $extraColumn = false) {
        $table = $container->addTable();
        $table->setDataFromClosure(function (CManager_DataProviderParameter $parameter) {
            // sengaja lambat untuk mensimulasikan request ajax data table yang masih
            // in-flight saat kontainernya di-reload ulang (skenario race yang dicurigai)
            usleep(2000000);
            $perPage = $parameter->getPerPage() ?: 10;
            $page = $parameter->getPage() ?: 1;

            return c::paginator(static::$rows, count(static::$rows), $perPage, $page, [
                'path' => CPagination_Paginator::resolveCurrentPath(),
            ]);
        });
        $table->addColumn('code')->setLabel('Code');
        $table->addColumn('name')->setLabel('Name');
        $table->addColumn('continent')->setLabel('Continent');
        if ($extraColumn) {
            // meniru kolom kondisional (mis. chiefEmail/chiefPhone di datafinance/transaction)
            // yang jumlahnya berbeda antar render tergantung filter/permission
            $table->addColumn('isd')->setLabel('ISD');
        }
        $table->setAjax(true);

        return $table;
    }

    public function index() {
        $app = c::app();
        $app->setTitle('Table Reload Bug Repro');

        $tableDiv = $app->addDiv('table-container-reloadbug');
        $this->renderTable($tableDiv, false);

        $action = $app->addAction('btn-reload-test')->setLabel('Reload Table (repro)')->addClass('btn-primary');
        $action->addListener('click')->addReloadHandler()
            ->setUrl(curl::base() . 'demo/elements/table/reloadbug/reload')
            ->setTarget('table-container-reloadbug');

        return $app;
    }

    public function reload() {
        $tableDiv = c::app()->addDiv('table-container-reloadbug');
        // render KEDUA sengaja punya jumlah kolom berbeda dari render pertama
        $this->renderTable($tableDiv, true);

        return c::app();
    }
}
