<?php

class CDebug_Collector_Channel_Slack extends CDebug_Collector_Channel_ChannelAbstract {
    /**
     * @param array $data
     *
     * @return void
     */
    public function send($data) {
        $webhookUrl = CF::config('collector.slack.webhookUrl');
        if (!$webhookUrl) {
            return;
        }
        CVendor_Slack::webhook($webhookUrl)
            ->setText($this->formatText($data))
            ->send();
    }

    /**
     * @param array $data
     *
     * @return string
     */
    protected function formatText($data) {
        $error = carr::get($data, 'error', 'Exception');
        $message = carr::get($data, 'message', '');
        $appCode = carr::get($data, 'appCode');
        $file = carr::get($data, 'file');
        $line = carr::get($data, 'line');

        $text = ":rotating_light: *{$error}*: {$message}";
        if ($appCode) {
            $text .= "\nApp: `{$appCode}`";
        }
        if ($file) {
            $text .= "\n`{$file}:{$line}`";
        }

        return $text;
    }
}
