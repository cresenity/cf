<?php

class CTrader {
    const COMPATIBILITY_DEFAULT = 0;

    const COMPATIBILITY_METASTOCK = 1;

    /**
     * @var CTrader_MomentumIndicators
     */
    protected static $momentumIndicators = null;

    /**
     * @var null|CTrader_Module_OverlapStudies
     */
    protected static $overlapStudies = null;

    /**
     * @var int
     */
    protected static $outBegIdx;

    /**
     * @var int
     */
    protected static $outNBElement;

    /**
     * Slow Stochastic Relative Strength Index.
     *
     * @param array $real         array of real values
     * @param int   $slowK_Period [OPTIONAL] [DEFAULT 3, SUGGESTED 1-200] Smoothing for making the Slow-K line. Valid range from 1 to 100000, usually set to 3.
     * @param int   $slowK_MAType [OPTIONAL] [DEFAULT TRADER_MA_TYPE_SMA] Type of Moving Average for Slow-K. MovingAverageType::* series of constants should be used.
     * @param int   $slowD_Period [OPTIONAL] [DEFAULT 3, SUGGESTED 1-200] Smoothing for making the Slow-D line. Valid range from 1 to 100000.
     * @param int   $slowD_MAType [OPTIONAL] [DEFAULT TRADER_MA_TYPE_SMA] Type of Moving Average for Slow-D. MovingAverageType::* series of constants should be used.
     *
     * @throws \Exception
     *
     * @return array Returns an array with calculated data. [SlowK => [...], SlowD => [...]]
     */
    public static function slowstochrsi(array $real, int $rsiPeriod = 14, int $fastKPeriod = 5, int $slowK_Period = 3, int $slowK_MAType = CTrader_Enum_MovingAverageType::SMA, int $slowD_Period = 3, int $slowD_MAType = CTrader_Enum_MovingAverageType::SMA): array {
        $real = \array_values($real);
        $endIdx = count($real) - 1;
        $rsi = [];
        self::checkForError(self::getMomentumIndicators()::rsi(0, $endIdx, $real, $rsiPeriod, self::$outBegIdx, self::$outNBElement, $rsi));
        $rsi = array_values($rsi);
        $endIdx = self::verifyArrayCounts([&$rsi]);
        $outSlowK = [];
        $outSlowD = [];
        self::checkForError(self::getMomentumIndicators()::stoch(0, $endIdx, $rsi, $rsi, $rsi, $fastKPeriod, $slowK_Period, $slowK_MAType, $slowD_Period, $slowD_MAType, self::$outBegIdx, self::$outNBElement, $outSlowK, $outSlowD));

        return [
            'SlowK' => self::adjustIndexes($outSlowK, self::$outBegIdx),
            'SlowD' => self::adjustIndexes($outSlowD, self::$outBegIdx),
        ];
    }

    protected static function prep() {
        self::$outBegIdx = 0;
        self::$outNBElement = 0;
    }

    /**
     * @throws \CTrader_Exception
     */
    protected static function checkForError(int $returnCode) {
        switch ($returnCode) {
            case CTrader_ReturnCode::SUCCESS:
                return;
            default:
                throw new \CTrader_Exception(CTrader_ReturnCode::getMessage($returnCode), $returnCode);
        }
    }

    /**
     * @return \CTrader_MomentumIndicators
     */
    protected static function getMomentumIndicators() {
        self::prep();
        if (\is_null(self::$momentumIndicators)) {
            self::$momentumIndicators = new CTrader_Module_MomentumIndicators();
            self::$momentumIndicators::construct();
        }

        return self::$momentumIndicators;
    }

    /**
     * @return \CTrader_Module_OverlapStudies
     */
    protected static function getOverlapStudies() {
        self::prep();
        if (\is_null(self::$overlapStudies)) {
            self::$overlapStudies = new CTrader_Module_OverlapStudies();
            self::$overlapStudies::construct();
        }

        return self::$overlapStudies;
    }

    protected static function adjustIndexes(array $outReal, int $offset): array {
        $newOutReal = [];
        $outReal = \array_values($outReal);
        foreach ($outReal as $index => $inDouble) {
            $newOutReal[$index + $offset] = $inDouble;
        }

        return $newOutReal;
    }

    /**
     * @throws \Exception
     *
     * @return int
     */
    protected static function verifyArrayCounts(array $arrays) {
        $count = count($arrays[0]);
        foreach ($arrays as &$array) {
            if (count($array) !== $count) {
                throw new \CTrader_Exception(CTrader_ReturnCode::getMessage(CTrader_ReturnCode::UNEVEN_PARAMETERS), CTrader_ReturnCode::UNEVEN_PARAMETERS);
            }
            $array = \array_values($array);
        }

        return $count - 1;
    }

    /**
     * Moving Average Convergence/Divergence.
     *
     * @param array $real         array of real values
     * @param int   $fastPeriod   [OPTIONAL] [DEFAULT 12, SUGGESTED 4-200] Number of period for the fast MA. Valid range from 2 to 100000.
     * @param int   $slowPeriod   [OPTIONAL] [DEFAULT 26, SUGGESTED 4-200] Number of period for the slow MA. Valid range from 2 to 100000.
     * @param int   $signalPeriod [OPTIONAL] [DEFAULT 9, SUGGESTED 1-200] Smoothing for the signal line (nb of period). Valid range from 1 to 100000.
     *
     * @throws \Exception
     *
     * @return array Returns an array with calculated data. [MACD => [...], MACDSignal => [...], MACDHist => [...]]
     */
    public static function macd(array $real, int $fastPeriod = 12, int $slowPeriod = 26, int $signalPeriod = 9): array {
        $real = \array_values($real);
        $endIdx = count($real) - 1;
        $outMACD = [];
        $outMACDSignal = [];
        $outMACDHist = [];
        self::checkForError(self::getMomentumIndicators()::macd(0, $endIdx, $real, $fastPeriod, $slowPeriod, $signalPeriod, self::$outBegIdx, self::$outNBElement, $outMACD, $outMACDSignal, $outMACDHist));

        return
            [
                'MACD' => self::adjustIndexes($outMACD, self::$outBegIdx),
                'MACDSignal' => self::adjustIndexes($outMACDSignal, self::$outBegIdx),
                'MACDHist' => self::adjustIndexes($outMACDHist, self::$outBegIdx),
            ];
    }

    /**
     * Simple Moving Average.
     *
     * @throws CTrader_Exception
     */
    public static function sma(array $real, int $timePeriod = 30): array {
        $real = \array_values($real);
        $outReal = [];
        self::checkForError(self::getOverlapStudies()::sma(0, count($real) - 1, $real, $timePeriod, self::$outBegIdx, self::$outNBElement, $outReal));

        return self::adjustIndexes($outReal, self::$outBegIdx);
    }

    /**
     * Exponential Moving Average.
     *
     * @throws CTrader_Exception
     */
    public static function ema(array $real, int $timePeriod = 30): array {
        $real = \array_values($real);
        $outReal = [];
        self::checkForError(self::getOverlapStudies()::ema(0, count($real) - 1, $real, $timePeriod, self::$outBegIdx, self::$outNBElement, $outReal));

        return self::adjustIndexes($outReal, self::$outBegIdx);
    }

    /**
     * Weighted Moving Average.
     *
     * @throws CTrader_Exception
     */
    public static function wma(array $real, int $timePeriod = 30): array {
        $real = \array_values($real);
        $outReal = [];
        self::checkForError(self::getOverlapStudies()::wma(0, count($real) - 1, $real, $timePeriod, self::$outBegIdx, self::$outNBElement, $outReal));

        return self::adjustIndexes($outReal, self::$outBegIdx);
    }

    /**
     * Relative Strength Index.
     *
     * @throws CTrader_Exception
     */
    public static function rsi(array $real, int $timePeriod = 14): array {
        $real = \array_values($real);
        $outReal = [];
        self::checkForError(self::getMomentumIndicators()::rsi(0, count($real) - 1, $real, $timePeriod, self::$outBegIdx, self::$outNBElement, $outReal));

        return self::adjustIndexes($outReal, self::$outBegIdx);
    }

    /**
     * Bollinger Bands.
     *
     * @param int $maType salah satu CTrader_Enum_MovingAverageType
     *
     * @throws CTrader_Exception
     *
     * @return array ['UpperBand' => array, 'MiddleBand' => array, 'LowerBand' => array]
     */
    public static function bbands(array $real, int $timePeriod = 5, float $deviationsUp = 2.0, float $deviationsDown = 2.0, int $maType = 0): array {
        $real = \array_values($real);
        $outRealUpperBand = [];
        $outRealMiddleBand = [];
        $outRealLowerBand = [];
        self::checkForError(self::getOverlapStudies()::bbands(
            0,
            count($real) - 1,
            $real,
            $timePeriod,
            $deviationsUp,
            $deviationsDown,
            $maType,
            self::$outBegIdx,
            self::$outNBElement,
            $outRealUpperBand,
            $outRealMiddleBand,
            $outRealLowerBand
        ));

        return [
            'UpperBand' => self::adjustIndexes($outRealUpperBand, self::$outBegIdx),
            'MiddleBand' => self::adjustIndexes($outRealMiddleBand, self::$outBegIdx),
            'LowerBand' => self::adjustIndexes($outRealLowerBand, self::$outBegIdx),
        ];
    }

    /**
     * Momentum.
     *
     * @throws CTrader_Exception
     */
    public static function mom(array $real, int $timePeriod = 10): array {
        $real = \array_values($real);
        $outReal = [];
        self::checkForError(self::getMomentumIndicators()::mom(0, count($real) - 1, $real, $timePeriod, self::$outBegIdx, self::$outNBElement, $outReal));

        return self::adjustIndexes($outReal, self::$outBegIdx);
    }

    /**
     * Average Directional Movement Index - kekuatan tren, bukan arahnya.
     *
     * @throws CTrader_Exception
     */
    public static function adx(array $high, array $low, array $close, int $timePeriod = 14): array {
        $endIdx = self::verifyArrayCounts([&$high, &$low, &$close]);
        $outReal = [];
        self::checkForError(self::getMomentumIndicators()::adx(0, $endIdx, $high, $low, $close, $timePeriod, self::$outBegIdx, self::$outNBElement, $outReal));

        return self::adjustIndexes($outReal, self::$outBegIdx);
    }

    /**
     * Commodity Channel Index.
     *
     * @throws CTrader_Exception
     */
    public static function cci(array $high, array $low, array $close, int $timePeriod = 14): array {
        $endIdx = self::verifyArrayCounts([&$high, &$low, &$close]);
        $outReal = [];
        self::checkForError(self::getMomentumIndicators()::cci(0, $endIdx, $high, $low, $close, $timePeriod, self::$outBegIdx, self::$outNBElement, $outReal));

        return self::adjustIndexes($outReal, self::$outBegIdx);
    }

    /**
     * Williams %R.
     *
     * @throws CTrader_Exception
     */
    public static function willR(array $high, array $low, array $close, int $timePeriod = 14): array {
        $endIdx = self::verifyArrayCounts([&$high, &$low, &$close]);
        $outReal = [];
        self::checkForError(self::getMomentumIndicators()::willR(0, $endIdx, $high, $low, $close, $timePeriod, self::$outBegIdx, self::$outNBElement, $outReal));

        return self::adjustIndexes($outReal, self::$outBegIdx);
    }

    /**
     * Aroon.
     *
     * Perhatikan urutannya: TA-Lib mengeluarkan Down lebih dulu, baru Up.
     * Menukarnya menghasilkan angka yang tetap masuk akal tetapi terbalik
     * artinya, dan itu tidak akan terlihat sebagai galat.
     *
     * @throws CTrader_Exception
     *
     * @return array ['AroonDown' => array, 'AroonUp' => array]
     */
    public static function aroon(array $high, array $low, int $timePeriod = 14): array {
        $endIdx = self::verifyArrayCounts([&$high, &$low]);
        $outAroonDown = [];
        $outAroonUp = [];
        self::checkForError(self::getMomentumIndicators()::aroon(0, $endIdx, $high, $low, $timePeriod, self::$outBegIdx, self::$outNBElement, $outAroonDown, $outAroonUp));

        return [
            'AroonDown' => self::adjustIndexes($outAroonDown, self::$outBegIdx),
            'AroonUp' => self::adjustIndexes($outAroonUp, self::$outBegIdx),
        ];
    }

    /**
     * Stochastic - garis SlowK dan SlowD.
     *
     * @throws CTrader_Exception
     *
     * @return array ['SlowK' => array, 'SlowD' => array]
     */
    public static function stoch(array $high, array $low, array $close, int $fastKPeriod = 5, int $slowKPeriod = 3, int $slowDPeriod = 3): array {
        $endIdx = self::verifyArrayCounts([&$high, &$low, &$close]);
        $outSlowK = [];
        $outSlowD = [];
        self::checkForError(self::getMomentumIndicators()::stoch(
            0,
            $endIdx,
            $high,
            $low,
            $close,
            $fastKPeriod,
            $slowKPeriod,
            CTrader_Enum_MovingAverageType::SMA,
            $slowDPeriod,
            CTrader_Enum_MovingAverageType::SMA,
            self::$outBegIdx,
            self::$outNBElement,
            $outSlowK,
            $outSlowD
        ));

        return [
            'SlowK' => self::adjustIndexes($outSlowK, self::$outBegIdx),
            'SlowD' => self::adjustIndexes($outSlowD, self::$outBegIdx),
        ];
    }

    /**
     * Rate of Change.
     *
     * @throws CTrader_Exception
     */
    public static function roc(array $real, int $timePeriod = 10): array {
        $real = \array_values($real);
        $outReal = [];
        self::checkForError(self::getMomentumIndicators()::roc(0, count($real) - 1, $real, $timePeriod, self::$outBegIdx, self::$outNBElement, $outReal));

        return self::adjustIndexes($outReal, self::$outBegIdx);
    }
}
