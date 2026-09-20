<?php

/**
 * Description of collector.
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

    /*
    | Kolektor pemakaian API @deprecated (CDebug::collector()->collectDeprecated() / CF::deprecated()).
    | Default MATI; app menyalakannya di default/config/collector.php miliknya. `deprecatedPush` = URL
    | endpoint devcloud (mis. 'https://devcloud.cresenity.com/v1/deprecations'), memakai key yang sama
    | dengan exceptionPush (devcloud.phpExceptionCollector.key); tanpa URL entri ditulis ke
    | temp/collector/deprecated untuk ditarik devcloud. `deprecatedLimit` = maksimal entri unik per proses.
    */
    'deprecated' => false,
    'deprecatedPush' => false,
    'deprecatedLimit' => 50,
];
