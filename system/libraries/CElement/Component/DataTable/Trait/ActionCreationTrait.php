<?php
/**
 * @see CElement_Component_DataTable
 */
trait CElement_Component_DataTable_Trait_ActionCreationTrait {
    /**
     * @param array $options
     *
     * @return CElement_Component_Action
     */
    public function createAutoRefreshToogleAction($options) {
        $label = carr::get($options, 'label');
        $labelStart = 'Start Live';
        $labelStop = 'Stop Live';
        if (is_array($label)) {
            $labelStart = carr::get($options, 'label.start') ?: 'Start Live';
            $labelStop = carr::get($options, 'label.stop') ?: 'Stop Live';
        } else {
            if (is_string($label)) {
                $labelStart = $label;
                $labelStop = $label;
            }
        }

        $autoRefreshInterval = carr::get($options, 'interval') ?: ($this->autoRefreshInterval ?: 5);
        $id = carr::get($options, 'id');
        $act = new CElement_Component_Action($id);
        $act->setLabel($labelStart);
        $idAct = $act->id();
        $act->onClickListener()->addCustomHandler()->setJs("
            let oTable = $('#" . $this->id . "').data('cappDataTable');
            if(oTable) {
                let intervalHandler = $('#" . $this->id . "').data('cappDataTableAutoRefreshHandler');
                if(intervalHandler) {
                    clearInterval(intervalHandler);
                    $('#" . $this->id . "').data('cappDataTableAutoRefreshHandler', null);
                    $('#" . $idAct . "').html('" . $labelStart . "');
                } else {
                    $('#" . $this->id . "').data('cappDataTableAutoRefreshHandler', setInterval( function () {
                        $('#" . $this->id . "').DataTable().ajax.reload(null, false);
                    }, " . ((int) $autoRefreshInterval) . " * 1000));
                    $('#" . $idAct . "').html('" . $labelStop . "');
                }
            }
        ");
        $act->addJs("
            setTimeout(function() {
                let oTable = $('#" . $this->id . "').data('cappDataTable');

                if(oTable) {
                    let intervalHandler = $('#" . $this->id . "').data('cappDataTableAutoRefreshHandler');
                    if(intervalHandler) {
                        $('#" . $idAct . "').html('" . $labelStop . "');
                    } else {
                        $('#" . $idAct . "').html('" . $labelStart . "');

                    }
                }
            },1);
        ");

        return $act;
    }

    /**
     * @param array $options
     *
     * @return CElement_Component_Action
     */
    public function createExportAction($options) {
        $id = carr::get($options, 'id');
        $options['action'] = CExporter::ACTION_STORE;
        $act = new CElement_Component_Action($id);
        $act->setLabel('Export');

        $ajaxMethod = CAjax::createMethod();
        $ajaxMethod->setType('DataTableExporter');
        $ajaxMethod->setData('table', serialize($this));
        $ajaxMethod->setData('exporter', $options);
        $ajaxMethod->setExpiration(carr::get($options, 'expiration', c::now()->addDays(1)->getTimestamp()));
        if (carr::get($options, 'auth', c::app()->isAuthEnabled())) {
            $ajaxMethod->enableAuth();
        }
        $downloadUrl = $ajaxMethod->makeUrl();

        $act->setLink($downloadUrl)->setLinkTarget('_blank');

        return $act;
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function createDownloadUrl($options) {
        /** @var CElement_Component_DataTable $this */
        $exportable = $this->toExportable();
        $ajaxMethod = CAjax::createMethod();
        $ajaxMethod->setType(CAjax_Engine_Exporter::class);
        $ajaxMethod->setData('exporter', serialize($exportable));
        $ajaxMethod->setData('filename', carr::get($options, 'filename'));
        $ajaxMethod->setData('writerType', carr::get($options, 'writerType'));
        $ajaxMethod->setData('headers', carr::get($options, 'headers', []));
        $ajaxMethod->setExpiration(carr::get($options, 'expiration', c::now()->addDays(1)->getTimestamp()));
        if (carr::get($options, 'auth', c::app()->isAuthEnabled())) {
            $ajaxMethod->enableAuth();
        }

        $downloadUrl = $ajaxMethod->makeUrl();

        return $downloadUrl;
    }

    /**
     * @param array $options
     *
     * @return CElement_Component_Action
     */
    public function createDownloadAction($options = []) {
        $id = carr::get($options, 'id');

        $options['action'] = CExporter::ACTION_DOWNLOAD;
        $act = new CElement_Component_Action($id);
        $act->setLabel('Export');
        $downloadUrl = $this->createDownloadUrl($options);
        $act->setLink($downloadUrl)->setLinkTarget('_blank');

        return $act;
    }

    /**
     * @param array $options
     *
     * @return CElement_Component_Action
     */
    public function createDownloadProgressAction($options = []) {
        $id = carr::get($options, 'id');

        $options['action'] = CExporter::ACTION_DOWNLOAD;
        $options['queued'] = true;
        $act = CElement_Factory::createComponent('Action', $id)->setLabel('Export');
        /** @var CElement_Component_Action $act */
        $disk = carr::get($options, 'disk');
        $filename = carr::get($options, 'filename');

        $fileUrl = CStorage::instance()->disk($disk)->url($filename);

        $ajaxMethod = CAjax::createMethod();
        $ajaxMethod->setType(CAjax_Engine_DataTableExporter::class);
        $ajaxMethod->setData('table', serialize($this));
        $ajaxMethod->setData('exporter', $options);
        $ajaxMethod->setData('progress', true);
        $ajaxMethod->setData('state', 'PENDING');
        $ajaxMethod->setData('progressValue', '0');
        $ajaxMethod->setData('progressMax', '100');
        $ajaxMethod->setData('writerType', carr::get($options, 'writerType', CExporter::XLS));
        $ajaxMethod->setData('fileUrl', $fileUrl);
        $ajaxMethod->setExpiration(carr::get($options, 'expiration', c::now()->addDays(1)->getTimestamp()));
        if (carr::get($options, 'auth', c::app()->isAuthEnabled())) {
            $ajaxMethod->enableAuth();
        }
        $downloadUrl = $ajaxMethod->makeUrl();

        $act->addListener('click')->addDownloadProgressHandler()->setUrl($downloadUrl);

        return $act;
    }
}
