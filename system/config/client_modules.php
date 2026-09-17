<?php

$modules = require __DIR__ . '/../data/assets-module.php';

// Definisi 'cresenity' ini dulu ada di modules/cresenity/config/client_modules.php dan menimpa
// entri sejenis di assets-module.php; dipertahankan di sini supaya modul yang dimuat tidak berubah.
$modules['cresenity'] = [
    'css' => [
        'spinkit.css',
    ],
    'requirements' => ['block-ui'],
];

return $modules;
