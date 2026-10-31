<?php

trait CTrait_Controller_Application_Log_System {
    public function getPath() {
        return DOCROOT . 'logs/' . CF::appCode();
    }

    public function system() {
        $app = c::app();
        $path = $this->getPath();
        $years = array_reverse($this->getSubdirectory($path));

        $reportTimes = [];

        foreach ($years as $year) {
            $months = array_reverse($this->getSubdirectory("{$path}/{$year}"));
            foreach ($months as $month) {
                $reportTimes[] = [
                    'year' => $year,
                    'month' => $month
                ];
            }
        }

        $tabList = $app->addTabList();
        foreach ($reportTimes as $time) {
            $year = carr::get($time, 'year');
            $month = carr::get($time, 'month');
            $tab = $tabList->addTab()
                ->setLabel("{$year} - {$month}")
                ->addClass('p-0')
                ->setNoPadding()
                ->setAjaxUrl($this->currentUrl() . "tabDaily/{$year}/{$month}");
        }

        return $app;
    }

    private function currentUrl() {
        $class = get_called_class();

        $classExplode = explode('_', $class);
        $classExplode = array_map(function ($item) {
            return cstr::camel($item);
        }, $classExplode);
        $url = curl::base() . implode('/', array_slice($classExplode, 1)) . '/';

        return $url;
    }

    private function getSubdirectory($path) {
        return array_values(array_diff(scandir($path), ['..', '.']));
    }

    /**
     * @param string      $year
     * @param string      $month
     * @param null|string $file
     *
     * @return null|string
     */
    private function resolveLogPath($year, $month, $file = null) {
        $path = $this->getPath() . DS . $year . DS . $month;
        if ($file !== null) {
            $path .= DS . $file;
        }
        $real = realpath($path);
        $root = realpath($this->getPath());
        if ($real === false || $root === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        return $real;
    }

    public function tabDaily($year, $month) {
        $app = c::app();

        $path = $this->resolveLogPath($year, $month);
        if ($path === null) {
            return c::abort(404);
        }
        $logFiles = [];

        try {
            $directory = new RecursiveDirectoryIterator($path);
            $iterator = new RecursiveIteratorIterator($directory);
            $logFiles = new RegexIterator($iterator, '/^.+\.log$/i', RecursiveRegexIterator::GET_MATCH);
        } catch (Exception $ex) {
        }
        $tabList = $app->addTabList();
        $logFiles = c::collect($logFiles)->sort(function ($a, $b) {
            return c::spaceshipOperator($b, $a);
        })->toArray();
        foreach ($logFiles as $file) {
            $file = carr::get($file, 0);

            if ($file) {
                $basename = basename($file);
                $tab = $tabList->addTab()
                    ->setLabel($basename)
                    ->addClass('p-0')
                    ->setNoPadding()
                    ->setAjaxUrl($this->currentUrl() . "systemTable/{$year}/{$month}?file=" . urlencode($basename));
            }
        }

        return $app;
    }

    public function systemTable($year, $month) {
        $file = c::request()->file;
        $app = c::app();
        $path = $this->resolveLogPath($year, $month, (string) $file);
        if ($path === null || !is_file($path)) {
            return c::abort(404);
        }
        $table = $app->addTable();
        $table->addHeaderAction()->setLabel('Download')
            ->addClass('btn-primary')
            ->setLink($this->currentUrl() . "systemTableDownload/{$year}/{$month}/{$file}");
        $table->addColumn('time')->setLabel('Time');
        $table->addColumn('environment')->setLabel('Environment');
        $table->addColumn('level')->setLabel('Level')->setCallback(function ($row, $value) {
            return '<span class="badge badge-' . c::get($row, 'levelClass', 'secondary') . '">' . $value . '</span>';
        });
        $table->addColumn('message')->setLabel('Message')->setCallback(function ($row, $value) {
            return c::div()->addShowMore()->add($value);
        });
        $table->setDataFromClosure(function (CManager_DataProviderParameter $parameter) use ($path) {
            $errCode = 0;
            $errMessage = '';

            $perPage = $parameter->getPerPage();
            $page = $parameter->getPage();
            $keywords = $parameter->getSearchOrData();
            $logFile = CLogger::reader()->createLogFile($path);
            $logQuery = $logFile->logs();
            $keyword = carr::first($keywords);
            $logQuery->search($keyword);
            $logQuery->setDirection(CLogger_Reader_Direction::BACKWARD);

            return $logQuery->paginate($perPage, $page, function (CLogger_Reader_Log $log) {
                return [
                    'time' => (string) $log->time,
                    'environment' => $log->environment,
                    'level' => $log->level->getName(),
                    'levelClass' => $log->level->getClass(),
                    'short' => $log->text,
                    'message' => $log->fullText,
                ];
            });
        });
        $table->setAjax(true);

        return $app;
    }

    public function systemTableDownload($year, $month, $file) {
        $path = $this->resolveLogPath($year, $month, $file);
        if ($path === null || !is_file($path)) {
            return c::abort(404);
        }
        $logFile = CLogger::reader()->createLogFile($path);

        return $logFile->download();
    }
}
