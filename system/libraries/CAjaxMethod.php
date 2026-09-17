<?php

/**
 * @see CAjax_Method
 * @deprecated since 1.2 use CAjax_Method
 */
class CAjaxMethod {
    use CTrait_Compat_AjaxMethod;

    public $name = '';

    public $method = 'GET';

    public $data = [];

    public $type = '';

    public $target = '';

    public $param = [];

    public function __construct() {
    }

    public static function factory() {
        return new CAjaxMethod();
    }

    public function setData($key, $data) {
        $this->data[$key] = $data;

        return $this;
    }

    public function setType($type) {
        $this->type = $type;

        return $this;
    }

    public function setMethod($method) {
        $this->method = $method;

        return $this;
    }

    public function makeUrl($indent = 0) {
        $js = CStringBuilder::factory()->setIndent($indent);
        //generate ajax_method
        //save this object to file.
        $json = json_encode($this);

        $ajax_method = date('Ymd') . cutils::randmd5();
        $disk = CTemporary::disk();
        $filename = $ajax_method . '.tmp';

        $file = CTemporary::getPath('ajax', $filename);
        $disk->put($file, $json);
        $base_url = curl::base();

        return $base_url . 'ccore/ajax/' . $ajax_method;
    }
}
