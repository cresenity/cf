<?php

class CTrader_CandleSetting {
    const TYPE_BODY_LONG = 0;

    const TYPE_BODY_VERY_LONG = 1;

    const TYPE_BODY_SHORT = 2;

    const TYPE_BODY_DOJI = 3;

    const TYPE_SHADOW_LONG = 4;

    const TYPE_SHADOW_VERY_LONG = 5;

    const TYPE_SHADOW_SHORT = 6;

    const TYPE_SHADOW_VERY_SHORT = 7;

    const TYPE_NEAR = 8;

    const TYPE_FAR = 9;

    const TYPE_EQUAL = 10;

    const TYPE_ALL_CANDLE_SETTINGS = 11;

    const RANGE_TYPE_REAL_BODY = 0;

    const RANGE_TYPE_HIGH_LOW = 1;

    const RANGE_TYPE_SHADOWS = 2;

    /**
     * @var int
     */
    public $settingType;

    /**
     * @var int
     */
    public $rangeType;

    /**
     * @var int
     */
    public $avgPeriod;

    /**
     * @var float
     */
    public $factor;

    public function __construct(int $settingType, int $rangeType = null, int $avgPeriod = null, float $factor = null) {
        $this->settingType = $settingType;
        $this->rangeType = $rangeType;
        $this->avgPeriod = $avgPeriod;
        $this->factor = $factor;
    }

    public function copyFrom(CTrader_CandleSetting $source) {
        $this->settingType = $source->settingType;
        $this->rangeType = $source->rangeType;
        $this->avgPeriod = $source->avgPeriod;
        $this->factor = $source->factor;
    }

    public function candleSetting(CTrader_CandleSetting $that) {
        $this->settingType = $that->settingType;
        $this->rangeType = $that->rangeType;
        $this->avgPeriod = $that->avgPeriod;
        $this->factor = $that->factor;
    }
}
