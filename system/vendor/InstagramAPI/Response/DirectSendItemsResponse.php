<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * DirectSendItemsResponse.
 *
 * @method mixed getAction()
 * @method mixed getMessage()
 * @method Model\DirectSendItemPayload[] getPayload()
 * @method string getStatus()
 * @method mixed getStatusCode()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isAction()
 * @method bool isMessage()
 * @method bool isPayload()
 * @method bool isStatus()
 * @method bool isStatusCode()
 * @method bool isZMessages()
 * @method $this setAction(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setPayload(Model\DirectSendItemPayload[] $value)
 * @method $this setStatus(string $value)
 * @method $this setStatusCode(mixed $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetAction()
 * @method $this unsetMessage()
 * @method $this unsetPayload()
 * @method $this unsetStatus()
 * @method $this unsetStatusCode()
 * @method $this unsetZMessages()
 */
class DirectSendItemsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'action'      => '',
        'status_code' => '',
        'payload'     => 'Model\DirectSendItemPayload[]',
    ];
}
