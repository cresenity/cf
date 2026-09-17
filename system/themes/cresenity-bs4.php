<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 29, 2019, 6:59:42 PM
 */

return [
    'client_modules' => [
        'layout-helpers',
        'pace',
        'jquery',
        'bootstrap-4-material',
        'fontawesome-5-f',
        'ionicons',
        'linearicons',
        'open-ionic',

        'pe-icon-7-stroke',
        'glyphicons',
        'perfect-scrollbar',
        'sidenav',
        'layout-helpers',
        'form',
        'fileupload',
        'notify',
        'bootbox',
        'prettify',
        'jquery-ui-1.12.1.custom',
        'jquery.datatable',
        'select2',
        'theme-material',
    ],
    'js' => [
        'cresenity.js',
        'cresenity/cresenity.bs4.js?v=3',
        'cresenity/administrator.js?v=' . uniqid(),
    ],
    'css' => [
        'cresenity.css',
        'cresenity/cresenity.bs4.css?v=' . uniqid(),
        'uikit.css',
        'spinkit.css',
        'cresenity/cresenity.main.bs4.css?v=' . uniqid(),
        'administrator/theme-material/theme-material.css?v=' . uniqid(),
        'cresenity/administrator.css?v=' . uniqid(),
    ],
    'scss' => [
        'scss.scss',
    ],
    'data' => [
        'table' => [
            //"dom" => "<'row'<'col-sm-6'l><'col-sm-6'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
            //"dom" => "<fr\"\"l>t<\"F\"<\".footer_action\">p>",
            'dom' => "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        ],
    ],
];
