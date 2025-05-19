<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="row">
    <div class="col-md-12">

        <div id="log-ingestion" class="panel panel-default">
            <div class="panel-heading">
                <div class="clearfix">
                    <div class="pull-left">
                        <h3>Log Ingestion</h3>
                        <h4>Powered by OpenObserve</h4>
                    </div>
                    <div class="pull-right">	
                        <a href="/console/devices/log-ingestion/export/csv" id="export-btn" data-toggle="modal" data-target="#decision_modal" class="btn btn-default btn-sm disabled"><i class="fad fa-download"></i> Export</a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle action-btn" disabled="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?php echo $btn_text; ?> <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu pull-right with-icons action-items-menu">
                            <?php foreach ($interval_dropdown as $idx => $item): ?>
                                <?php if ($idx === 0) { ?>
                                <li><a tabindex="-1" href="/console/devices/log-ingestion/<?php echo $item['interval']; ?>"><i <?php echo(!$this->uri->segment(4) || $this->uri->segment(4) === $item['interval'])?'class="glyphicon glyphicon-ok-sign"':'class="icon-blank"'; ?>></i> <?php echo $item['month_year']; ?></a></li>
                                <?php } else { ?>
                                <li><a tabindex="-1" href="/console/devices/log-ingestion/<?php echo $item['interval']; ?>"><i <?php echo($this->uri->segment(4) === $item['interval'])?'class="glyphicon glyphicon-ok-sign"':'class="icon-blank"'; ?>></i> <?php echo $item['month_year']; ?></a></li>
                                <?php } ?>
                            <?php endforeach; ?>
                            </ul>
                        </div>   
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="ingestion-data text-center">
                            <h4>Total Usage</h4>
                            <div class="total-ingestion">
                                <i class="fad fa-spinner-third fa-spin"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="ingestion-data text-center">
                            <h4>Month-to-Date Cost</h4>
                            <div class="ingestion-cost">
                                <i class="fad fa-spinner-third fa-spin"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div id="ingestion-chart">
                            <div class="top_chart_loader">
                                <div class="item-number">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-footer">
                <hr>
                <div class="timezone-notice text-right">
                    Data is stored in UTC &amp; displayed in <?php echo $timezone === 'UTC' ? 'the UTC time zone' : 'your time zone preference'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="hidden" id="log-ingestion-interval" data-interval="<?php echo $interval; ?>"></div>

<script>
    load_log_ingestion_chart = function(dataSet)
    {
        let log_ingestion_chart = new Highcharts.Chart({
            chart: {
                type: 'column',
                renderTo: 'ingestion-chart'
            },
            credits: {
                enabled: false
            },
            exporting: {
                enabled: false
            },
            accessibility: {
                enabled: false
            },
            legend: {
                enabled: false
            },
            title: {
                text: dataSet.chart_title,
                style: {
                    fontSize: '16px',
                    fontWeight: '300'
                } 
            },
            tooltip: {
                formatter: function () {
                    let bytes   = this.y,
                        gb      = bytes / 1000000000,
                        cost_gb = dataSet.cost_gb,
                        i       = parseInt(Math.floor(Math.log(bytes) / Math.log(1000)), 10),
                        sizes   = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

                    if (bytes === 0) {
                        return false;
                    }

                    if (i === 0) {
                        return `<table><tr><th>Usage:&nbsp;</th><td>${bytes} ${sizes[i]}</td></tr><tr><th>Cost:</th><td>$0.00</td></tr></table>`;
                    } else {
                        return `<table><tr><th>Usage:&nbsp;</th><td>${(bytes / (1000 ** i)).toFixed(2)} ${sizes[i]}</td></tr><tr><th>Cost:</th><td>$${(gb * cost_gb).toFixed(2)}</td></tr></table>`;
                    }
                },
                useHTML: true,
                hideDelay: 200
            },
            xAxis: {
                categories: dataSet.categories,
                tickWidth: 1,
                type: 'datetime',
                labels: {
                    format: '{value:%e}'
                }
            },
            yAxis: {
                min: 0,
                title: {
                    text: 'Usage'
                }
            },
            series: [{
                data: dataSet.series_data
            }],
            lang: {
                noData: 'No Data Available'
            },
            noData: {
                style: {
                    fontWeight: 'bold',
                    fontSize: '13px',
                    color: '#303030'
                }
            }
        });
    }
</script>
