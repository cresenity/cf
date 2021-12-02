<?php

class CNotification_TaskQueue_NotificationSender extends CNotification_TaskQueueAbstract {
    protected $params;

    public function __construct($params) {
        $this->params = $params;
    }

    public function execute() {
        $channel = carr::get($this->params, 'channel');
        $options = carr::get($this->params, 'options');
        $className = carr::get($this->params, 'className');
        $this->logDaemon('Processing NotificationSender ' . $className . ' with options: ' . json_encode($options));

        try {
            CNotification::manager()->channel($channel)->sendWithoutQueue($className, $options);
        } catch (CModel_Exception_ModelNotFoundException $ex) {
            $this->logDaemon('Ignore Error: ' . $className . '');
        }
        $this->logDaemon('Processed NotificationSender ' . $className . '');
    }
}
