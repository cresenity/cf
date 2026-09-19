<?php

class CWebhook_Server {
    /**
     * Mulai panggilan webhook keluar dengan konfigurasi `webhook.server.<name>`.
     *
     * @param string $name
     *
     * @return CWebhook_Server_WebhookCall
     */
    public static function create($name = 'default') {
        return CWebhook_Server_WebhookCall::create($name);
    }
}
