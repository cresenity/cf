<?php

/**
 * @deprecated 1.8
 */
class CXMPP_Ejabberd_Response {
    protected $errCode;

    protected $errMessage;

    protected $data;

    protected $rawResponse;

    protected $command;

    public function __construct($command, $body) {
        $this->errCode = 0;
        $this->errMessage = '';
        $this->data = [];
        $this->command = $command;
        if ($body instanceof Exception) {
            $this->errCode = 9999;
            $this->errMessage = $body->getMessage();
            $this->data = [];
        }

        $jsonDecoded = false;

        if (is_string($body)) {
            $this->rawResponse = $body;
            $jsonDecoded = json_decode($body, true);
            if (!is_array($jsonDecoded)) {
                if ($body == '"internal_error"') {
                    $body = ['status' => 'error', 'ejabberd' => $body];
                    $body = ['status' => 'success', 'ejabberd' => $body, 'command' => $this->command->getCommandName()];
                } else {
                    $body = ['status' => 'success', 'ejabberd' => $body];
                }
            } else {
                $body = $jsonDecoded;
            }
        }

        if (is_array($body)) {
            $status = carr::get($body, 'status', null);
            if ($status != null && $status != 'success') {
                $this->errCode = carr::get($body, 'code', 9998);
                $this->errMessage = carr::get($body, 'message', 'ejabberd error on guzzle exception');
            }
            $this->data = $body;
        }

        if (!is_array($body)) {
            cdbg::dd($body);
            $this->errCode = 10000;
            $this->errMessage = 'Unknown error';
        }
    }

    public function toArray() {
        return [
            'errCode' => $this->errCode,
            'errMessage' => $this->errMessage,
            'data' => $this->data(),
        ];
    }

    public function data() {
        $data = $this->data;

        if (strlen($this->rawResponse) > 0) {
            $data = array_merge($data, ['rawResponse' => $this->rawResponse]);
        }

        return $data;
    }

    public function toJson() {
        $array = $this->toArray();
        if (empty(carr::get($array, 'data', []))) {
            $array['data'] = new stdclass();
        }

        return json_encode($array);
    }

    public function hasError() {
        return $this->errCode > 0;
    }

    public function throwException() {
        throw new CXMPP_Ejabberd_Exception($this->errMessage, $this->errCode);
    }

    public function __toString() {
        return $this->toJson();
    }
}
