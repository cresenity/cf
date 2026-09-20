<?php

defined('SYSPATH') or die('No direct access allowed.');

class CImage_Chart_Runner extends CController {
    public function __construct() {
        parent::__construct();
    }

    public function imageLine() {
        $request = TB::getRequest();
        $dataArray = carr::get($request, 'data', []);
        $width = carr::get($request, 'width', 500);
        $height = carr::get($request, 'height', 200);

        if (strlen($dataArray) > 0) {
            $dataArray = explode(',', $dataArray);
        }

        $data = $data = CImage_Chart::createData();
        $data->addPoints($dataArray, 'Probe 3');
        $data->setSerieTicks('Probe 2', 4);
        $data->setSerieWeight('Probe 3', 2);
        $data->setAxisName(0, 'Temperatures');
        $data->addPoints($dataArray, 'Labels');
        $data->setSerieDescription('Labels', 'Months');
        $data->setAbscissa('Labels');

        /* Create the 1st chart */
        $image = CImage_Chart::createImage($width, $height, $data);
        $image->setGraphArea(0, 0, $width, $height);
        $image->drawFilledRectangle(0, 0, $width, $height, [
            'r' => 255,
            'g' => 255,
            'b' => 255,
            'surrounding' => -200,
            'alpha' => 10,
            'Angle' => 90
        ]);
        $image->drawScale(['drawSubTicks' => true]);
        $image->setShadow(true, ['x' => 1, 'y' => 1, 'r' => 0, 'g' => 0, 'b' => 0, 'alpha' => 10]);
        $image->setFontProperties(['fontName' => CConstant::CRESENITY_FONT_PATH . '/pf_arma_five.ttf', 'fontSize' => 6]);
        $image->drawLineChart(['displayColor' => 691001]);
        $image->setShadow(false);

        $image->autoOutput('example.drawLineChart.png');
    }

    public function json() {
        $json = CHTTP::request()->get('c', '');

        $data = json_decode($json, true);
        cdbg::dd($json, $data, json_last_error_msg());
    }

    public function __call($method, $args) {
        $chartType = CHTTP::request()->get('cht', '');
        $chartSize = CHTTP::request()->get('chs', '');
        $chartLabel = CHTTP::request()->get('chl', '');
        $chartData = CHTTP::request()->get('chd', '');

        $chartSizeArray = explode('x', $chartSize);
        $chartWidth = carr::get($chartSizeArray, 0, '250');
        $chartHeight = carr::get($chartSizeArray, 1, '250');
        if (!is_numeric($chartWidth)) {
            $chartWidth = '250';
        }
        if (!is_numeric($chartHeight)) {
            $chartHeight = '250';
        }
        $data = CImage_Chart::createData();

        $defaultDataLabel = null;
        if (cstr::startsWith($chartData, 't:')) {
            $chartData = substr($chartData, 2);
            $chartDataArray = explode('|', $chartData);
            foreach ($chartDataArray as $chda) {
                $seriesArray = explode(',', $chda);
                if ($defaultDataLabel === null) {
                    $defaultDataLabel = $seriesArray;
                }
                $data->addPoints($seriesArray, 'Score A');
            }
        }
        $data->setSerieDescription('Score A', 'Application A');

        $chartLabelArray = explode('|', $chartLabel);
        $haveLabel = is_array($chartLabelArray) && count($chartLabelArray) > 0 && strlen($chartLabelArray[0]) > 0;
        if (!$haveLabel) {
            $chartLabelArray = $defaultDataLabel;
        }
        $data->addPoints($chartLabelArray, 'Labels');
        $data->setAbscissa('Labels');
        /* Create the image */
        $image = CImage_Chart::createImage($chartWidth, $chartHeight, $data);
        $pie = CImage_Chart::createPie($image, $data);

        $centerX = $chartWidth / 2;
        $centerY = $chartHeight / 2;
        $radius = min($centerX, $centerY) - 10;

        $chartOptions = [
            //'radius' => $radius,
            'drawLabels' => $haveLabel,
            'border' => true,
            'labelStacked' => true,
        ];

        if ($chartType == 'p3') {
            $pie->draw3DPie($centerX, $centerY, $chartOptions);
        }

        if ($chartType == 'p') {
            $chartOptions['valuePosition'] = CImage_Chart_Constant::PIE_VALUE_NATURAL;
            //$chartOptions['valuePosition'] = CImage_Chart_Constant::PIE_VALUE_INSIDE;
            //$chartOptions['labelStacked'] = false;
            $pie->draw2DPie($centerX, $centerY, $chartOptions);
        }

        $image->autoOutput('chart.png');
        //echo $image->render('example.draw2DPie.png');
    }

    public function google() {
        $piChart = new CImage_QuickChart_PieChart();
        // or if you installed via composer
        // $piChart = new gchart\gPieChart();

        $piChart->addDataSet([112, 315, 66, 40]);
        $piChart->setLabels(['first', 'second', 'third', 'fourth']);
        $piChart->setLegend(['first', 'second', 'third', 'fourth']);
        $piChart->setColors(['ff3344', '11ff11', '22aacc', '3333aa']);
        $piChart->renderImage();
    }

    public function passthru() {
        $url = 'http://chart.apis.google.com/chart';
        $get = $_GET;
        $get['chid'] = md5(uniqid(rand(), true));
        $url .= '?' . curl::asPostString($get);

        try {
            $context = stream_context_create(
                ['http' => [
                    'method' => 'GET',
                    'header' => 'Content-type: application/x-www-form-urlencoded' . "\r\n",
                ]]
            );
            fpassthru(fopen($url, 'r', false, $context));
            header('Content-type: image/png');
        } catch (Exception $ex) {
            $response = [];
            $response['errCode'] = '1';
            $response['errMessage'] = $ex->getMessage();
            $response['data'] = [];
            $response['data']['exception'] = get_class($ex); // Reflection might be better here
            $response['data']['trace'] = $ex->getTraceAsString();

            return c::response()->json($response);
        }
    }
}
