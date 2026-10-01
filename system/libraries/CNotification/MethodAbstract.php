<?php

abstract class CNotification_MethodAbstract implements CNotification_MethodInterface {
    use CTrait_HasOptions;

    public function __construct() {
    }

    /**
     * Kerangka email bersama (config email.template) pengganti buildEmailBuilder() salinan per app.
     *
     * @param array $options menimpa config email.template
     *
     * @return CEmail_Builder_Template
     */
    public function emailTemplate(array $options = []) {
        return CEmail::template($options);
    }

    /**
     * @param mixed $logNotificationModel
     *
     * @return void
     */
    public function onNotificationSent($logNotificationModel) {
    }
}
