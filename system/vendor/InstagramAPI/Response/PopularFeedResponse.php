<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * PopularFeedResponse.
 *
 * @method mixed getAutoLoadMoreEnabled()
 * @method Model\Item[] getItems()
 * @method string getMaxId()
 * @method mixed getMessage()
 * @method mixed getMoreAvailable()
 * @method string getNextMaxId()
 * @method int getNumResults()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isAutoLoadMoreEnabled()
 * @method bool isItems()
 * @method bool isMaxId()
 * @method bool isMessage()
 * @method bool isMoreAvailable()
 * @method bool isNextMaxId()
 * @method bool isNumResults()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setAutoLoadMoreEnabled(mixed $value)
 * @method $this setItems(Model\Item[] $value)
 * @method $this setMaxId(string $value)
 * @method $this setMessage(mixed $value)
 * @method $this setMoreAvailable(mixed $value)
 * @method $this setNextMaxId(string $value)
 * @method $this setNumResults(int $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetAutoLoadMoreEnabled()
 * @method $this unsetItems()
 * @method $this unsetMaxId()
 * @method $this unsetMessage()
 * @method $this unsetMoreAvailable()
 * @method $this unsetNextMaxId()
 * @method $this unsetNumResults()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class PopularFeedResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'next_max_id'            => 'string',
        'more_available'         => '',
        'auto_load_more_enabled' => '',
        'items'                  => 'Model\Item[]',
        'num_results'            => 'int',
        'max_id'                 => 'string',
    ];
}
