<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Aug 24, 2018, 7:57:23 PM
 */
trait CTrait_Controller_Documentation_Cache {
    public function index() {
        $this->cache();
    }

    public function cache() {
        $app = CApp::instance();
        $app->title('Cache');

        $code = 'CCache::store(\'file\')->get(\'foo\');';
        $app->addDiv()->addClass('my-2 console')->add($code);

        $result = CCache::store('file')->get('foo');
        $app->addDiv()->addClass('my-2 json-container')->add($result);
        echo $app->render();
    }
}
