@php
if (!isset($label)) {
    $label = '[EMPTY LABEL]';
}
if (!isset($height)) {
    $height = '162px';
}
if (!isset($data)) {
    $data = [];
}
$id = uniqid();
@endphp
<div x-data="redisMetric()">
    <canvas id="{{ $id }}" style="width:100%; height:400px;"></canvas>
</div>

@CAppPushScript
<script>

    window.redisMetric = function() {
        // Chart di luar state Alpine: proxy reaktif pada objek Chart membuat chart.update() rekursi tanpa henti.
        let chart = null;
        let collection = null;

        return {
            interval: null,
            maxData:20,
            datasets: @json($datasets),
            pollingUrl: '{{ $pollingUrl }}',
            init() {
                if(window.cresenity) {
                    this.initChart();
                    this.fillData();
                    this.refreshPeriodically();
                    cresenity.on('reload:success',() => this.destroy());
                } else {
                    window.addEventListener('cresenity:loaded',()=>{
                        this.initChart();
                        this.fillData();
                        this.refreshPeriodically();
                        cresenity.on('reload:success',() => this.destroy());
                    });
                }

            },
            initChart() {
                chart = new Chart(document.getElementById('{{ $id }}').getContext("2d"), {
                    type: 'line',
                    data: [],

                    options: {
                        scales: {
                            xAxis: {
                                display: true,
                                scaleLabel: {
                                    display: true,
                                    labelString: 'Time'
                                }
                            },
                            yAxis: {
                                display: true,
                                scaleLabel: {
                                    display: true,
                                    labelString: 'MB'
                                }
                            }
                        },
                        legend: {
                            display: true
                        },
                        responsive: false,
                        maintainAspectRatio: false
                    }
                });
            },
            refreshPeriodically() {
                this.interval = setInterval(() => {
                    this.fillData();
                }, 3000);
            },
            destroy() {
                clearInterval(this.interval);
            },
            time () {
                const d = new Date();
                return d.getHours() + ":" + d.getMinutes() + ":" + d.getSeconds();
            },
            async fillData () {
                let response = await this.getData();
                if(collection==null) {
                    collection = {
                        labels: [],
                        datasets: [],
                    }

                }
                collection.labels.push(this.time());
                if(collection.labels.length>this.maxData) {
                    collection.labels = collection.labels.slice(-1 * this.maxData);
                }
                let index = 0;
                for(let i in this.datasets) {

                    if(collection.datasets.length < index+1) {
                        collection.datasets[index] = {
                            label: this.datasets[i].key,
                            fill: false,
                            backgroundColor: this.datasets[i].color,
                            borderColor: this.datasets[i].color,
                            data:[],
                        }
                    }
                    collection.datasets[index].data.push(response[this.datasets[i].key]);
                    if(collection.datasets[index].data.length>this.maxData) {
                        collection.datasets[index].data = collection.datasets[index].data.slice(-1 * this.maxData);
                    }
                    index++;
                }

                chart.data.labels = collection.labels;
                chart.data.datasets = collection.datasets;
                chart.update();
            },
            async getData() {
                return new Promise((resolve, reject) => {

                    $.ajax({
                        url: this.pollingUrl,
                        cache: false,
                        type: 'post',
                        dataType: 'json',
                        processData: false,
                        contentType: false,
                        success: function (responseData) {
                            if(typeof responseData.errCode === 'undefined') {
                                feWeb.showError('Unknown error');
                                return resolve(false);
                            }
                            if(responseData.errCode != 0) {
                                cresenity.toast({
                                    type:error,
                                    message:responseData.errMessage,

                                });
                                return resolve(false);
                            }

                            return resolve(responseData.data);
                        },
                        error: function (xhr, ajaxOptions, thrownError) {
                            let error = thrownError;

                            if(typeof thrownError == 'object') {
                                console.log(JSON.stringify(thrownError));
                                error = thrownError.error;
                            }
                            cresenity.toast({
                                type:error,
                                message:error,
                            });
                            return resolve(false);
                        },
                        complete: function () {


                        },
                    });
                });
            }
        }
    }

</script>
@CAppEndPushScript
