<?php

/**
 * Fungsi statistik TA-Lib.
 *
 * Modul ini tidak pernah ikut ter-port, padahal OverlapStudies::bbands()
 * memanggilnya: pada maType selain SMA, bbands mengambil cabang
 * `StatisticFunctions::stdDev(...)` dan kelas itu tidak ada di mana pun -
 * fatal seketika. Cabang SMA memakai jalur lain
 * (TA_INT_stddev_using_precalc_ma) sehingga tidak pernah tersentuh, dan itulah
 * sebabnya kekurangan ini bertahan lama tanpa ketahuan.
 *
 * Isinya mengikuti TA-Lib asli: simpangan baku dihitung dari
 * mean(x^2) - mean(x)^2 atas rata-rata bergerak sederhana periode yang sama,
 * bukan atas rata-rata jenis lain. Itu bukan penyederhanaan - TA_STDDEV di
 * hulu memang menghitung SMA-nya sendiri lebih dulu lalu memakai helper yang
 * sama, jadi hasilnya identik dengan cabang SMA bila keduanya dijalankan atas
 * masukan yang sama.
 */
class CTrader_Module_StatisticFunctions extends CTrader_ModuleAbstract {
    /**
     * Standard Deviation.
     *
     * @param float[] $inReal
     * @param float   $optInNbDev pengali simpangan baku
     * @param float[] $outReal
     */
    public static function stdDev(int $startIdx, int $endIdx, array $inReal, int $optInTimePeriod, float $optInNbDev, int &$outBegIdx, int &$outNBElement, array &$outReal): int {
        if ($returnCode = static::validateStartEndIndexes($startIdx, $endIdx)) {
            return $returnCode;
        }
        if ($optInTimePeriod < 2 || $optInTimePeriod > 100000) {
            return CTrader_ReturnCode::BAD_PARAM;
        }

        //rata-rata bergerak sederhana lebih dulu, persis seperti TA_STDDEV
        $movingAverage = [];
        $returnCode = CTrader_Module_OverlapStudies::sma($startIdx, $endIdx, $inReal, $optInTimePeriod, $outBegIdx, $outNBElement, $movingAverage);
        if ($returnCode != CTrader_ReturnCode::SUCCESS || (int) $outNBElement == 0) {
            $outNBElement = 0;

            return $returnCode;
        }

        static::TA_INT_stddev_using_precalc_ma($inReal, $movingAverage, (int) $outBegIdx, (int) $outNBElement, $optInTimePeriod, $outReal);

        if ($optInNbDev != 1.0) {
            for ($i = 0; $i < (int) $outNBElement; $i++) {
                $outReal[$i] *= $optInNbDev;
            }
        }

        return CTrader_ReturnCode::SUCCESS;
    }
}
