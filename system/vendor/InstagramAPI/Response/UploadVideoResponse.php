<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * UploadVideoResponse.
 *
 * @method float getConfigureDelayMs()
 * @method mixed getMessage()
 * @method mixed getResult()
 * @method string getStatus()
 * @method string getUploadId()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isConfigureDelayMs()
 * @method bool isMessage()
 * @method bool isResult()
 * @method bool isStatus()
 * @method bool isUploadId()
 * @method bool isZMessages()
 * @method $this setConfigureDelayMs(float $value)
 * @method $this setMessage(mixed $value)
 * @method $this setResult(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setUploadId(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetConfigureDelayMs()
 * @method $this unsetMessage()
 * @method $this unsetResult()
 * @method $this unsetStatus()
 * @method $this unsetUploadId()
 * @method $this unsetZMessages()
 */
class UploadVideoResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'upload_id'          => 'string',
        'configure_delay_ms' => 'float',
        'result'             => '',
    ];
}
