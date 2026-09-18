<?php

/**
 * Description of StyleRenderer
 */
class CProfiler_StyleRenderer {
    public function render() {
        $coreStyle = '
#cf-profiler-button {
    position:fixed;
    bottom:0;
    right:0;
    padding:5px 10px;
    background:#fff;
    border:1px solid #F8FFF8;
    min-width:100px;
    z-index:9999;
}
#cf-profiler {
    font-family: Monaco, \'Courier New\';
    background-color: #F8FFF8;
    margin-top: 20px;
    clear: both;
    padding: 10px 10px 0;
    border: 1px solid #E5EFF8;
    text-align: left;
    display:none;
    position:fixed;
    z-index:9998;
    left:0;
    top:0;
    bottom:0;
    overflow-y:auto;
    right:0;
}
#cf-profiler.active {
    display:block;
}
#cf-profiler pre {
    margin: 0;
    font: inherit;
}
#cf-profiler .cp-meta {
    margin: 0 0 10px;
    padding: 4px;
    background: #FFF;
    border: 1px solid #E5EFF8;
    color: #A6B0B8;
    text-align: center;
}';
        $tableStyle = '
#cf-profiler .kp-table {

	font-size: 1.0em;
	color: #4D6171;
	width: 100%;
	border-collapse: collapse;
	border-top: 1px solid #E5EFF8;
	border-right: 1px solid #E5EFF8;
	border-left: 1px solid #E5EFF8;
	margin-bottom: 10px;
}
#cf-profiler .kp-table td {
	background-color: #FFFFFF;
	border-bottom: 1px solid #E5EFF8;
	padding: 3px;
	vertical-align: top;
}
#cf-profiler .kp-table .kp-title td {
	font-weight: bold;
	background-color: inherit;
}
#cf-profiler .kp-table .kp-altrow td {
	background-color: #F7FBFF;
}
#cf-profiler .kp-table .kp-totalrow td {
	background-color: #FAFAFA;
	border-top: 1px solid #D2DCE5;
	font-weight: bold;
}
#cf-profiler .kp-table .kp-column {
	width: 100px;
	border-left: 1px solid #E5EFF8;
	text-align: center;
}
#cf-profiler .kp-table .kp-data, #cf-profiler .kp-table .kp-name {
	background-color: #FAFAFB;
	vertical-align: top;
}
#cf-profiler .kp-table .kp-name {
	width: 200px;
	border-right: 1px solid #E5EFF8;
}
#cf-profiler .kp-table .kp-altrow .kp-data, #cf-profiler .kp-table .kp-altrow .kp-name {
	background-color: #F6F8FB;
}';

        return $coreStyle . $tableStyle;
    }
}
