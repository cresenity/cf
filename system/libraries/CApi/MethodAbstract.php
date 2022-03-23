<?php

/**
 * Description of MethodAbstract.
 *
 * @author Hery
 */
abstract class CApi_MethodAbstract implements CInterface_Arrayable {
    protected $errCode = 0;

    protected $errMessage = '';

    protected $data = [];

    protected $request;

    protected $lang = null;

    protected $sessionId = null;

    protected $session;

    protected $apiRequest;

    /**
     * The middleware registered on the controller.
     *
     * @var array
     */
    protected $middleware = [];

    protected $sessionOptions = [
        'driver' => 'File',
        'expiration' => null,
    ];

    protected $orgId;

    protected $sessionIdParameter = 'sessionId';

    public function __construct($orgId = null, $sessionId = null, $request = null) {
        if ($orgId == null) {
            $orgId = CF::orgId();
        }
        $this->request = $request;

        $this->sessionId = $sessionId;
        $this->orgId = $orgId;
    }

    abstract public function execute();

    public function setApiRequest(CApi_HTTP_Request $apiRequest) {
        $this->apiRequest = $apiRequest;
    }

    /**
     * Register middleware on the controller.
     *
     * @param \Closure|array|string $middleware
     * @param array                 $options
     *
     * @return $this
     */
    public function middleware($middleware, array $options = []) {
        foreach ((array) $middleware as $m) {
            $this->middleware[] = [
                'middleware' => $m,
                'options' => &$options,
            ];
        }

        return $this;
    }

    /**
     * Get the middleware assigned to the controller.
     *
     * @return array
     */
    public function getMiddleware() {
        return $this->middleware;
    }

    public function toArray() {
        return $this->result();
    }

    public function request() {
        if ($this->request == null) {
            return array_merge($_GET, $_POST);
        }

        return $this->request;
    }

    public function sessionId() {
        if ($this->sessionId == null) {
            $this->sessionId = carr::get($this->request(), $this->sessionIdParameter);
        }

        return $this->sessionId;
    }

    public function result() {
        $return = [
            'errCode' => (int) $this->errCode,
            'errMessage' => $this->errMessage,
            'data' => $this->data,
        ];

        return $return;
    }

    public function getErrCode() {
        return $this->errCode;
    }

    public function getErrMessage() {
        return $this->errMessage;
    }

    public function hasError() {
        return $this->errCode > 0;
    }

    public function lang($message, $params = []) {
        return c::__($message, $params, $this->lang);
    }

    /**
     * @return CApi_Session
     */
    public function session() {
        if ($this->session == null) {
            $this->session = $this->getSession();
        }

        return $this->session;
    }

    protected function getSession() {
        return CApi::session($this->sessionId(), $this->sessionOptions);
    }

    protected function validate($rules, $messages = [], $data = null) {
        if ($data == null) {
            $data = $this->request();
        }
        $validator = CValidation::createValidator($data, $rules, $messages);
        if ($validator->fails()) {
            $error = $validator->errors()->first();
            $this->errCode++;
            $this->errMessage = $error;
        }
    }
}
