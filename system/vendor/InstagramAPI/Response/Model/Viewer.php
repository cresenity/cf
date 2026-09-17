<?php

namespace InstagramAPI\Response\Model;

use InstagramAPI\AutoPropertyMapper;

/**
 * Viewer.
 *
 * @method EligiblePromotions getEligiblePromotions()
 * @method bool isEligiblePromotions()
 * @method $this setEligiblePromotions(EligiblePromotions $value)
 * @method $this unsetEligiblePromotions()
 */
class Viewer extends AutoPropertyMapper
{
    public static $JSON_PROPERTY_MAP = [
        'eligible_promotions'   => 'EligiblePromotions',
    ];
}
