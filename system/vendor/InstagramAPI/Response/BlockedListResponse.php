<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * BlockedListResponse.
 *
 * @method Model\User[] getBlockedList()
 * @method mixed getMessage()
 * @method string getNextMaxId()
 * @method mixed getPageSize()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBlockedList()
 * @method bool isMessage()
 * @method bool isNextMaxId()
 * @method bool isPageSize()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setBlockedList(Model\User[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setNextMaxId(string $value)
 * @method $this setPageSize(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBlockedList()
 * @method $this unsetMessage()
 * @method $this unsetNextMaxId()
 * @method $this unsetPageSize()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class BlockedListResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'blocked_list' => 'Model\User[]',
        'next_max_id'  => 'string',
        'page_size'    => '',
    ];
}
