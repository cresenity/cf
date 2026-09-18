<?php

defined('SYSPATH') or die('No direct access allowed.');

abstract class CAjax_Engine implements CAjax_EngineInterface {
    /**
     * @var CAjax_Method
     */
    protected $ajaxMethod;

    /**
     * @var array
     */
    protected $input;

    /**
     * @var array
     */
    protected $args;

    /**
     * @param CAjax_Method $ajaxMethod
     * @param null|array   $input      explicit input; null reads the request (GET/POST by the method's verb)
     */
    public function __construct(CAjax_Method $ajaxMethod, ?array $input = null) {
        $this->ajaxMethod = $ajaxMethod;
        if ($input !== null) {
            $this->input = $input;

            return;
        }
        $this->input = array_merge($_GET, $_POST);
        if (strtoupper($ajaxMethod->getMethod()) == 'GET') {
            $this->input = $_GET;
        }
        if (strtoupper($ajaxMethod->getMethod()) == 'POST') {
            $this->input = $_POST;
        }
    }

    public function setInput(array $input) {
        $this->input = $input;
    }

    /**
     * Get Input.
     *
     * @return array
     */
    public function getInput() {
        return $this->input;
    }

    /**
     * @return string
     */
    public function getMethod() {
        return $this->ajaxMethod->getMethod();
    }

    /**
     * Get Data.
     *
     * @return array
     */
    public function getData() {
        return $this->ajaxMethod->getData();
    }

    /**
     * Get Type.
     *
     * @return string
     */
    public function getType() {
        return $this->ajaxMethod->getType();
    }

    /**
     * Get args.
     *
     * @return array
     */
    public function getArgs() {
        return $this->ajaxMethod->getArgs();
    }

    /**
     * @return CAjax_Method
     */
    public function getAjaxMethod() {
        return $this->ajaxMethod;
    }

    /**
     * Convert response to JSON.
     *
     * @param int    $errCode
     * @param string $errMessage
     * @param array  $data
     *
     * @return \CHTTP_JsonResponse
     */
    public function toJsonResponse($errCode, $errMessage, $data = []) {
        return c::response()->json([
            'errCode' => $errCode,
            'errMessage' => $errMessage,
            'data' => $data,
        ]);
    }

    /**
     * Invoke a callback function with the given arguments.
     *
     * @param callable $callback
     * @param array    $args
     *
     * @return mixed
     */
    public function invokeCallback($callback, array $args = []) {
        return c::call($callback, $args);
    }
}
