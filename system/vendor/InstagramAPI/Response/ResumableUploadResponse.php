<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * ResumableUploadResponse.
 *
 * @method mixed getMessage()
 * @method string getStatus()
 * @method int getUploadId()
 * @method mixed getXsharingNonces()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isUploadId()
 * @method bool isXsharingNonces()
 * @method bool isZMessages()
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUploadId(int $value)
 * @method $this setXsharingNonces(mixed $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetUploadId()
 * @method $this unsetXsharingNonces()
 * @method $this unsetZMessages()
 */
class ResumableUploadResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'xsharing_nonces' => '',
        'upload_id'       => 'int',
    ];
}
