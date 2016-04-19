<?php

global $th;

if (!isset($th)) {
	require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
	$th = new rkphplib\TestHelper();
}

$th->load('src/DateCalc.class.php');
$th->runFuncTest('sql2num');
$th->runFuncTest('max');
$th->runFuncTest('sqlTS');
$th->runFuncTest('sqlAddMonth');
$th->runFuncTest('date2unix');
$th->runFuncTest('dmy2unix');
$th->runFuncTest('date2dmyhis');
$th->runFuncTest('date2dmy');
$th->runFuncTest('sql_date');
$th->runFuncTest('min_sec');
$th->runFuncTest('kw');
$th->runFuncTest('hms2sec');
$th->runFuncTest('sec2hms');
$th->runFuncTest('nowstr2time');
$th->runFuncTest('nextMonth');
$th->runFuncTest('prevMonth');
$th->runFuncTest('lastDay');
$th->runFuncTest('sqlDayOfYear');
$th->runFuncTest('formatDateStr');
$th->runFuncTest('formatDateTimeStr');
