<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * DirectInboxResponse.
 *
 * @method Model\DirectInbox getInbox()
 * @method Model\Megaphone getMegaphone()
 * @method mixed getMessage()
 * @method mixed getPendingRequestsTotal()
 * @method Model\User[] getPendingRequestsUsers()
 * @method string getSeqId()
 * @method string getSnapshotAtMs()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isInbox()
 * @method bool isMegaphone()
 * @method bool isMessage()
 * @method bool isPendingRequestsTotal()
 * @method bool isPendingRequestsUsers()
 * @method bool isSeqId()
 * @method bool isSnapshotAtMs()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setInbox(Model\DirectInbox $value)
 * @method $this setMegaphone(Model\Megaphone $value)
 * @method $this setMessage(mixed $value)
 * @method $this setPendingRequestsTotal(mixed $value)
 * @method $this setPendingRequestsUsers(Model\User[] $value)
 * @method $this setSeqId(string $value)
 * @method $this setSnapshotAtMs(string $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetInbox()
 * @method $this unsetMegaphone()
 * @method $this unsetMessage()
 * @method $this unsetPendingRequestsTotal()
 * @method $this unsetPendingRequestsUsers()
 * @method $this unsetSeqId()
 * @method $this unsetSnapshotAtMs()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class DirectInboxResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'pending_requests_total' => '',
        'seq_id'                 => 'string',
        'pending_requests_users' => 'Model\User[]',
        'inbox'                  => 'Model\DirectInbox',
        'megaphone'              => 'Model\Megaphone',
        'snapshot_at_ms'         => 'string',
    ];
}
