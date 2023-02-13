<?php

defined('SYSPATH') or die('No direct access allowed.');

class CComponent_ControllerHandler {
    protected $handler;

    public function __construct($method) {
        $class = CComponent_Handler_HttpConnectionHandler::class;
        switch ($method) {
            case 'upload':
                $class = CComponent_Handler_FileUploadHandler::class;

                break;
            case 'preview':
                $class = CComponent_Handler_FilePreviewHandler::class;

                break;
        }
        if (!class_exists($class)) {
            throw new Exception('Component handler not found for method:' . $method);
        }
        $this->handler = new $class();
    }

    public function execute($payload) {
        $response = $this->handler->__invoke($payload);

        return $response;
    }
}
