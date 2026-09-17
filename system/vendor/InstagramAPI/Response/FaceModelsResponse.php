<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * FaceModelsResponse.
 *
 * @method Model\FaceModels getFaceModels()
 * @method mixed getMessage()
 * @method string getStatus()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isFaceModels()
 * @method bool isMessage()
 * @method bool isStatus()
 * @method bool isZMessages()
 * @method $this setFaceModels(Model\FaceModels $value)
 * @method $this setMessage(mixed $value)
 * @method $this setStatus(string $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetFaceModels()
 * @method $this unsetMessage()
 * @method $this unsetStatus()
 * @method $this unsetZMessages()
 */
class FaceModelsResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'face_models' => 'Model\FaceModels',
    ];
}
