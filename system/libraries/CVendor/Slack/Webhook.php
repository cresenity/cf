<?php

use Illuminate\Contracts\Support\Arrayable;

/**
 * Pengirim pesan Slack lewat incoming webhook (https://api.slack.com/messaging/webhooks).
 */
class CVendor_Slack_Webhook implements Arrayable {
    private $url;

    private $text = '';

    private $username = '';

    private $iconEmoji = '';

    private $blocks = [];

    public function __construct($url) {
        $this->url = $url;
    }

    /**
     * @param string $text
     *
     * @return self
     */
    public function setText($text) {
        $this->text = $text;

        return $this;
    }

    /**
     * @param string $username
     *
     * @return self
     */
    public function setUsername($username) {
        $this->username = $username;

        return $this;
    }

    /**
     * @param string $iconEmoji mis. ':rotating_light:'
     *
     * @return self
     */
    public function setIconEmoji($iconEmoji) {
        $this->iconEmoji = $iconEmoji;

        return $this;
    }

    /**
     * @param array $blocks format Block Kit mentah (https://api.slack.com/block-kit)
     *
     * @return self
     */
    public function setBlocks(array $blocks) {
        $this->blocks = $blocks;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray() {
        $data = ['text' => $this->text];
        if ($this->username !== '') {
            $data['username'] = $this->username;
        }
        if ($this->iconEmoji !== '') {
            $data['icon_emoji'] = $this->iconEmoji;
        }
        if ($this->blocks) {
            $data['blocks'] = $this->blocks;
        }

        return $data;
    }

    /**
     * @return CHTTP_Client_Response
     */
    public function send() {
        return CHTTP::client()->connectTimeout(2)->timeout(3)->post($this->url, $this->toArray());
    }
}
