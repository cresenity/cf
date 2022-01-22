<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Feb 16, 2018, 9:59:28 PM
 */
class CAjax_Method implements CInterface_Jsonable {
    public $name = '';

    public $method = 'GET';

    /**
     * @var array
     */
    public $data = [];

    public $type = '';

    public $target = '';

    public $param = [];

    public $args = [];

    public function __construct($options = []) {
        if ($options == null) {
            $options = [];
        }
        $this->fromArray($options);
    }

    /**
     * @param string $key
     * @param array  $data
     *
     * @return $this
     */
    public function setData($key, $data) {
        $this->data[$key] = $data;

        return $this;
    }

    /**
     * @return array
     */
    public function getData() {
        return $this->data;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type) {
        if (class_exists($type)) {
            $type = c::classBasename($type);
        }

        $this->type = $type;

        return $this;
    }

    /**
     * @param array $type
     *
     * @return $this
     */
    public function setArgs(array $args) {
        $this->args = $args;

        return $this;
    }

    /**
     * @return array
     */
    public function getArgs() {
        return $this->args;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param string $method
     *
     * @return $this
     */
    public function setMethod($method) {
        $this->method = $method;

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * @param int $jsonOption
     *
     * @return string
     */
    public function makeUrl($jsonOption = 0) {
        //generate ajax_method
        $json = $this->toJson($jsonOption);

        //save this object to file.
        $ajaxMethod = date('Ymd') . cutils::randmd5();
        $disk = CTemporary::disk();
        $filename = $ajaxMethod . '.tmp';

        $file = CTemporary::getPath('ajax', $filename);
        $disk->put($file, $json);

        $base_url = curl::httpbase();

        return $base_url . 'cresenity/ajax/' . $ajaxMethod;
    }

    /**
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0) {
        return json_encode($this, $options);
    }

    /**
     * @param string $json
     *
     * @return $this
     */
    public function fromJson($json) {
        $jsonArray = json_decode($json, true);

        return $this->fromArray($jsonArray);
    }

    public function fromArray(array $array) {
        $this->data = carr::get($array, 'data', []);
        $this->method = carr::get($array, 'method', 'GET');
        $this->type = carr::get($array, 'type');

        return $this;
    }

    /**
     * @param string $json
     *
     * @return CAjax_Method
     */
    public static function createFromJson($json) {
        $instance = new CAjax_Method();

        return $instance->fromJson($json);
    }

    /**
     * @param CAjax_Method $ajaxMethod
     * @param null|array   $input
     *
     * @throws CAjax_Exception
     *
     * @return CAjax_Engine
     */
    public static function createEngine(CAjax_Method $ajaxMethod, $input = null) {
        $class = 'CAjax_Engine_' . $ajaxMethod->type;

        if (!class_exists($class)) {
            throw new CAjax_Exception(c::__('class ajax engine :class not found', [':class' => $class]));
        }
        $engine = new $class($ajaxMethod, $input);

        return $engine;
    }

    /**
     * @param string $input
     *
     * @return string
     */
    public function executeEngine($input = null) {
        $engine = self::createEngine($this, $input);
        $response = $engine->execute();
        if ($response != null && $response instanceof CHTTP_JsonResponse) {
            return $response->getContent();
        }

        return $response;
    }
}
