<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * SyncResponse.
 *
 * @method Model\Experiment[] getExperiments()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isExperiments()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setExperiments(Model\Experiment[] $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetExperiments()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class SyncResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'experiments' => 'Model\Experiment[]',
    ];
}
