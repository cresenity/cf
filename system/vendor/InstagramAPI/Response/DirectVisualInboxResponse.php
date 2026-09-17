<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * DirectVisualInboxResponse.
 *
 * @method bool getHasMoreRead()
 * @method bool getHasMoreUnread()
 * @method mixed getMessage()
 * @method mixed getReadCursor()
 * @method string getStatus()
 * @method Model\DirectThread[] getThreads()
 * @method mixed getUnreadCursor()
 * @method mixed getUnseenCount()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isHasMoreRead()
 * @method bool isHasMoreUnread()
 * @method bool isMessage()
 * @method bool isReadCursor()
 * @method bool isStatus()
 * @method bool isThreads()
 * @method bool isUnreadCursor()
 * @method bool isUnseenCount()
 * @method bool isZMessages()
 * @method $this setHasMoreRead(bool $value)
 * @method $this setHasMoreUnread(bool $value)
 * @method $this setMessage(mixed $value)
 * @method $this setReadCursor(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setThreads(Model\DirectThread[] $value)
 * @method $this setUnreadCursor(mixed $value)
 * @method $this setUnseenCount(mixed $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetHasMoreRead()
 * @method $this unsetHasMoreUnread()
 * @method $this unsetMessage()
 * @method $this unsetReadCursor()
 * @method $this unsetStatus()
 * @method $this unsetThreads()
 * @method $this unsetUnreadCursor()
 * @method $this unsetUnseenCount()
 * @method $this unsetZMessages()
 */
class DirectVisualInboxResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'unseen_count'    => '',
        'has_more_unread' => 'bool',
        'read_cursor'     => '',
        'has_more_read'   => 'bool',
        'unread_cursor'   => '',
        'threads'         => 'Model\DirectThread[]',
    ];
}
