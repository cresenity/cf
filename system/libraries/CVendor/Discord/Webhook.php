<?php

use Illuminate\Contracts\Support\Arrayable;

class CVendor_Discord_Webhook implements Arrayable {
    private $url;

    private $content = '';

    private $username = '';

    private $avatarUrl = '';

    private $tts = false;

    private $embeds = [];

    public function __construct($url) {
        $this->url = $url;
    }

    /**
     * Set message to send.
     *
     * @param string $content
     *
     * @return self
     */
    public function setContent($content) {
        $this->content = $content;

        return $this;
    }

    /**
     * Set username.
     *
     * @param string $username
     *
     * @return self
     */
    public function setUsername($username) {
        $this->username = $username;

        return $this;
    }

    /**
     * Set avatar.
     *
     * @param string $avatarUrl
     *
     * @return self
     */
    public function setAvatar($avatarUrl) {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    /**
     * Set TTS.
     *
     * @param bool $tts
     *
     * @return void
     */
    public function setTTS($tts) {
        $this->tts = $tts;

        return $this;
    }

    /**
     * @return CVendor_Discord_Webhook_Embed
     */
    public function addEmbed() {
        $embed = new CVendor_Discord_Webhook_Embed();
        $this->embeds[] = $embed;

        return $embed;
    }

    /**
     * Get fields as an array.
     *
     * @return array
     */
    public function toArray() {
        return [
            'content' => $this->content,
            'username' => $this->username,
            'avatar_url' => $this->avatarUrl,
            'tts' => $this->tts,

            'embeds' => c::collect($this->embeds)->map(function ($item) {
                if ($item instanceof CVendor_Discord_Webhook_Embed) {
                    return $item->toArray();
                }

                return $item;
            })->all()
        ];
    }

    /**
     * @return CHTTP_Client_Response
     */
    public function send() {
        return CHTTP::client()->connectTimeout(2)->timeout(3)->post($this->url, $this->toArray());
    }
}
