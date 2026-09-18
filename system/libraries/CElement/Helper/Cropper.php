<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * Description of Cropper.
 */
class CElement_Helper_Cropper extends CElement_Element {
    //use CElement_Trait_Template;
    use CElement_Trait_UseViewTrait;

    /**
     * @var int|float|null
     */
    protected $cropperWidth;

    /**
     * @var int|float|null
     */
    protected $cropperHeight;

    /**
     * @var bool
     */
    protected $cropperResizable;

    /**
     * @var CElement_FormInput|null
     */
    protected $owner;

    /**
     * @var string|null
     */
    protected $imgSrc;

    /**
     * @param string $id
     * @param string $tag
     *
     * @return void
     */
    public function __construct($id = '', $tag = 'div') {
        parent::__construct($id, $tag);
        //$this->templateName = 'CElement/Helper/Cropper';
        $this->view = 'cresenity/element/helper/cropper';
        $dataModule = [
            'css' => [
                'plugins/cropper/cropper.css',
            ],
            'js' => [
                'plugins/cropper/cropper.js',
            ],
        ];
        CManager::registerModule('cropper', $dataModule);

        $this->cropperResizable = true;

        $this->onBeforeParse(function (CView_View $view) {
            $view->with('id', $this->id);
            $view->with('imgSrc', $this->imgSrc);
            $view->with('cropperWidth', $this->cropperWidth);
            $view->with('cropperHeight', $this->cropperHeight);
            $view->with('cropperResizable', $this->cropperResizable);
        });
    }

    /**
     * @param CElement_FormInput $owner
     *
     * @return $this
     */
    public function setOwner($owner) {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @param int|float $width
     * @param int|float $height
     *
     * @return $this
     */
    public function setSize($width, $height) {
        $this->cropperWidth = $width;
        $this->cropperHeight = $height;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setCropperResizable($bool = true) {
        $this->cropperResizable = $bool;

        return $this;
    }

    /**
     * @return int|float|null
     */
    public function getCropperWidth() {
        return $this->cropperWidth;
    }

    /**
     * @return int|float|null
     */
    public function getCropperHeight() {
        return $this->cropperHeight;
    }

    /**
     * @return int|float
     */
    public function getAspectRatio() {
        return $this->cropperWidth / $this->cropperHeight;
    }

    /**
     * @return bool
     */
    public function getCropperResizable() {
        return $this->cropperResizable;
    }
}
