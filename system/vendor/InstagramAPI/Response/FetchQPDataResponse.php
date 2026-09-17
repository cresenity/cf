<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * FetchQPDataResponse.
 *
 * @method mixed getErrorMsg()
 * @method string getExtraInfo()
 * @method mixed getMessage()
 * @method Model\QPData getQpData()
 * @method string getRequestStatus()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isErrorMsg()
 * @method bool isExtraInfo()
 * @method bool isMessage()
 * @method bool isQpData()
 * @method bool isRequestStatus()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setErrorMsg(mixed $value)
 * @method $this setExtraInfo(string $value)
 * @method $this setMessage(mixed $value)
 * @method $this setQpData(Model\QPData $value)
 * @method $this setRequestStatus(string $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetErrorMsg()
 * @method $this unsetExtraInfo()
 * @method $this unsetMessage()
 * @method $this unsetQpData()
 * @method $this unsetRequestStatus()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class FetchQPDataResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'request_status' => 'string',
        'extra_info'     => 'string',
        'qp_data'        => 'Model\QPData',
        'error_msg'      => '',
    ];
}
