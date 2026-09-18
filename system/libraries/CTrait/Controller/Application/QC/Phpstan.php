<?php

/**
 * Description of phpstan.
 */
trait CTrait_Controller_Application_QC_Phpstan {
    protected function getTitle() {
        return 'Php Stan';
    }

    public function index() {
        $app = c::app();

        $app->title($this->getTitle());

        $phpstan = CQC::phpstan();

        if (!$phpstan->isInstalled()) {
            $app->addAlert()->setType('error')->add('phpstan is not installed, please run phpstan:install command');

            return $app;
        }
        $runUrl = $this->controllerUrl() . 'run';
        $pollUrl = $this->controllerUrl() . 'poll';
        $data = CQC::phpstan()->createRunner()->getData();
        $app->addView('cresenity.qc.phpstan', [
            'data' => $data,
            'runUrl' => $runUrl,
            'pollUrl' => $pollUrl,
        ]);
        $result = carr::get($data, 'result');
        if (is_array($result)) {
            $app->addDiv()->addClass('mb-3')->add('Total Error:' . c::formatter()->formatNumber(carr::get($result, 'totals.file_errors')));
            $errors = carr::get($result, 'errors');
            $errorData = c::collect($errors)
                ->filter(function ($item) {
                    return !cstr::startsWith($item, 'Child process error');
                })->map(function ($item) {
                    return [
                        'file' => '#',
                        'errors' => 1,
                        // 'messages' => c::collect(carr::get($item, 'messages'))->filter(function ($message) {
                        //     return carr::get($message, 'ignorable') === true;
                        // })->toArray()
                        'messages' => $item,
                    ];
                })->toArray();

            $tableData = c::collect(carr::get($result, 'files'))->map(function ($item, $key) {
                $file = $key;
                if (cstr::startsWith($file, DOCROOT)) {
                    $file = str_replace(DOCROOT, '', $file);
                }

                return [
                    'file' => $file,
                    'errors' => carr::get($item, 'errors'),
                    // 'messages' => c::collect(carr::get($item, 'messages'))->filter(function ($message) {
                    //     return carr::get($message, 'ignorable') === true;
                    // })->toArray()
                    'messages' => carr::get($item, 'messages'),
                ];
            })->filter(function ($row) {
                return count(carr::get($row, 'messages')) > 0;
            })->toArray();
            $tableData = array_merge($errorData, $tableData);
            $table = $app->addTable();
            $table->setDataFromArray($tableData);
            $table->addColumn('file')->setLabel('File');
            $table->addColumn('errors')->setLabel('Error');
            $table->addColumn('messages')->setLabel('Message')->setCallback(function ($row, $value) {
                $div = c::div();
                if (is_array($value)) {
                    $ul = $div->addUl();
                    foreach ($value as $val) {
                        $line = carr::get($val, 'line');
                        $message = carr::get($val, 'message');
                        $ul->addLi()->add('line ' . $line . ':' . c::e($message));
                    }
                } else {
                    $div->add($value);
                }

                return $div;
            });
        }

        return $app;
    }

    public function run() {
        CQC::phpstan()->createRunner()->run();

        return CApp_Base::toJsonResponse(0, '', []);
    }

    public function poll() {
        $data = CQC::phpstan()->createRunner()->getData();

        return CApp_Base::toJsonResponse(0, '', $data);
    }
}
