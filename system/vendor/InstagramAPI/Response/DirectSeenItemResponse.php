<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * DirectSeenItemResponse.
 *
 * @method mixed getAction()
 * @method mixed getMessage()
 * @method Model\DirectSeenItemPayload getPayload()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isAction()
 * @method bool isMessage()
 * @method bool isPayload()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setAction(mixed $value)
 * @method $this setMessage(mixed $value)
 * @method $this setPayload(Model\DirectSeenItemPayload $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetAction()
 * @method $this unsetMessage()
 * @method $this unsetPayload()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class DirectSeenItemResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'action'  => '',
        'payload' => 'Model\DirectSeenItemPayload', // The number of unseen items.
    ];
}
