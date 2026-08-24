<?php

/**
 * Class Core.
 */
class CTrader_ModuleAbstract {
    /**
     * @var int
     */
    public static $compatibility = CTrader::COMPATIBILITY_DEFAULT;

    /**
     * @var int[]
     */
    protected static $unstablePeriod;

    /**
     * @var CandleSetting[]
     */
    protected static $candleSettings;

    /**
     * Core constructor.
     *
     * These settings would be set above, but are not allowed to be defaults for static variables.
     */
    public static function construct() {
        static::$candleSettings = [
            /* real body is long when it's longer than the average of the 10 previous candles' real body */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_BODY_LONG, CTrader_CandleSetting::RANGE_TYPE_REAL_BODY, 10, 1.),
            /* real body is very long when it's longer than 3 times the average of the 10 previous candles' real body */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_BODY_VERY_LONG, CTrader_CandleSetting::RANGE_TYPE_REAL_BODY, 10, 3.),
            /* real body is short when it's shorter than the average of the 10 previous candles' real bodies */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_BODY_SHORT, CTrader_CandleSetting::RANGE_TYPE_REAL_BODY, 10, 1.),
            /* real body is like doji's body when it's shorter than 10% the average of the 10 previous candles' high-low range */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_BODY_DOJI, CTrader_CandleSetting::RANGE_TYPE_HIGH_LOW, 10, 0.1),
            /* shadow is long when it's longer than the real body */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_SHADOW_LONG, CTrader_CandleSetting::RANGE_TYPE_REAL_BODY, 0, 1.),
            /* shadow is very long when it's longer than 2 times the real body */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_SHADOW_VERY_LONG, CTrader_CandleSetting::RANGE_TYPE_REAL_BODY, 0, 2.),
            /* shadow is short when it's shorter than half the average of the 10 previous candles' sum of shadows */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_SHADOW_SHORT, CTrader_CandleSetting::RANGE_TYPE_SHADOWS, 10, 1.),
            /* shadow is very short when it's shorter than 10% the average of the 10 previous candles' high-low range */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_SHADOW_VERY_SHORT, CTrader_CandleSetting::RANGE_TYPE_HIGH_LOW, 10, 0.1),
            /* when measuring distance between parts of candles or width of gaps "near" means "<= 20% of the average of the 5 previous candles' high-low range" */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_NEAR, CTrader_CandleSetting::RANGE_TYPE_HIGH_LOW, 5, 0.2),
            /* when measuring distance between parts of candles or width of gaps "far" means ">= 60% of the average of the 5 previous candles' high-low range" */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_FAR, CTrader_CandleSetting::RANGE_TYPE_HIGH_LOW, 5, 0.6),
            /* when measuring distance between parts of candles or width of gaps "equal" means "<= 5% of the average of the 5 previous candles' high-low range" */
            new CTrader_CandleSetting(CTrader_CandleSetting::TYPE_EQUAL, CTrader_CandleSetting::RANGE_TYPE_HIGH_LOW, 5, 0.05),
        ];
        static::$unstablePeriod = \array_pad([], CTrader_Enum_UnstablePeriodFunctionID::ALL, 0);
    }

    /**
     * @return array
     */
    protected static function double(int $size) {
        return \array_pad([], $size, 0.);
    }

    /**
     * @return int
     */
    protected static function validateStartEndIndexes(int $startIdx, int $endIdx) {
        if ($startIdx < 0) {
            return CTrader_ReturnCode::OUT_OF_RANGE_START_INDEX;
        }
        if (($endIdx < 0) || ($endIdx < $startIdx)) {
            return CTrader_ReturnCode::OUT_OF_RANGE_END_INDEX;
        }

        return CTrader_ReturnCode::SUCCESS;
    }

    //@codingStandardsIgnoreStart
    protected static function TA_INT_PO(int $startIdx, int $endIdx, array $inReal, int $optInFastPeriod, int $optInSlowPeriod, int $optInMethod_2, int &$outBegIdx, int &$outNBElement, array &$outReal, array &$tempBuffer, bool $doPercentageOutput): int {
        //@codingStandardsIgnoreEnd
        $outBegIdx1 = 0;
        $outNbElement1 = 0;
        $outBegIdx2 = 0;
        $outNbElement2 = 0;
        if ($optInSlowPeriod < $optInFastPeriod) {
            $tempInteger = $optInSlowPeriod;
            $optInSlowPeriod = $optInFastPeriod;
            $optInFastPeriod = $tempInteger;
        }
        $ReturnCode = CTrader_Module_OverlapStudies::movingAverage($startIdx, $endIdx, $inReal, $optInFastPeriod, $optInMethod_2, $outBegIdx2, $outNbElement2, $tempBuffer);
        if ($ReturnCode == CTrader_ReturnCode::SUCCESS) {
            $ReturnCode = CTrader_Module_OverlapStudies::movingAverage($startIdx, $endIdx, $inReal, $optInSlowPeriod, $optInMethod_2, $outBegIdx1, $outNbElement1, $outReal);
            if ($ReturnCode == CTrader_ReturnCode::SUCCESS) {
                $tempInteger = $outBegIdx1 - $outBegIdx2;
                if ($doPercentageOutput != 0) {
                    for ($i = 0, $j = $tempInteger; $i < $outNbElement1; $i++, $j++) {
                        $tempReal = $outReal[$i];
                        if (!(((-0.00000001) < $tempReal) && ($tempReal < 0.00000001))) {
                            $outReal[$i] = (($tempBuffer[$j] - $tempReal) / $tempReal) * 100.0;
                        } else {
                            $outReal[$i] = 0.0;
                        }
                    }
                } else {
                    for ($i = 0, $j = $tempInteger; $i < $outNbElement1; $i++, $j++) {
                        $outReal[$i] = $tempBuffer[$j] - $outReal[$i];
                    }
                }
                $outBegIdx = $outBegIdx1;
                $outNBElement = $outNbElement1;
            }
        }
        if ($ReturnCode != CTrader_ReturnCode::SUCCESS) {
            $outBegIdx = 0;
            $outNBElement = 0;
        }

        return $ReturnCode;
    }

    //@codingStandardsIgnoreStart
    protected static function TA_INT_MACD(int $startIdx, int $endIdx, array $inReal, int $optInFastPeriod, int $optInSlowPeriod, int $optInSignalPeriod_2, int &$outBegIdx, int &$outNBElement, array &$outMACD, array &$outMACDSignal, array &$outMACDHist): int {
        //@codingStandardsIgnoreEnd
        //double[] $slowEMABuffer;
        //double[] $fastEMABuffer;
        //double $k1, $k2;
        //ReturnCode $ReturnCode;
        //int $tempInteger;
        $outBegIdx1 = 0;
        $outNbElement1 = 0;
        $outBegIdx2 = 0;
        $outNbElement2 = 0;
        //int $lookbackTotal, $lookbackSignal;
        //int $i;
        if ($optInSlowPeriod < $optInFastPeriod) {
            $tempInteger = $optInSlowPeriod;
            $optInSlowPeriod = $optInFastPeriod;
            $optInFastPeriod = $tempInteger;
        }
        if ($optInSlowPeriod != 0) {
            $k1 = ((double) 2.0 / ((double) ($optInSlowPeriod + 1)));
        } else {
            $optInSlowPeriod = 26;
            $k1 = (double) 0.075;
        }
        if ($optInFastPeriod != 0) {
            $k2 = ((double) 2.0 / ((double) ($optInFastPeriod + 1)));
        } else {
            $optInFastPeriod = 12;
            $k2 = (double) 0.15;
        }
        $lookbackSignal = CTrader_Module_Lookback::emaLookback($optInSignalPeriod_2);
        $lookbackTotal = $lookbackSignal;
        $lookbackTotal += CTrader_Module_Lookback::emaLookback($optInSlowPeriod);
        if ($startIdx < $lookbackTotal) {
            $startIdx = $lookbackTotal;
        }
        if ($startIdx > $endIdx) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return CTrader_ReturnCode::SUCCESS;
        }
        $tempInteger = ($endIdx - $startIdx) + 1 + $lookbackSignal;
        $fastEMABuffer = static::double($tempInteger);
        $slowEMABuffer = static::double($tempInteger);
        $tempInteger = $startIdx - $lookbackSignal;
        $ReturnCode = static::TA_INT_EMA($tempInteger, $endIdx, $inReal, $optInSlowPeriod, $k1, $outBegIdx1, $outNbElement1, $slowEMABuffer);
        if ($ReturnCode != CTrader_ReturnCode::SUCCESS) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return $ReturnCode;
        }
        $ReturnCode = static::TA_INT_EMA($tempInteger, $endIdx, $inReal, $optInFastPeriod, $k2, $outBegIdx2, $outNbElement2, $fastEMABuffer);
        if ($ReturnCode != CTrader_ReturnCode::SUCCESS) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return $ReturnCode;
        }
        if (($outBegIdx1 != $tempInteger)
            || ($outBegIdx2 != $tempInteger)
            || ($outNbElement1 != $outNbElement2)
            || ($outNbElement1 != ($endIdx - $startIdx) + 1 + $lookbackSignal)
        ) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return CTrader_ReturnCode::INTERNAL_ERROR;
        }
        for ($i = 0; $i < $outNbElement1; $i++) {
            $fastEMABuffer[$i] = $fastEMABuffer[$i] - $slowEMABuffer[$i];
        }
        //System::arraycopy($fastEMABuffer, $lookbackSignal, $outMACD, 0, ($endIdx - $startIdx) + 1);
        $outMACD = \array_slice($fastEMABuffer, $lookbackSignal, ($endIdx - $startIdx) + 1);
        $ReturnCode = static::TA_INT_EMA(0, $outNbElement1 - 1, $fastEMABuffer, $optInSignalPeriod_2, ((double) 2.0 / ((double) ($optInSignalPeriod_2 + 1))), $outBegIdx2, $outNbElement2, $outMACDSignal);
        if ($ReturnCode != CTrader_ReturnCode::SUCCESS) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return $ReturnCode;
        }
        for ($i = 0; $i < $outNbElement2; $i++) {
            $outMACDHist[$i] = $outMACD[$i] - $outMACDSignal[$i];
        }
        $outBegIdx = $startIdx;
        $outNBElement = $outNbElement2;

        return CTrader_ReturnCode::SUCCESS;
    }

    //@codingStandardsIgnoreStart
    protected static function TA_INT_EMA(int $startIdx, int $endIdx, $inReal, int $optInTimePeriod, float $optInK_1, int &$outBegIdx, int &$outNBElement, array &$outReal): int {
        //@codingStandardsIgnoreEnd
        //double $tempReal, $prevMA;
        //int $i, $today, $outIdx, $lookbackTotal;
        $lookbackTotal = CTrader_Module_Lookback::emaLookback($optInTimePeriod);
        if ($startIdx < $lookbackTotal) {
            $startIdx = $lookbackTotal;
        }
        if ($startIdx > $endIdx) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return CTrader_ReturnCode::SUCCESS;
        }
        $outBegIdx = $startIdx;
        if ((static::$compatibility) == CTrader::COMPATIBILITY_DEFAULT) {
            $today = $startIdx - $lookbackTotal;
            $i = $optInTimePeriod;
            $tempReal = 0.0;
            while ($i-- > 0) {
                $tempReal += $inReal[$today++];
            }
            $prevMA = $tempReal / $optInTimePeriod;
        } else {
            $prevMA = $inReal[0];
            $today = 1;
        }
        while ($today <= $startIdx) {
            $prevMA = (($inReal[$today++] - $prevMA) * $optInK_1) + $prevMA;
        }
        $outReal[0] = $prevMA;
        $outIdx = 1;
        while ($today <= $endIdx) {
            $prevMA = (($inReal[$today++] - $prevMA) * $optInK_1) + $prevMA;
            $outReal[$outIdx++] = $prevMA;
        }
        $outNBElement = $outIdx;

        return CTrader_ReturnCode::SUCCESS;
    }

    //@codingStandardsIgnoreStart
    protected static function TA_INT_SMA(int $startIdx, int $endIdx, array $inReal, int $optInTimePeriod, int &$outBegIdx, int &$outNBElement, array &$outReal): int {
        //@codingStandardsIgnoreEnd
        //double $periodTotal, $tempReal;
        //int $i, $outIdx, $trailingIdx, $lookbackTotal;
        $lookbackTotal = ($optInTimePeriod - 1);
        if ($startIdx < $lookbackTotal) {
            $startIdx = $lookbackTotal;
        }
        if ($startIdx > $endIdx) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return CTrader_ReturnCode::SUCCESS;
        }
        $periodTotal = 0;
        $trailingIdx = $startIdx - $lookbackTotal;
        $i = $trailingIdx;
        if ($optInTimePeriod > 1) {
            while ($i < $startIdx) {
                $periodTotal += $inReal[$i++];
            }
        }
        $outIdx = 0;
        do {
            $periodTotal += $inReal[$i++];
            $tempReal = $periodTotal;
            $periodTotal -= $inReal[$trailingIdx++];
            $outReal[$outIdx++] = $tempReal / $optInTimePeriod;
        } while ($i <= $endIdx);
        $outNBElement = $outIdx;
        $outBegIdx = $startIdx;

        return CTrader_ReturnCode::SUCCESS;
    }

    //@codingStandardsIgnoreStart
    protected static function TA_INT_stddev_using_precalc_ma(array $inReal, array &$inMovAvg, int $inMovAvgBegIdx, int $inMovAvgNbElement, int $timePeriod, array &$output): int {
        //@codingStandardsIgnoreEnd
        //double $tempReal, $periodTotal2, $meanValue2;
        //int $outIdx;
        //int $startSum, $endSum;
        $startSum = 1 + $inMovAvgBegIdx - $timePeriod;
        $endSum = $inMovAvgBegIdx;
        $periodTotal2 = 0;
        for ($outIdx = $startSum; $outIdx < $endSum; $outIdx++) {
            $tempReal = $inReal[$outIdx];
            $tempReal *= $tempReal;
            $periodTotal2 += $tempReal;
        }
        for ($outIdx = 0; $outIdx < $inMovAvgNbElement; $outIdx++, $startSum++, $endSum++) {
            $tempReal = $inReal[$endSum];
            $tempReal *= $tempReal;
            $periodTotal2 += $tempReal;
            $meanValue2 = $periodTotal2 / $timePeriod;
            $tempReal = $inReal[$startSum];
            $tempReal *= $tempReal;
            $periodTotal2 -= $tempReal;
            $tempReal = $inMovAvg[$outIdx];
            $tempReal *= $tempReal;
            $meanValue2 -= $tempReal;
            if (!($meanValue2 < 0.00000001)) {
                $output[$outIdx] = sqrt($meanValue2);
            } else {
                $output[$outIdx] = (double) 0.0;
            }
        }

        return CTrader_ReturnCode::SUCCESS;
    }

    //@codingStandardsIgnoreStart
    protected static function TA_INT_VAR(int $startIdx, int $endIdx, array $inReal, int $optInTimePeriod, int &$outBegIdx, int &$outNBElement, array &$outReal): int {
        //@codingStandardsIgnoreEnd
        //double $tempReal, $periodTotal1, $periodTotal2, $meanValue1, $meanValue2;
        //int $i, $outIdx, $trailingIdx, $nbInitialElementNeeded;
        $nbInitialElementNeeded = ($optInTimePeriod - 1);
        if ($startIdx < $nbInitialElementNeeded) {
            $startIdx = $nbInitialElementNeeded;
        }
        if ($startIdx > $endIdx) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return CTrader_ReturnCode::SUCCESS;
        }
        $periodTotal1 = 0;
        $periodTotal2 = 0;
        $trailingIdx = $startIdx - $nbInitialElementNeeded;
        $i = $trailingIdx;
        if ($optInTimePeriod > 1) {
            while ($i < $startIdx) {
                $tempReal = $inReal[$i++];
                $periodTotal1 += $tempReal;
                $tempReal *= $tempReal;
                $periodTotal2 += $tempReal;
            }
        }
        $outIdx = 0;
        do {
            $tempReal = $inReal[$i++];
            $periodTotal1 += $tempReal;
            $tempReal *= $tempReal;
            $periodTotal2 += $tempReal;
            $meanValue1 = $periodTotal1 / $optInTimePeriod;
            $meanValue2 = $periodTotal2 / $optInTimePeriod;
            $tempReal = $inReal[$trailingIdx++];
            $periodTotal1 -= $tempReal;
            $tempReal *= $tempReal;
            $periodTotal2 -= $tempReal;
            $outReal[$outIdx++] = $meanValue2 - $meanValue1 * $meanValue1;
        } while ($i <= $endIdx);
        $outNBElement = $outIdx;
        $outBegIdx = $startIdx;

        return CTrader_ReturnCode::SUCCESS;
    }
}
