<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
interface CEmoji_IndexInterface {
    /**
     * @param string $unicode
     *
     * @return array
     */
    public function findByUnicode($unicode);

    /**
     * @param string $name
     * @param array
     */
    public function findByName($name);

    /**
     * @return string
     */
    public function getEmojiUnicodeRegex();

    /**
     * @return string
     */
    public function getEmojiNameRegex();

    /**
     * @return array
     */
    public function getEmojis();
}
