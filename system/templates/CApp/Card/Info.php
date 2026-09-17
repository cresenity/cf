<?php
defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since Jun 13, 2018, 9:52:34 AM
 */
//generate uniqid
$uniqid = uniqid(time());
$manager = CManager::instance();
if (!$manager->isRegisteredModule('chartjs')) {
    $manager->registerModule('chartjs');
}
if (!isset($chart) || !is_array($chart)) {
    $chart = [];
}
if (!isset($properties) || !is_array($properties)) {
    $properties = [];
}
$chartPercent = carr::get($chart, 'percent');
$chartColor = carr::get($chart, 'color');
?>


<div class="card card-info mb-3">
    <div class="card-body">

        <div class="media align-items-center">
            <div class="media-body small mr-3">
                <div class="font-weight-semibold mb-3"><?php echo $title; ?></div>
                <?php
                $iProp = 0;
                foreach ($properties as $prop): ?>
                    <?php
                    $propClass = '';
                    if ($iProp < count($properties)) {
                        $propClass = 'mb-1';
                    }
                    $label = carr::get($prop, 'label');
                    $value = carr::get($prop, 'value');
                    ?>
                    <div class="<?php echo $propClass; ?>"><?php echo $label; ?>: <?php echo $value; ?></div>

                    <?php
                    $iProp++;
                endforeach;
                ?>
            </div>
            <div class="d-flex align-items-center position-relative" style="height:60px;width: 60px;">
                <div class="w-100 position-absolute" style="height:60px;top:0;">
                    <canvas id="<?php echo $uniqid; ?>-chart" width="60" height="60"></canvas>
                </div>
                <div class="w-100 text-center font-weight-bold"><?php echo $chartPercent; ?>%</div>
            </div>
        </div>
    </div>

</div>

<script>
    var chart2 = new Chart(document.getElementById('<?php echo $uniqid; ?>-chart').getContext("2d"), {
        type: 'doughnut',
        data: {
            datasets: [{
                    data: [<?php echo $chartPercent; ?>, <?php echo 100 - $chartPercent; ?>],
                    backgroundColor: ['<?php echo $chartColor; ?>', 'rgba(255, 255, 255, .1)'],
                    hoverBackgroundColor: ['<?php echo $chartColor; ?>', 'rgba(255, 255, 255, .1)'],
                    borderWidth: 0
                }]
        },

        options: {
            scales: {
                xAxes: [{
                        display: false,
                    }],
                yAxes: [{
                        display: false
                    }]
            },
            legend: {
                display: false
            },
            tooltips: {
                enabled: false
            },
            cutoutPercentage: 94,
            responsive: false,
            maintainAspectRatio: true
        }
    });
</script>
