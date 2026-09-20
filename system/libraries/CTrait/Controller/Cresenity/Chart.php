<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 *
 * @since Aug 30, 2019, 2:12:43 AM
 */
use CImage_Chart_Constant as Constant;

trait CTrait_Controller_Cresenity_Chart {
    public function line() {
        $data = CImage_Chart::createData();
        $data->addPoints([-4, Constant::VOID, Constant::VOID, 12, 8, 3], 'Probe 1');
        $data->addPoints([3, 12, 15, 8, 5, -5], 'Probe 2');
        $data->addPoints([2, 7, 5, 18, 19, 22], 'Probe 3');
        $data->setSerieTicks('Probe 2', 4);
        $data->setSerieWeight('Probe 3', 2);
        $data->setAxisName(0, 'Temperatures');
        $data->addPoints(['Jan', 'feb', 'Mar', 'apr', 'May', 'Jun'], 'Labels');
        $data->setSerieDescription('Labels', 'Months');
        $data->setAbscissa('Labels');

        /* Create the 1st chart */
        $image = CImage_Chart::createImage(700, 230, $data);
        $image->setGraphArea(60, 60, 450, 190);
        $image->drawFilledRectangle(60, 60, 450, 190, [
            'r' => 255,
            'g' => 255,
            'b' => 255,
            'surrounding' => -200,
            'alpha' => 10
        ]);
        $image->drawScale(['drawSubTicks' => true]);
        $image->setShadow(true, ['x' => 1, 'y' => 1, 'r' => 0, 'g' => 0, 'b' => 0, 'alpha' => 10]);
        $image->setFontProperties(['fontName' => CConstant::CRESENITY_FONT_PATH . '/pf_arma_five.ttf', 'fontSize' => 6]);
        $image->drawLineChart(['displayValues' => true, 'displayColor' => Constant::DISPLAY_AUTO]);
        $image->setShadow(false);

        /* Create the 2nd chart */
        $image->setGraphArea(500, 60, 670, 190);
        $image->drawFilledRectangle(500, 60, 670, 190, ['r' => 255, 'g' => 255, 'b' => 255, 'surrounding' => -200, 'alpha' => 10]);
        $image->drawScale(['pos' => Constant::SCALE_POS_TOPBOTTOM, 'drawSubTicks' => true]);
        $image->setShadow(true, ['x' => -1, 'y' => 1, 'r' => 0, 'g' => 0, 'b' => 0, 'alpha' => 10]);
        $image->drawLineChart();
        $image->setShadow(false);

        /* Write the legend */
        $image->drawLegend(510, 205, ['style' => Constant::LEGEND_NOBORDER, 'mode' => Constant::LEGEND_HORIZONTAL]);
        $image->autoOutput('example.drawLineChart.png');
    }
}
