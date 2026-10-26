<?php

/**
 * Titik ekstensi channel tujuan tambahan untuk CDebug_Collector_Exception/Deprecated, selain
 * devcloud (yang ditangani langsung di CDebug_Collector_Exception::dispatch(), bukan lewat sini).
 */
abstract class CDebug_Collector_Channel_ChannelAbstract {
    /**
     * @param array $data payload yang sama dengan yang dikirim ke devcloud
     *
     * @return void
     */
    abstract public function send($data);

    /**
     * @param string $name 'slack'|'discord'|'sentry'
     *
     * @throws Exception nama channel tidak dikenal
     *
     * @return static
     */
    public static function make($name) {
        $map = [
            'slack' => CDebug_Collector_Channel_Slack::class,
            'discord' => CDebug_Collector_Channel_Discord::class,
            'sentry' => CDebug_Collector_Channel_Sentry::class,
        ];
        if (!isset($map[$name])) {
            throw new Exception("Channel collector tidak dikenal: {$name}");
        }
        $class = $map[$name];

        return new $class();
    }
}
