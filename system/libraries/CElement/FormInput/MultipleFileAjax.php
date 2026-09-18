<?php

/**
 * Description of ImageAjax.
 */
class CElement_FormInput_MultipleFileAjax extends CElement_FormInput {
    use CElement_Trait_UseViewTrait;

    /**
     * @var string
     */
    protected $imgSrc;

    /**
     * @var int|string
     */
    protected $maxWidth;

    /**
     * @var int|string
     */
    protected $maxHeight;

    /**
     * @var int
     */
    protected $maxUploadSize;

    /**
     * @var array
     */
    protected $allowedExtension;

    /**
     * @var null|callable|CFunction_SerializableClosure
     */
    protected $validationCallback;

    /**
     * @var int
     */
    protected $limitFile;

    /**
     * @var bool
     */
    protected $disabledUpload;

    /**
     * @var null|CElement_Helper_Cropper
     */
    protected $cropper;

    /**
     * @var array
     */
    protected $files;

    /**
     * Declared but never assigned/read anywhere in this class.
     *
     * @var mixed
     */
    protected $link;

    /**
     * @var bool
     */
    protected $removeLink;

    /**
     * @var array
     */
    protected $customControl;

    /**
     * @var array
     */
    protected $customControlValue;

    /**
     * @var int|null
     */
    protected $maximum;

    /**
     * @var string
     */
    protected $accept;

    /**
     * @var bool
     */
    protected $withInfo;

    /**
     * @var bool
     */
    protected $asFileAjax = false;

    /**
     * @param string|null $id
     *
     * @return void
     */
    public function __construct($id) {
        parent::__construct($id);
        $this->type = 'image';
        $this->tag = 'div';
        $this->imgSrc = '';
        $this->maxWidth = '200';
        $this->maxHeight = '150';
        $this->maxUploadSize = 0;
        $this->allowedExtension = [];
        $this->limitFile = 10;
        $this->accept = '*/*';
        $this->disabledUpload = false;
        $this->view = 'cresenity/element/form-input/multiple-file-ajax';
        $this->removeLink = true;
        $this->files = [];
        $this->maximum = null;
        $this->customControl = [];
        $this->customControlValue = [];
        $this->withInfo = false;
        $this->asFileAjax = false;
        c::manager()->registerModule('mime-icons');
        $this->onBeforeParse(function (CView_View $view) {
            $ajaxName = $this->name;
            $ajaxName = str_replace('[', '-', $ajaxName);
            $ajaxName = str_replace(']', '-', $ajaxName);

            $ajaxUrl = CAjax::createMethod()->setType(CAjax_Engine_FileUpload::class)
                ->setData('inputName', $ajaxName)
                ->setData('allowedExtension', $this->allowedExtension)
                ->setData('validationCallback', $this->validationCallback)
                ->setData('withInfo', $this->withInfo)
                ->setData('asFileAjax', $this->asFileAjax)
                ->makeUrl();

            $view->with('id', $this->id);
            $view->with('imgSrc', $this->imgSrc);
            $view->with('maxWidth', $this->maxWidth);
            $view->with('maxHeight', $this->maxHeight);
            $view->with('maxUploadSize', $this->maxUploadSize);
            $view->with('limitFile', $this->limitFile);
            $view->with('disabledUpload', $this->disabledUpload);
            $view->with('preTag', $this->pretag());
            $view->with('postTag', $this->posttag());
            $view->with('name', $this->name);
            $view->with('value', $this->value);
            $view->with('ajaxName', $ajaxName);
            $view->with('ajaxUrl', $ajaxUrl);
            $view->with('files', $this->files);
            $view->with('maximum', $this->maximum == null ? 0 : $this->maximum);
            $view->with('removeLink', $this->removeLink);
            $view->with('customControl', $this->customControl);
            $view->with('customControlValue', $this->customControlValue);
            $view->with('cropper', $this->cropper);
            $view->with('accept', $this->accept);
            $view->with('asFileAjax', $this->asFileAjax);
        });
    }

    /**
     * @param bool $withInfo
     *
     * @return $this
     */
    public function setWithInfo($withInfo = true) {
        $this->withInfo = $withInfo;

        return $this;
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    public function html($indent = 0) {
        $templateHtml = $this->getViewHtml();
        $html = $templateHtml;
        if ($this->cropper != null) {
            $html .= $this->cropper->html();
        }

        return $html;
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    public function js($indent = 0) {
        $templateJs = $this->getViewJs();
        $js = $templateJs;
        if ($this->cropper != null) {
            $js .= $this->cropper->js();
        }

        return $js;
    }

    /**
     * @param int $size
     *
     * @return $this
     */
    public function setMaxUploadSize($size) {
        $this->maxUploadSize = $size;

        return $this;
    }

    /**
     * @param string|array $ext
     *
     * @return $this
     */
    public function setAllowedExtension($ext) {
        $arr = $ext;
        if (!is_array($arr)) {
            $arr = [$ext];
        }
        $this->allowedExtension = $arr;

        return $this;
    }

    /**
     * @param callable|Closure $callback
     *
     * @return $this
     */
    public function setValidationCallback($callback) {
        $this->validationCallback = c::toSerializableClosure($callback);

        return $this;
    }

    /**
     * @param int $limit
     *
     * @return $this
     */
    public function setLimitFile($limit) {
        $this->limitFile = $limit;

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setAsFileAjax($bool = true) {
        $this->asFileAjax = $bool;

        return $this;
    }

    /**
     * @return CElement_Helper_Cropper
     */
    public function cropper() {
        if ($this->cropper == null) {
            $this->cropper = new CElement_Helper_Cropper();
            $this->cropper->setOwner($this);
        }

        return $this->cropper;
    }

    /**
     * @param CModel_Collection $collection
     * @param string            $inputName
     *
     * @return $this
     */
    public function setFileFromResources(CModel_Collection $collection, $inputName = '') {
        foreach ($collection as $model) {
            $this->addFile($model->getUrl(), $inputName, $model->getKey());
        }

        return $this;
    }

    /**
     * @param string     $fileUrl
     * @param string     $inputName
     * @param int|string $inputValue
     * @param string     $fileName
     *
     * @return $this
     */
    public function addFile($fileUrl, $inputName = '', $inputValue = '', $fileName = '') {
        $arr = [];
        $arr['input_name'] = $inputName;
        $arr['input_value'] = $inputValue;
        $arr['file_url'] = $fileUrl;
        $arr['file_name'] = $fileName;

        $this->files[] = $arr;

        return $this;
    }

    /**
     * @param CAjax_FileAjax $fileAjax
     *
     * @return $this
     */
    public function addFileAjax(CAjax_FileAjax $fileAjax) {
        $arr = [];
        $arr['input_name'] = $fileAjax->getIdentifier();
        $arr['input_value'] = $fileAjax->getIdentifier();
        $arr['file_url'] = $fileAjax->getUrl();
        $arr['file_name'] = $fileAjax->getFileName();
        $this->files[] = $arr;

        return $this;
    }

    /**
     * @param string $control
     * @param string $inputName
     * @param string $inputLabel
     *
     * @return $this
     */
    public function addCustomControl($control, $inputName, $inputLabel) {
        $arr = [];
        $arr['control'] = $control;
        $arr['input_name'] = $inputName;
        $arr['input_label'] = $inputLabel;
        $this->customControl[] = $arr;

        return $this;
    }

    /**
     * @param string $inputName
     * @param string $controlName
     * @param mixed  $inputValue
     *
     * @return $this
     */
    public function addCustomControlValue($inputName, $controlName, $inputValue) {
        $this->customControlValue[$inputName][$controlName] = $inputValue;

        return $this;
    }

    /**
     * @param int|null $maximum
     *
     * @return $this
     */
    public function setMaximum($maximum = null) {
        $this->maximum = $maximum;

        return $this;
    }

    /**
     * @param string $accept
     *
     * @return $this
     */
    public function setAccept($accept) {
        $this->accept = $accept;

        return $this;
    }
}
