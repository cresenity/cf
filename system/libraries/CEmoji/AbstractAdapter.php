<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @deprecated 1.8
 */
abstract class CEmoji_AbstractAdapter implements CEmoji_AdapterInterface {
    protected $baseUrl;

    /**
     * @var string
     */
    protected $imageHtmlTemplate = '<img alt=":{{name}}:" class="emoji" src="{{baseUrl}}{{size}}x{{size}}/{{unicode}}.png">';

    /**
     * Supported icon sizes for emoji. empty array for support all size.
     *
     * @var array
     */
    protected $supportedIconSizes = [];

    /**
     * Icon size of emoji image.
     *
     * @var int
     */
    protected $iconSize;

    /**
     * Object of mappings twemoji name to unicode representation and description of twemoji.
     *
     * @var CEmoji_AbstractIndex
     */
    protected $index;

    /**
     * @return string
     */
    public function getImageHtmlTemplate() {
        return $this->imageHtmlTemplate;
    }

    /**
     * @param string $imageHtmlTemplate
     */
    public function setImageHtmlTemplate($imageHtmlTemplate) {
        $this->imageHtmlTemplate = $imageHtmlTemplate;
    }

    /**
     * @return CEmoji_IndexInterface
     */
    public function getIndex() {
        return $this->index;
    }

    /**
     * @param CEmoji_IndexInterface $index
     */
    public function setIndex(CEmoji_IndexInterface $index) {
        $this->index = $index;
    }

    /**
     * Throws an exception if icon size is not valid/supported.
     *
     * @throws Exception
     */
    protected function validateIconSize() {
        if (count($this->supportedIconSizes) == 0) {
            return true;
        }
        if (!in_array($this->iconSize, $this->supportedIconSizes)) {
            throw new Exception('Icon must be of size 16, 36 or 72');
        }
    }

    /**
     * @param array  $emoji
     * @param string $imageHtmlTemplate
     *
     * @return string
     */
    protected function renderTemplate(array $emoji, $imageHtmlTemplate = null) {
        $search = [
            '{{name}}',
            '{{unicode}}',
            '{{description}}',
            '{{baseUrl}}',
            '{{size}}',
        ];
        $replace = [
            $emoji['name'],
            $emoji['unicode'],
            $emoji['description'],
            $this->baseUrl,
            $this->iconSize,
        ];

        return str_replace($search, $replace, $imageHtmlTemplate !== null ? $imageHtmlTemplate : $this->imageHtmlTemplate);
    }

    public function getEmojiList() {
        return $this->index->getEmojis();
    }
}
