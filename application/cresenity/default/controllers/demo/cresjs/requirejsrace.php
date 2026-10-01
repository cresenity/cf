<?php

class Controller_Demo_Cresjs_Requirejsrace extends \Cresenity\Demo\Controller {
    public function index() {
        $app = c::app();
        $app->title('CF.requireJsAsync Race Repro');
        $app->addView('demo/page/cresjs/requirejsrace');

        return $app;
    }

    public function slowScript() {
        // sengaja lambat untuk mensimulasikan tag <script> yang sudah ada di DOM
        // tapi browser belum selesai mendownload/mengeksekusinya (skenario race
        // yang dicurigai pada CF.requireJsAsync/requireCssAsync).
        usleep(1200000);

        return c::response(
            'window.__slowScriptLoaded = true;',
            200,
            ['Content-Type' => 'application/javascript']
        );
    }

    public function slowCss() {
        usleep(1200000);

        return c::response(
            '#slow-css-marker { --slow-css-loaded: 1; }',
            200,
            ['Content-Type' => 'text/css']
        );
    }
}
