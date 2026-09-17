<?php

/**
 * Description of collector.
 *
 * @author Hery
 */
return [
    'exception' => false,

    /*
    | Push exception langsung ke devcloud (CDebug_Collector_Exception::pushToDevcloud())
    | sebelum jatuh ke file+SSH-pull yang sudah ada. Default MATI (false) - sebuah app
    | menyalakannya secara eksplisit di default/config/collector.php miliknya sendiri
    | dengan mengisi URL endpoint devcloud-nya langsung di sini, mis.
    | 'https://devcloud.cresenity.com/v1/exceptions' - bukan sekadar flag boolean, supaya
    | menyalakan push dan menentukan ke mana perginya adalah satu keputusan yang sama, tidak
    | bisa "menyala" tanpa tujuan yang jelas. Key autentikasinya (app.php_exception_ingest_key)
    | tetap terpisah lewat env.php - devcloud.phpExceptionCollector.key (system/config/
    | devcloud.php) - supaya rahasia tidak ikut ke config file yang bisa saja ter-commit.
    */
    'exceptionPush' => false,
];
