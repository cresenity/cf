<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
class CEmoji_AbstractIndex implements CEmoji_IndexInterface {
    /**
     * @var array
     */
    protected $emojis = [];

    /**
     * @var array
     */
    protected $emojiUnicodes = [];

    /**
     * @var array
     */
    protected $emojiNames = [];

    /**
     * @var string
     */
    protected $emojiUnicodeRegex = '';

    /**
     * @var string
     */
    protected $emojiNameRegex = '';

    /**
     * @param string $unicode
     *
     * @return array
     */
    public function findByUnicode($unicode) {
        if (isset($this->emojiUnicodes[$unicode], $this->emojis[$this->emojiUnicodes[$unicode]])) {
            return $this->emojis[$this->emojiUnicodes[$unicode]];
        }
    }

    /**
     * @param string $name
     * @param array
     */
    public function findByName($name) {
        if (isset($this->emojiNames[$name], $this->emojis[$this->emojiNames[$name]])) {
            return $this->emojis[$this->emojiNames[$name]];
        }
    }

    /**
     * @return string
     */
    public function getEmojiUnicodeRegex() {
        return $this->emojiUnicodeRegex;
    }

    /**
     * @return string
     */
    public function getEmojiNameRegex() {
        return $this->emojiNameRegex;
    }

    /**
     * @return array
     */
    public function getEmojis() {
        return $this->emojis;
    }
}
