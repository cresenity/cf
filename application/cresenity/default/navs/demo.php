<?php

return [
    [
        'name' => 'dashboard',
        'label' => c::__('Dashboard'),
        'icon' => 'ti-home',
        'uri' => 'demo/dashboard/index',
    ],
    [
        'name' => 'app',
        'label' => c::__('App'),
        'icon' => 'ti-desktop',
        'subnav' => include dirname(__FILE__) . '/demo/app.php',
    ],
    [
        'name' => 'elements',
        'label' => c::__('Elements'),
        'icon' => 'ti-layout',
        'subnav' => include dirname(__FILE__) . '/demo/elements.php',
    ],
    [
        'name' => 'controls',
        'label' => c::__('Controls'),
        'icon' => 'ti-control-record',
        'subnav' => include dirname(__FILE__) . '/demo/controls.php',
    ],
    [
        'name' => 'listener',
        'label' => c::__('Listener'),
        'icon' => 'ti-bolt',
        'subnav' => include dirname(__FILE__) . '/demo/listener.php',
    ],
    [
        'name' => 'model',
        'label' => c::__('Model'),
        'icon' => 'ti-server',
        'subnav' => include dirname(__FILE__) . '/demo/model.php',
    ],
    [
        'name' => 'view',
        'label' => c::__('View'),
        'icon' => 'ti-eye',
        'subnav' => include dirname(__FILE__) . '/demo/view.php',
    ],
    [
        'name' => 'image',
        'label' => c::__('Image'),
        'icon' => 'ti-image',
        'subnav' => include dirname(__FILE__) . '/demo/image.php',
    ],
    [
        'name' => 'cresjs',
        'label' => c::__('Cres JS'),
        'icon' => 'ti-pulse',
        'subnav' => include dirname(__FILE__) . '/demo/cresjs.php',
    ],
    [
        'name' => 'report',
        'label' => c::__('Report'),
        'icon' => 'ti-bar-chart',
        'subnav' => include dirname(__FILE__) . '/demo/report.php',
    ],
    [
        'name' => 'module',
        'label' => c::__('Module'),
        'icon' => 'ti-package',
        'subnav' => include dirname(__FILE__) . '/demo/module.php',
    ],
    [
        'name' => 'email',
        'label' => c::__('Email'),
        'icon' => 'ti-mail',
        'subnav' => include dirname(__FILE__) . '/demo/email.php',
        'badge' => 'new',
    ],
    [
        'name' => 'utils',
        'label' => c::__('Utils'),
        'icon' => 'ti-settings',
        'subnav' => include dirname(__FILE__) . '/demo/utils.php',
        'badge' => 'new',
    ],
    // [
    //     'name' => 'system',
    //     'label' => c::__('System'),
    //     'subnav' => include dirname(__FILE__) . '/demo/system.php',
    // ],
];
