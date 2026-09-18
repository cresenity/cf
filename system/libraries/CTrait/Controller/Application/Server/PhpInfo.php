<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jul 7, 2020
 */
trait CTrait_Controller_Application_Server_PhpInfo {
    public function phpinfo() {
        $app = c::app();

        $app->title(c::__('PHP Info'));

        $html = CView::factory('cresenity/phpinfo/index');
        $html = $html->render();
        $app->add($html);

        echo $app->render();
    }
}
