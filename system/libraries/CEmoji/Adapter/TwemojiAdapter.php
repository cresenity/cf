<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since May 16, 2019, 9:57:59 PM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
class CEmoji_Adapter_TwemojiAdapter extends CEmoji_AbstractAdapter {

    protected $baseUrl = '//twemoji.maxcdn.com/';

    /**
     * Regular expression for finding twemoji names (surrounded by double colon)
     */
    const TWEMOJI_REGEX = '/(:[a-zA-Z0-9_]*:)/';

    /**
     * Skeleton for image html tag
     *
     * @var string
     */
    const IMAGE_TAG = '<img src="%s" alt="%s" class="%s">';

    /**
     * Supported icon sizes for twemoji.
     *
     * @var array
     */
    protected $supportedIconSizes = [16, 36, 72];

    /**
     * Object of mappings twemoji name to unicode representation and description of twemoji
     *
     * @var CEmoji_Adapter_Twemoji_TwemojiIndex
     */
    protected $index;

    /**
     * @param int $iconSize
     */
    public function __construct($iconSize = 16) {
        $this->iconSize = $iconSize;
        $this->validateIconSize();
        $this->index = new CEmoji_Adapter_Twemoji_TwemojiIndex();
    }

    /**
     * @param string $string
     * @param string $imageHtmlTemplate
     *
     * @return string
     */
    public function replaceEmojiWithImages($string, $imageHtmlTemplate = null) {
        // NB: Named emoji should be replaced first as the string will then contain them in the image alt tags
        // Replace named emoji, e.g. ":smile:"
        $string = preg_replace_callback($this->getIndex()->getEmojiNameRegex(), function ($matches) use ($imageHtmlTemplate) {
            return $this->getEmojiImageByName($matches[1], $imageHtmlTemplate);
        }, $string);
        // Replace unicode emoji
        $string = preg_replace_callback($this->getIndex()->getEmojiUnicodeRegex(), function ($matches) use ($imageHtmlTemplate) {
            return $this->getEmojiImageByUnicode($matches[0], $imageHtmlTemplate);
        }, $string);
        return $string;
    }

    /**
     * @param string $name
     * @param string $imageHtmlTemplate
     *
     * @return string
     */
    public function getEmojiImageByUnicode($unicode, $imageHtmlTemplate = null) {
        $emoji = $this->index->findByUnicode($unicode);
        return $this->renderTemplate($emoji, $imageHtmlTemplate);
    }

    /**
     * @param string $name
     * @param string $imageHtmlTemplate
     *
     * @return string
     */
    public function getEmojiImageByName($name, $imageHtmlTemplate = null) {
        $emoji = $this->index->findByName($name);
        return $this->renderTemplate($emoji, $imageHtmlTemplate);
    }

    /**
     * Returns generated url of given twemoji name (surrounded by double colon).
     *
     * @param $twemojiName
     * @return string
     */
    public function getUrl($twemojiName) {
        return sprintf(
                self::TWEMOJI_URL, $this->iconSize, $this->getUnicode($twemojiName)
        );
    }

    /**
     * Returns unicode representation of twemoji name (surrounded by double colon).
     *
     * @param $twemojiName
     * @return string
     */
    public function getUnicode($twemojiName) {
        return $this->twemojiIndex[$twemojiName]['unicode'];
    }

    /**
     * Returns description of given twemoji name (surrounded by double colon).
     *
     * @param $twemojiName
     * @return string
     */
    public function getDescription($twemojiName) {
        return $this->twemojiIndex[$twemojiName]['description'];
    }

    /**
     * Returns image of twemoji name (surrounded by double colon).
     *
     * @param string $twemojiName
     * @param string|array $classNames
     * @return string
     */
    public function getImage($twemojiName, $classNames = '') {
        return $this->makeImage($twemojiName, $classNames);
    }

    /**
     * Prints image of twemoji name (surrounded by double colon).
     *
     * @param string $twemojiName
     * @param string|array $classNames
     */
    public function image($twemojiName, $classNames = '') {
        echo $this->makeImage($twemojiName, $classNames);
    }

    /**
     * Replaces twemoji names (surrounded by double colon) in text with corresponding images.
     *
     * @param string $text
     * @return string
     */
    public function parseText($text, $classNames = '') {
        return preg_replace_callback(self::TWEMOJI_REGEX, function($matches) use ($classNames) {
            return $this->getImage($matches[1], $classNames);
        }, $text);
    }

    /**
     * Returns formatted text of image html tag for given twemoji name (surrounded by double colon)
     * with optional classes applied to it.
     *
     * @param string $twemojiName
     * @param string|array $classNames
     * @return string
     */
    private function makeImage($twemojiName, $classNames = '') {
        return sprintf(
                self::IMAGE_TAG, $this->getUrl($twemojiName), $this->getDescription($twemojiName), is_array($classNames) ? implode(' ', $classNames) : $classNames
        );
    }

}
