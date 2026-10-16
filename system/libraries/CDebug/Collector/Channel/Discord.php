<?php

class CDebug_Collector_Channel_Discord extends CDebug_Collector_Channel_ChannelAbstract {
    /**
     * @param array $data
     *
     * @return void
     */
    public function send($data) {
        $webhookUrl = CF::config('collector.discord.webhookUrl');
        if (!$webhookUrl) {
            return;
        }

        $error = carr::get($data, 'error', 'Exception');
        $message = (string) carr::get($data, 'message', '');
        $appCode = carr::get($data, 'appCode');
        $file = carr::get($data, 'file');
        $line = carr::get($data, 'line');

        $webhook = CVendor_Discord::webhook($webhookUrl);
        $embed = $webhook->addEmbed()
            ->setTitle(':rotating_light: ' . $error)
            ->setDescription(mb_substr($message, 0, 3500))
            ->setColor('ff4d6d')
            ->setTimestamp(date('c'));
        if ($appCode) {
            $embed->addField('App', $appCode, true);
        }
        if ($file) {
            $embed->addField('Lokasi', $file . ':' . $line, false);
        }
        $webhook->send();
    }
}
