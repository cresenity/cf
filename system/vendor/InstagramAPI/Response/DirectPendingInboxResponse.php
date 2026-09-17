<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * DirectPendingInboxResponse.
 *
 * @method Model\DirectInbox getInbox()
 * @method mixed getMessage()
 * @method mixed getPendingRequestsTotal()
 * @method string getSeqId()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isInbox()
 * @method bool isMessage()
 * @method bool isPendingRequestsTotal()
 * @method bool isSeqId()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setInbox(Model\DirectInbox $value)
 * @method $this setMessage(mixed $value)
 * @method $this setPendingRequestsTotal(mixed $value)
 * @method $this setSeqId(string $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetInbox()
 * @method $this unsetMessage()
 * @method $this unsetPendingRequestsTotal()
 * @method $this unsetSeqId()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class DirectPendingInboxResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'seq_id'                 => 'string',
        'pending_requests_total' => '',
        'inbox'                  => 'Model\DirectInbox',
    ];
}
