<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
class CEmoji {
    /**
     * @param int $iconSize
     *
     * @return \CEmoji_Adapter_TwemojiAdapter
     */
    public static function twemoji($iconSize = 16) {
        return new CEmoji_Adapter_TwemojiAdapter($iconSize);
    }
}
