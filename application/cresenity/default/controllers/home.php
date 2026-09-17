<?php

/**
 * Description of home.
 *
 * @author Hery
 */
class Controller_Home extends CController {
    public function index() {
        $app = CApp::instance();
        $app->setLoginRequired(false);
        $app->setView('welcome');

        return $app;
    }

    public function upload() {
        $app = CApp::instance();
        $app->setLoginRequired(false);
        CManager::registerModule('bootstrap-4');
        $app->setViewName('test.upload');

        return $app;
    }

    public function validate() {
        $app = CApp::instance();
        $app->setLoginRequired(false);
        CManager::registerModule('bootstrap-4');
        $app->setViewName('test.validate');

        return $app;
    }

    public function test() {
        $app = CApp::instance();
        $app->setLoginRequired(false);
        $app->setViewName('test2');
        $app->setTheme('cfdocs');
        echo $app->render();
    }

    public function member() {
        $app = CApp::instance();
        $app->setLoginRequired(false);
        $app->setViewName('test');
        $app->setTheme('cfdocs');

        $app->addView('member', [
            'members' => \Cresenity\Testing\MemberModel::all()
        ]);

        return $app;
    }

    public function child() {
        return c::response()->view('child');
    }

    public function app() {
        $app = CApp::instance();

        echo $app->render();
    }
}
