<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Apr 14, 2019, 12:52:52 PM
 */
class CAjax_Engine_Validation extends CAjax_Engine {
    public function execute() {
        $data = $this->ajaxMethod->getData();
        $dataValidation = unserialize(carr::get($data, 'dataValidation'));

        $formId = carr::get($data, 'formId');
        $formId = carr::get($data, '');

        $data = array_merge($_GET, $_POST);

        $remoteValidator = new CJavascript_Validation_Remote($data, $dataValidation);
        $result = $remoteValidator->validate();

        echo json_encode($result);
    }
}
