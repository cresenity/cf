<?php

trait CTrader_Module_Trait_MomentumIndicatorsStochTrait {
    public static function rsi(int $startIdx, int $endIdx, array $inReal, int $optInTimePeriod, int &$outBegIdx, int &$outNBElement, array &$outReal): int {
        /** @var CTrader_Module_MomentumIndicators $this */
        if ($RetCode = static::validateStartEndIndexes($startIdx, $endIdx)) {
            return $RetCode;
        }
        if ((int) $optInTimePeriod == (PHP_INT_MIN)) {
            $optInTimePeriod = 14;
        } elseif (((int) $optInTimePeriod < 2) || ((int) $optInTimePeriod > 100000)) {
            return CTrader_ReturnCode::BAD_PARAM;
        }
        $outBegIdx = 0;
        $outNBElement = 0;
        $lookbackTotal = CTrader_Module_Lookback::rsiLookback($optInTimePeriod);
        if ($startIdx < $lookbackTotal) {
            $startIdx = $lookbackTotal;
        }
        if ($startIdx > $endIdx) {
            return CTrader_ReturnCode::SUCCESS;
        }
        $outIdx = 0;
        if ($optInTimePeriod == 1) {
            $outBegIdx = $startIdx;
            $i = ($endIdx - $startIdx) + 1;
            $outNBElement = $i;
            //System::arraycopy($inReal, $startIdx, $outReal, 0, $i);
            $outReal = \array_slice($inReal, $startIdx, $i);

            return CTrader_ReturnCode::SUCCESS;
        }
        $today = $startIdx - $lookbackTotal;
        $prevValue = $inReal[$today];
        $unstablePeriod = (static::$unstablePeriod[CTrader_Enum_UnstablePeriodFunctionID::RSI]);
        if (($unstablePeriod == 0)
            && ((static::$compatibility) == CTrader::COMPATIBILITY_METASTOCK)
        ) {
            $savePrevValue = $prevValue;
            $prevGain = 0.0;
            $prevLoss = 0.0;
            for ($i = $optInTimePeriod; $i > 0; $i--) {
                $tempValue1 = $inReal[$today++];
                $tempValue2 = $tempValue1 - $prevValue;
                $prevValue = $tempValue1;
                if ($tempValue2 < 0) {
                    $prevLoss -= $tempValue2;
                } else {
                    $prevGain += $tempValue2;
                }
            }
            $tempValue1 = $prevLoss / $optInTimePeriod;
            $tempValue2 = $prevGain / $optInTimePeriod;
            $tempValue1 = $tempValue2 + $tempValue1;
            if (!(((-0.00000001) < $tempValue1) && ($tempValue1 < 0.00000001))) {
                $outReal[$outIdx++] = 100 * ($tempValue2 / $tempValue1);
            } else {
                $outReal[$outIdx++] = 0.0;
            }
            if ($today > $endIdx) {
                $outBegIdx = $startIdx;
                $outNBElement = $outIdx;

                return CTrader_ReturnCode::SUCCESS;
            }
            $today -= $optInTimePeriod;
            $prevValue = $savePrevValue;
        }
        $prevGain = 0.0;
        $prevLoss = 0.0;
        $today++;
        for ($i = $optInTimePeriod; $i > 0; $i--) {
            $tempValue1 = $inReal[$today++];
            $tempValue2 = $tempValue1 - $prevValue;
            $prevValue = $tempValue1;
            if ($tempValue2 < 0) {
                $prevLoss -= $tempValue2;
            } else {
                $prevGain += $tempValue2;
            }
        }
        $prevLoss /= $optInTimePeriod;
        $prevGain /= $optInTimePeriod;
        if ($today > $startIdx) {
            $tempValue1 = $prevGain + $prevLoss;
            if (!(((-0.00000001) < $tempValue1) && ($tempValue1 < 0.00000001))) {
                $outReal[$outIdx++] = 100.0 * ($prevGain / $tempValue1);
            } else {
                $outReal[$outIdx++] = 0.0;
            }
        } else {
            while ($today < $startIdx) {
                $tempValue1 = $inReal[$today];
                $tempValue2 = $tempValue1 - $prevValue;
                $prevValue = $tempValue1;
                $prevLoss *= ($optInTimePeriod - 1);
                $prevGain *= ($optInTimePeriod - 1);
                if ($tempValue2 < 0) {
                    $prevLoss -= $tempValue2;
                } else {
                    $prevGain += $tempValue2;
                }
                $prevLoss /= $optInTimePeriod;
                $prevGain /= $optInTimePeriod;
                $today++;
            }
        }
        while ($today <= $endIdx) {
            $tempValue1 = $inReal[$today++];
            $tempValue2 = $tempValue1 - $prevValue;
            $prevValue = $tempValue1;
            $prevLoss *= ($optInTimePeriod - 1);
            $prevGain *= ($optInTimePeriod - 1);
            if ($tempValue2 < 0) {
                $prevLoss -= $tempValue2;
            } else {
                $prevGain += $tempValue2;
            }
            $prevLoss /= $optInTimePeriod;
            $prevGain /= $optInTimePeriod;
            $tempValue1 = $prevGain + $prevLoss;
            if (!(((-0.00000001) < $tempValue1) && ($tempValue1 < 0.00000001))) {
                $outReal[$outIdx++] = 100.0 * ($prevGain / $tempValue1);
            } else {
                $outReal[$outIdx++] = 0.0;
            }
        }
        $outBegIdx = $startIdx;
        $outNBElement = $outIdx;

        return CTrader_ReturnCode::SUCCESS;
    }

    public static function stoch(int $startIdx, int $endIdx, array $inHigh, array $inLow, array $inClose, int $optInFastK_Period, int $optInSlowK_Period, int $optInSlowK_MAType, int $optInSlowD_Period, int $optInSlowD_MAType, int &$outBegIdx, int &$outNBElement, array &$outSlowK, array &$outSlowD): int {
        if ($RetCode = static::validateStartEndIndexes($startIdx, $endIdx)) {
            return $RetCode;
        }
        if ((int) $optInFastK_Period == (PHP_INT_MIN)) {
            $optInFastK_Period = 5;
        } elseif (((int) $optInFastK_Period < 1) || ((int) $optInFastK_Period > 100000)) {
            return CTrader_ReturnCode::BAD_PARAM;
        }
        if ((int) $optInSlowK_Period == (PHP_INT_MIN)) {
            $optInSlowK_Period = 3;
        } elseif (((int) $optInSlowK_Period < 1) || ((int) $optInSlowK_Period > 100000)) {
            return CTrader_ReturnCode::BAD_PARAM;
        }
        if ((int) $optInSlowD_Period == (PHP_INT_MIN)) {
            $optInSlowD_Period = 3;
        } elseif (((int) $optInSlowD_Period < 1) || ((int) $optInSlowD_Period > 100000)) {
            return CTrader_ReturnCode::BAD_PARAM;
        }
        $lookbackK = $optInFastK_Period - 1;
        $lookbackKSlow = CTrader_Module_Lookback::movingAverageLookback($optInSlowK_Period, $optInSlowK_MAType);
        $lookbackDSlow = CTrader_Module_Lookback::movingAverageLookback($optInSlowD_Period, $optInSlowD_MAType);
        $lookbackTotal = $lookbackK + $lookbackDSlow + $lookbackKSlow;
        if ($startIdx < $lookbackTotal) {
            $startIdx = $lookbackTotal;
        }
        if ($startIdx > $endIdx) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return CTrader_ReturnCode::SUCCESS;
        }
        $outIdx = 0;
        $trailingIdx = $startIdx - $lookbackTotal;
        $today = $trailingIdx + $lookbackK;
        $lowestIdx = $highestIdx = -1;
        $diff = $highest = $lowest = 0.0;
        if (($outSlowK == $inHigh)
            || ($outSlowK == $inLow)
            || ($outSlowK == $inClose)
        ) {
            $tempBuffer = $outSlowK;
        } elseif (($outSlowD == $inHigh)
            || ($outSlowD == $inLow)
            || ($outSlowD == $inClose)
        ) {
            $tempBuffer = $outSlowD;
        } else {
            $tempBuffer = static::double($endIdx - $today + 1);
        }
        while ($today <= $endIdx) {
            $tmp = $inLow[$today];
            if ($lowestIdx < $trailingIdx) {
                $lowestIdx = $trailingIdx;
                $lowest = $inLow[$lowestIdx];
                $i = $lowestIdx;
                while (++$i <= $today) {
                    $tmp = $inLow[$i];
                    if ($tmp < $lowest) {
                        $lowestIdx = $i;
                        $lowest = $tmp;
                    }
                }
                $diff = ($highest - $lowest) / 100.0;
            } elseif ($tmp <= $lowest) {
                $lowestIdx = $today;
                $lowest = $tmp;
                $diff = ($highest - $lowest) / 100.0;
            }
            $tmp = $inHigh[$today];
            if ($highestIdx < $trailingIdx) {
                $highestIdx = $trailingIdx;
                $highest = $inHigh[$highestIdx];
                $i = $highestIdx;
                while (++$i <= $today) {
                    $tmp = $inHigh[$i];
                    if ($tmp > $highest) {
                        $highestIdx = $i;
                        $highest = $tmp;
                    }
                }
                $diff = ($highest - $lowest) / 100.0;
            } elseif ($tmp >= $highest) {
                $highestIdx = $today;
                $highest = $tmp;
                $diff = ($highest - $lowest) / 100.0;
            }
            if ($diff != 0.0) {
                $tempBuffer[$outIdx++] = ($inClose[$today] - $lowest) / $diff;
            } else {
                $tempBuffer[$outIdx++] = 0.0;
            }
            $trailingIdx++;
            $today++;
        }
        $ReturnCode = CTrader_Module_OverlapStudies::movingAverage(
            0,
            $outIdx - 1,
            $tempBuffer,
            $optInSlowK_Period,
            $optInSlowK_MAType,
            $outBegIdx,
            $outNBElement,
            $tempBuffer
        );
        if (($ReturnCode != CTrader_ReturnCode::SUCCESS) || ((int) $outNBElement == 0)) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return $ReturnCode;
        }
        $ReturnCode = CTrader_Module_OverlapStudies::movingAverage(
            0,
            (int) $outNBElement - 1,
            $tempBuffer,
            $optInSlowD_Period,
            $optInSlowD_MAType,
            $outBegIdx,
            $outNBElement,
            $outSlowD
        );
        //System::arraycopy($tempBuffer, $lookbackDSlow, $outSlowK, 0, (int)$outNBElement);
        $outSlowK = \array_slice($tempBuffer, $lookbackDSlow, (int) $outNBElement);
        if ($ReturnCode != CTrader_ReturnCode::SUCCESS) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return $ReturnCode;
        }
        $outBegIdx = $startIdx;

        return CTrader_ReturnCode::SUCCESS;
    }

    public static function stochF(int $startIdx, int $endIdx, array $inHigh, array $inLow, array $inClose, int $optInFastK_Period, int $optInFastD_Period, int $optInFastD_MAType, int &$outBegIdx, int &$outNBElement, array &$outFastK, array &$outFastD): int {
        if ($RetCode = static::validateStartEndIndexes($startIdx, $endIdx)) {
            return $RetCode;
        }
        if ((int) $optInFastK_Period == (PHP_INT_MIN)) {
            $optInFastK_Period = 5;
        } elseif (((int) $optInFastK_Period < 1) || ((int) $optInFastK_Period > 100000)) {
            return CTrader_ReturnCode::BAD_PARAM;
        }
        if ((int) $optInFastD_Period == (PHP_INT_MIN)) {
            $optInFastD_Period = 3;
        } elseif (((int) $optInFastD_Period < 1) || ((int) $optInFastD_Period > 100000)) {
            return CTrader_ReturnCode::BAD_PARAM;
        }
        $lookbackK = $optInFastK_Period - 1;
        $lookbackFastD = CTrader_Module_Lookback::movingAverageLookback($optInFastD_Period, $optInFastD_MAType);
        $lookbackTotal = $lookbackK + $lookbackFastD;
        if ($startIdx < $lookbackTotal) {
            $startIdx = $lookbackTotal;
        }
        if ($startIdx > $endIdx) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return CTrader_ReturnCode::SUCCESS;
        }
        $outIdx = 0;
        $trailingIdx = $startIdx - $lookbackTotal;
        $today = $trailingIdx + $lookbackK;
        $lowestIdx = $highestIdx = -1;
        $diff = $highest = $lowest = 0.0;
        if (($outFastK == $inHigh)
            || ($outFastK == $inLow)
            || ($outFastK == $inClose)
        ) {
            $tempBuffer = $outFastK;
        } elseif (($outFastD == $inHigh)
            || ($outFastD == $inLow)
            || ($outFastD == $inClose)
        ) {
            $tempBuffer = $outFastD;
        } else {
            $tempBuffer = static::double($endIdx - $today + 1);
        }
        while ($today <= $endIdx) {
            $tmp = $inLow[$today];
            if ($lowestIdx < $trailingIdx) {
                $lowestIdx = $trailingIdx;
                $lowest = $inLow[$lowestIdx];
                $i = $lowestIdx;
                while (++$i <= $today) {
                    $tmp = $inLow[$i];
                    if ($tmp < $lowest) {
                        $lowestIdx = $i;
                        $lowest = $tmp;
                    }
                }
                $diff = ($highest - $lowest) / 100.0;
            } elseif ($tmp <= $lowest) {
                $lowestIdx = $today;
                $lowest = $tmp;
                $diff = ($highest - $lowest) / 100.0;
            }
            $tmp = $inHigh[$today];
            if ($highestIdx < $trailingIdx) {
                $highestIdx = $trailingIdx;
                $highest = $inHigh[$highestIdx];
                $i = $highestIdx;
                while (++$i <= $today) {
                    $tmp = $inHigh[$i];
                    if ($tmp > $highest) {
                        $highestIdx = $i;
                        $highest = $tmp;
                    }
                }
                $diff = ($highest - $lowest) / 100.0;
            } elseif ($tmp >= $highest) {
                $highestIdx = $today;
                $highest = $tmp;
                $diff = ($highest - $lowest) / 100.0;
            }
            if ($diff != 0.0) {
                $tempBuffer[$outIdx++] = ($inClose[$today] - $lowest) / $diff;
            } else {
                $tempBuffer[$outIdx++] = 0.0;
            }
            $trailingIdx++;
            $today++;
        }
        $ReturnCode = CTrader_Module_OverlapStudies::movingAverage(
            0,
            $outIdx - 1,
            $tempBuffer,
            $optInFastD_Period,
            $optInFastD_MAType,
            $outBegIdx,
            $outNBElement,
            $outFastD
        );
        if (($ReturnCode != CTrader_ReturnCode::SUCCESS) || ((int) $outNBElement) == 0) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return $ReturnCode;
        }
        //System::arraycopy($tempBuffer, $lookbackFastD, $outFastK, 0, (int)$outNBElement);
        $outFastK = \array_slice($tempBuffer, $lookbackFastD, (int) $outNBElement);
        if ($ReturnCode != CTrader_ReturnCode::SUCCESS) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return $ReturnCode;
        }
        $outBegIdx = $startIdx;

        return CTrader_ReturnCode::SUCCESS;
    }

    public static function stochRsi(int $startIdx, int $endIdx, array $inReal, int $optInTimePeriod, int $optInFastK_Period, int $optInFastD_Period, int $optInFastD_MAType, int &$outBegIdx, int &$outNBElement, array &$outFastK, array &$outFastD): int {
        if ($RetCode = static::validateStartEndIndexes($startIdx, $endIdx)) {
            return $RetCode;
        }
        $outBegIdx1 = 0;
        $outBegIdx2 = 0;
        $outNbElement1 = 0;
        if ((int) $optInTimePeriod == (PHP_INT_MIN)) {
            $optInTimePeriod = 14;
        } elseif (((int) $optInTimePeriod < 2) || ((int) $optInTimePeriod > 100000)) {
            return CTrader_ReturnCode::BAD_PARAM;
        }
        if ((int) $optInFastK_Period == (PHP_INT_MIN)) {
            $optInFastK_Period = 5;
        } elseif (((int) $optInFastK_Period < 1) || ((int) $optInFastK_Period > 100000)) {
            return CTrader_ReturnCode::BAD_PARAM;
        }
        if ((int) $optInFastD_Period == (PHP_INT_MIN)) {
            $optInFastD_Period = 3;
        } elseif (((int) $optInFastD_Period < 1) || ((int) $optInFastD_Period > 100000)) {
            return CTrader_ReturnCode::BAD_PARAM;
        }
        $outBegIdx = 0;
        $outNBElement = 0;
        $lookbackSTOCHF = CTrader_Module_Lookback::stochFLookback($optInFastK_Period, $optInFastD_Period, $optInFastD_MAType);
        $lookbackTotal = CTrader_Module_Lookback::rsiLookback($optInTimePeriod) + $lookbackSTOCHF;
        if ($startIdx < $lookbackTotal) {
            $startIdx = $lookbackTotal;
        }
        if ($startIdx > $endIdx) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return CTrader_ReturnCode::SUCCESS;
        }
        $outBegIdx = $startIdx;
        $tempArraySize = ($endIdx - $startIdx) + 1 + $lookbackSTOCHF;
        $tempRSIBuffer = static::double($tempArraySize);
        $ReturnCode = self::rsi(
            $startIdx - $lookbackSTOCHF,
            $endIdx,
            $inReal,
            $optInTimePeriod,
            $outBegIdx1,
            $outNbElement1,
            $tempRSIBuffer
        );
        if ($ReturnCode != CTrader_ReturnCode::SUCCESS || $outNbElement1 == 0) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return $ReturnCode;
        }
        $ReturnCode = self::stochF(
            0,
            $tempArraySize - 1,
            $tempRSIBuffer,
            $tempRSIBuffer,
            $tempRSIBuffer,
            $optInFastK_Period,
            $optInFastD_Period,
            $optInFastD_MAType,
            $outBegIdx2,
            $outNBElement,
            $outFastK,
            $outFastD
        );
        if ($ReturnCode != CTrader_ReturnCode::SUCCESS || ((int) $outNBElement) == 0) {
            $outBegIdx = 0;
            $outNBElement = 0;

            return $ReturnCode;
        }

        return CTrader_ReturnCode::SUCCESS;
    }
}
