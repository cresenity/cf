<?php

class CVendor_Slack {
    /**
     * @param string $url incoming webhook URL Slack
     *
     * @return CVendor_Slack_Webhook
     */
    public static function webhook($url) {
        return new CVendor_Slack_Webhook($url);
    }
}
