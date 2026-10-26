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
    | Channel tujuan tambahan untuk exception, di luar devcloud. Kosong/tidak diisi = ['devcloud']
    | (perilaku sebelum ini ada, tidak berubah). Isi array nama channel untuk menambah/mengganti
    | tujuan, mis. ['devcloud', 'slack'] (dua-duanya), ['slack'] (Slack saja, tanpa devcloud sama
    | sekali), ['discord'], ['sentry']. Channel non-devcloud lihat
    | CDebug_Collector_Channel_ChannelAbstract::make() - masing-masing butuh config sendiri di
    | 'slack'/'discord'/'sentry' di bawah, dan DIKIRIM SINKRON (HTTP POST biasa dengan timeout
    | pendek, bukan fire-and-forget seperti exceptionPush/devcloud) - kegagalan satu channel non-
    | devcloud tidak menggagalkan channel lain atau collect() itu sendiri, tapi channel 'devcloud'
    | gagal tetap fatal utk collect() (perilaku lama, lihat dispatch()) dan menghentikan channel
    | SETELAHNYA di daftar ini.
    */
    'exceptionChannels' => [],

    /*
    | Kolektor pemakaian API @deprecated (CDebug::collector()->collectDeprecated() / CF::deprecated()).
    | Default MATI; app menyalakannya di default/config/collector.php miliknya. `deprecatedPush` = URL
    | endpoint devcloud (mis. 'https://devcloud.cresenity.com/v1/deprecations'), memakai key yang sama
    | dengan exceptionPush (devcloud.phpExceptionCollector.key); tanpa URL entri ditulis ke
    | temp/collector/deprecated untuk ditarik devcloud. `deprecatedLimit` = maksimal entri unik per proses.
    | `deprecatedChannels` mengikuti pola exceptionChannels di atas, independen dari exceptionChannels.
    */
    'deprecated' => false,
    'deprecatedPush' => false,
    'deprecatedLimit' => 50,
    'deprecatedChannels' => [],

    /*
    | Config per channel non-devcloud, dipakai bersama oleh exceptionChannels & deprecatedChannels.
    | 'webhookUrl'/'dsn' bernilai false/kosong berarti channel itu dilewati (send() no-op) walau
    | namanya ada di *Channels - menghindari exception collector sendiri gagal gara-gara config lupa diisi.
    */
    'slack' => ['webhookUrl' => false],
    'discord' => ['webhookUrl' => false],
    'sentry' => ['dsn' => false],
];
