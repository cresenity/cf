<?php

use Illuminate\Contracts\Support\Jsonable;

defined('SYSPATH') or die('No direct access allowed.');

class CAjax_Method implements Jsonable {
    public $name = '';

    public $method = 'GET';

    /**
     * @var array
     */
    public $data = [];

    /**
     * @var string
     */
    public $type = '';

    /**
     * @var string
     */
    public $target = '';

    /**
     * @var array
     */
    public $param = [];

    /**
     * @var array
     */
    public $args = [];

    /**
     * @var int|DateTimeInterface
     */
    public $expiration;

    /**
     * @var bool|array
     */
    public $auth;

    public function __construct($options = []) {
        $this->auth = false;
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
        // the method is stored as JSON, so a closure has to travel as its serialize() string
        if ($data instanceof Closure) {
            $data = c::toSerializableClosure($data);
        }
        if ($data instanceof CFunction_SerializableClosure || $data instanceof \Opis\Closure\SerializableClosure) {
            $data = serialize($data);
        }
        $this->data[$key] = $data;

        return $this;
    }

    public function enableAuth() {
        $guard = c::app()->auth()->guard();
        $auth = true;
        if ($guard) {
            $auth = [];
            $auth['guard'] = c::app()->auth()->guardName();
        }
        if ($guard instanceof CAuth_Guard_SessionGuard) {
            $auth['id'] = $guard->id();
        }
        $this->auth = $auth;

        return $this;
    }

    /**
     * @param int|DateTimeInterface $expiration
     *
     * @return $this
     */
    public function setExpiration($expiration) {
        $expiration = $expiration instanceof DateTimeInterface
        ? $expiration->getTimestamp() : $expiration;
        $this->expiration = $expiration;

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
        $ajaxMethod = $this->store($jsonOption);

        $base_url = curl::httpbase();

        return $base_url . 'cresenity/ajax/' . $ajaxMethod;
    }

    /**
     * Save this method to its temp file and return the generated id (the last segment of makeUrl()).
     *
     * @param int $jsonOption
     *
     * @return string
     */
    public function store($jsonOption = 0) {
        $json = $this->toJson($jsonOption);

        $ajaxMethod = date('Ymd') . cutils::randmd5();
        $disk = CTemporary::disk();
        $file = CAjax::temporaryFile($ajaxMethod);
        $disk->put($file, $json);

        if (!$disk->exists($file)) {
            // Retry once - covers a transient write failure (e.g. a brief hiccup on an
            // app whose temp disk is cloud-backed, like S3, rather than local filesystem).
            // put()'s return value was never checked here, so a failed write previously
            // meant makeUrl() handed back a token whose backing file never actually
            // existed - cresenity/ajax/{token} then 404s the moment it's used, with
            // nothing recorded anywhere to explain why (confirmed live, tribelio
            // collector #14389: DownloadProgress export button clicked minutes after
            // page load, 404 on a token whose store() had run fine with no error).
            $disk->put($file, $json);
            if (!$disk->exists($file)) {
                CF::log(CLogger::WARNING, 'CAjax_Method::store() - gagal menulis payload ke temp disk setelah retry (file: ' . $file . ')');
            }
        }

        return $ajaxMethod;
    }

    public function toArray() {
        return [
            'name' => $this->name,
            'method' => $this->method,
            'type' => $this->type,
            'target' => $this->target,
            'param' => $this->param,
            'args' => $this->args,
            'expiration' => $this->expiration,
            'auth' => $this->auth,
            'data' => $this->data,
        ];
    }

    /**
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0) {
        return json_encode($this->toArray(), $options);
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
        $this->name = carr::get($array, 'name');
        $this->args = carr::get($array, 'args');
        $this->target = carr::get($array, 'target');
        $this->expiration = carr::get($array, 'expiration');
        $this->auth = carr::get($array, 'auth');

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
     * @param null|array $input
     *
     * @throws CAjax_Exception
     *
     * @return CAjax_Engine
     */
    public static function createEngine(CAjax_Method $ajaxMethod, $input = null) {
        $type = $ajaxMethod->type;
        if ($type == 'SearchSelect') {
            $type = CAjax::TYPE_SELECT_SEARCH;
        }
        $class = 'CAjax_Engine_' . $type;
        if (!class_exists($class)) {
            throw new CAjax_Exception(c::__('class ajax engine :class not found', [':class' => $class]));
        }
        $engine = new $class($ajaxMethod, $input);

        return $engine;
    }

    public function getExpiration() {
        return $this->expiration;
    }

    protected function checkAuth() {
        if ($this->auth) {
            // auth === true (enableAuth() without a resolvable guard) means the default guard
            $guard = c::auth(is_array($this->auth) ? carr::get($this->auth, 'guard') : null);
            if ($guard->check()) {
                if (get_class($guard) == CAuth_Guard_SessionGuard::class) {
                    if (carr::get($this->auth, 'id') != $guard->id()) {
                        return false;
                    }
                }

                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * @param string $input
     *
     * @return string
     */
    public function executeEngine($input = null) {
        $expiration = $this->getExpiration();

        if ($expiration && CCarbon::now()->getTimestamp() > $expiration) {
            throw new CAjax_Exception_ExpiredAjaxException('Expired Link');
        }

        if (!$this->checkAuth()) {
            throw new CAjax_Exception_AuthAjaxException('Unauthenticated');
        }

        $engine = self::createEngine($this, $input);
        $response = $engine->execute();
        if ($response != null && $response instanceof CHTTP_JsonResponse) {
            return $response;
        }

        return $response;
    }
}
