<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Jun 24, 2018, 6:52:38 PM
 */
//@codingStandardsIgnoreStart
trait CTrait_Compat_Element_FormInput_Image {
    /**
     * @param string $imgsrc
     *
     * @return $this
     *
     * @deprecated use setImgSrc
     */
    public function set_imgsrc($imgsrc) {
        /** @var CElement_FormInput_Image $this */
        return $this->setImgSrc($imgsrc);
    }

    /**
     * @param int $maxwidth
     *
     * @return $this
     *
     * @deprecated use setMaxWidth
     */
    public function set_maxwidth($maxwidth) {
        /** @var CElement_FormInput_Image $this */
        return $this->setMaxWidth($maxwidth);
    }

    /**
     * @param int $maxheight
     *
     * @return $this
     *
     * @deprecated use setMaxHeight
     */
    public function set_maxheight($maxheight) {
        /** @var CElement_FormInput_Image $this */
        return $this->setMaxHeight($maxheight);
    }

    /**
     * @param bool $bool
     *
     * @return $this
     *
     * @deprecated use setDisabledUpload
     */
    public function set_disabled_upload($bool) {
        /** @var CElement_FormInput_Image $this */
        return $this->setDisabledUpload($bool);
    }
}
