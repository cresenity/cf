<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since May 17, 2019, 1:43:03 AM
 */
trait CTrait_Controller_Application_Config_Editor {
    protected function getTitle() {
        return '';
    }

    protected function getConfigGroup() {
        return '';
    }

    protected function canEdit() {
        return false;
    }

    protected function canDelete() {
        return false;
    }

    protected function canAdd() {
        return false;
    }

    protected function baseUri() {
        return CFRouter::controllerUri();
    }

    private function computeTitle() {
        $title = $this->getTitle();
        if ($title == '') {
            $title = ucfirst($this->getConfigGroup()) . ' Setting';
        }

        return $title;
    }

    public function index() {
        $app = CApp::instance();

        $app->title($this->computeTitle());

        $config = CConfig::instance($this->getConfigGroup());

        $table = $app->addTable();
        $table->setDataFromArray($config->getConfigData());
        $table->addColumn('key')->setLabel('Key');
        $table->addColumn('value')->setLabel('Value');
        $table->addColumn('type')->setLabel('Type');
        $table->addColumn('file')->setLabel('File');
        $table->setApplyDataTable(false);
        //$table->setRowActionStyle('btn-dropdown');
        if ($this->canEdit()) {
            $table->addRowAction()->setLabel('Edit')->setIcon('fas fa-edit')->addClass('btn-primary')
                ->setLink($this->baseUri() . '/edit/{key}');
        }

        echo $app->render();
    }

    public function edit($key = null) {
        $app = CApp::instance();
        $app->addBreadcrumb($this->computeTitle(), $this->baseUri());
        $title = 'Edit ' . $key;
        $isEdit = strlen($key) > 0;
        $app->title($title);

        if (!$isEdit) {
            curl::redirect($this->baseUri());
        }
        $config = CConfig::instance($this->getConfigGroup());
        $appConfigFile = CF::appPath() . '/default/config/' . $this->getConfigGroup() . EXT;
        //find record for this key
        $configRecord = [];

        foreach ($config->getConfigData() as $d) {
            if (carr::get($d, 'key') == $key) {
                $configRecord = $d;

                break;
            }
        }

        $value = carr::get($configRecord, 'value');
        $file = carr::get($configRecord, 'file');
        $comment = carr::get($configRecord, 'comment');
        $originalValue = $value;
        $type = carr::get($configRecord, 'type');
        $post = CApp_Base::getRequestPost();
        $errCode = 0;
        $errMessage = '';
        if ($post != null) {
            $newValue = carr::get($post, 'value');
            $newValueCasted = $newValue;
            if (gettype($newValueCasted) !== $type) {
                settype($newValueCasted, $type);
            }
            $currentConfig = [];
            if (file_exists($appConfigFile)) {
                $currentConfig = include $appConfigFile;
            }
            carr::set_path($currentConfig, $key, $newValueCasted);
            CFile::putPhpValue($appConfigFile, $currentConfig);
            if ($errCode == 0) {
                cmsg::add('success', 'Successfully edit setting');
                curl::redirect($this->baseUri());
            } else {
                cmsg::add('error', $errMessage);
            }
        }

        $widget = $app->addWidget()->setTitle('Setting Record');

        $form = $widget->addForm();

        if ($file != $appConfigFile) {
            $form->addAlert()->setType('warning')->add('This edit will create new record on config file ' . $appConfigFile);
        }

        $keyControlType = 'text';
        if ($isEdit) {
            $keyControlType = 'label';
        }
        $valueControlType = 'text';
        $controlList = [];

        if (is_array($value)) {
            $valueControlType = 'select-tag';
        }
        if (is_bool($value)) {
            $valueControlType = 'select';
            $controlList = CConstant::yesNoList();
            $value = $value ? '1' : '0';
        }
        $form->addField()->setLabel('Config Location')->addControl('file', 'label')->setValue($file);
        $form->addField()->setLabel('Type')->addControl('type', 'label')->setValue($type);
        $form->addField()->setLabel('Key')->addControl('key', $keyControlType)->setValue($key);
        $valueControl = $form->addField()->setLabel('Value')->addControl('value', $valueControlType)->setValue($value);
        if ($valueControlType == 'select') {
            $valueControl->setList($controlList);
        }
        if (strlen($comment) > 0) {
            $form->addDiv()->setHaveIndent(false)->addClass('console')->add(trim($comment));
        }
        $form->addActionList()->addAction()->setLabel('Submit')->setSubmit()->setConfirm();
        echo $app->render();
    }
}
