<?php

namespace InstagramAPI\Response\Model;

use InstagramAPI\AutoPropertyMapper;

/**
 * ImageVersions2.
 *
 * @method ImageCandidate[] getCandidates()
 * @method mixed getTraceToken()
 * @method bool isCandidates()
 * @method bool isTraceToken()
 * @method $this setCandidates(ImageCandidate[] $value)
 * @method $this setTraceToken(mixed $value)
 * @method $this unsetCandidates()
 * @method $this unsetTraceToken()
 */
class ImageVersions2 extends AutoPropertyMapper
{
    public static $JSON_PROPERTY_MAP = [
        'candidates'  => 'ImageCandidate[]',
        'trace_token' => '',
    ];
}
