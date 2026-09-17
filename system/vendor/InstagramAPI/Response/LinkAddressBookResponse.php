<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * LinkAddressBookResponse.
 *
 * @method Model\Suggestion[] getItems()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isItems()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setItems(Model\Suggestion[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetItems()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class LinkAddressBookResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'items' => 'Model\Suggestion[]',
    ];
}
