<?php

defined('SYSPATH') or die('No direct access allowed.');
use DebugBar\DataCollector\Renderable;

class CDebug_DataCollector_RenderableCollector extends CDebug_DataCollector implements Renderable {
    protected $renderable = [];

    public function __construct() {
        $this->setDataFormatter(new CDebug_DataFormatter_SimpleFormatter());

        try {
            CApp::instance()->listenOnRenderableAdded(function (CApp_Event_OnRenderableAdded $eventArgs) {
                $this->addRenderable($eventArgs);
            });
        } catch (\Exception $e) {
            CDebug::bar()->addThrowable(new Exception('Cannot add listen to Element for Debugbar: ' . $e->getMessage(), $e->getCode(), $e));
        }
    }

    /**
     * @param string $className
     */
    public function addRenderable(CApp_Event_OnRenderableAdded $eventArgs) {
        $className = $eventArgs->getRenderableClass();
        $content = $eventArgs->getContent();
        $message = $className;
        if (is_array($content)) {
            $message = '[array] ' . var_export($content, true);
        } elseif (is_string($content)) {
            $message = $className . $content;
            if (strlen($content) > 0) {
                $message = '[string] ' . $message;
            }
        }
        $this->renderable[] = $message;
    }

    public function collect() {
        $messages = [];

        foreach ($this->renderable as $message) {
            $messages[] = [
                'message' => $message,
                // Use PHP syntax so we can copy-paste to compile config file.
                'is_string' => true,
            ];
        }

        return [
            'messages' => $messages,
            'count' => count($this->renderable),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getWidgets() {
        $name = $this->getName();

        return [
            "$name" => [
                'icon' => 'files-o',
                'widget' => 'PhpDebugBar.Widgets.MessagesWidget',
                'map' => "$name.messages",
                'default' => '{}'
            ],
            "$name:badge" => [
                'map' => "$name.count",
                'default' => 'null'
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public function getName() {
        return 'renderable';
    }
}
