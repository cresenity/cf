<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * DiscoverChannelsResponse.
 *
 * @method mixed getAutoLoadMoreEnabled()
 * @method Model\Item[] getItems()
 * @method mixed getMessage()
 * @method mixed getMoreAvailable()
 * @method string getNextMaxId()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isAutoLoadMoreEnabled()
 * @method bool isItems()
 * @method bool isMessage()
 * @method bool isMoreAvailable()
 * @method bool isNextMaxId()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setAutoLoadMoreEnabled(mixed $value)
 * @method $this setItems(Model\Item[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setMoreAvailable(mixed $value)
 * @method $this setNextMaxId(string $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetAutoLoadMoreEnabled()
 * @method $this unsetItems()
 * @method $this unsetMessage()
 * @method $this unsetMoreAvailable()
 * @method $this unsetNextMaxId()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class DiscoverChannelsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'auto_load_more_enabled' => '',
        'items'                  => 'Model\Item[]',
        'more_available'         => '',
        'next_max_id'            => 'string',
    ];
}
