<?php

class CWebhook_Client {
    /**
     * Konfigurasi penerima webhook `webhook.client.<name>`.
     *
     * @param string $name
     *
     * @throws CWebhook_Client_Exception_InvalidConfigException
     *
     * @return CWebhook_Client_Config
     */
    public static function config($name = 'default') {
        $properties = CF::config('webhook.client.' . $name);
        if (!is_array($properties)) {
            throw CWebhook_Client_Exception_InvalidConfigException::couldNotFindConfig($name);
        }

        return new CWebhook_Client_Config(array_merge(['name' => $name], $properties));
    }

    /**
     * Proses request webhook masuk memakai konfigurasi bernama.
     *
     * @param CHTTP_Request $request
     * @param string        $name
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public static function process(CHTTP_Request $request, $name = 'default') {
        return (new CWebhook_Client_WebhookProcessor($request, static::config($name)))->process();
    }
}
