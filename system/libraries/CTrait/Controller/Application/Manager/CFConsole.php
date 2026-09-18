<?php

/**
 * Description of CFConsole
 */
trait CTrait_Controller_Application_Manager_CFConsole {
    protected function getTitle() {
        return 'CF Console';
    }

    public function index() {
        $app = CApp::instance();
        $app->title($this->getTitle());

        $widget = $app->addWidget()->setNoPadding();
        $terminal = $widget->addTerminal();
        $terminal->setAjaxUrl($this->controllerUrl() . 'command');
        $terminal->setPrompt('CF >');
        $terminal->setGreetings('Welcome to CF Console');
        return $app;
    }

    public function command() {
        $request = CApp_Base::getRequest();

        $command = carr::get($request, 'command');

        $CFCli = new CApp_CFCli();

        $process = $CFCli->run($command);
        $errorOutput = $process->getErrorOutput();
        $output = $process->getOutput();
        if (strlen($errorOutput) > 0) {
            echo '[[;#ff0000;]' . $errorOutput . ']';
            return;
        }

        echo $output;
    }
}
