<?php

class CDebug_Collector_Channel_Sentry extends CDebug_Collector_Channel_ChannelAbstract {
    /**
     * @param array $data
     *
     * @return void
     */
    public function send($data) {
        $dsn = CF::config('collector.sentry.dsn');
        if (!$dsn) {
            return;
        }
        $client = CVendor_Sentry::client($dsn);
        if (!$client->isValid()) {
            return;
        }
        $client->send($this->buildEvent($data));
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function buildEvent($data) {
        $error = carr::get($data, 'error', 'Exception');
        $message = (string) carr::get($data, 'message', '');

        return [
            'event_id' => str_replace('-', '', carr::get($data, 'uuid') ?: cstr::uuid()),
            'timestamp' => strtotime((string) carr::get($data, 'datetime', 'now')),
            'platform' => 'php',
            'level' => 'error',
            'message' => $error . ': ' . $message,
            'exception' => [
                'values' => [[
                    'type' => $error,
                    'value' => $message,
                ]],
            ],
            'server_name' => carr::get($data, 'domain'),
            'extra' => [
                'file' => carr::get($data, 'file'),
                'line' => carr::get($data, 'line'),
                'appCode' => carr::get($data, 'appCode'),
                'orgId' => carr::get($data, 'orgId'),
                'CFVersion' => carr::get($data, 'CFVersion'),
            ],
        ];
    }
}
