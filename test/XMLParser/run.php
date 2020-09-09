<?php

$src = dirname(dirname(__DIR__)).'/src/';
require_once $src.'XMLParser.php';


/**
 *
 */
function xml_tag(string $tag, string $text, array $attrib, string $path) {
	$text = strlen($text) > 0 ? ">$text</$tag>" : '/>';

	$attrib_str = '';
	foreach ($attrib as $key => $value) {
		$attrib_str .= " $key='$value'";
	}

	print "<$tag$attrib_str$text\n";
}


/*
 * M A I N
 */

global $th;
if (!isset($th)) {
	require_once $src.'TestHelper.class.php';
	$th = new \rkphplib\TestHelper();
}


$n = 5;

for ($i = 1; $i <= $n; $i++) {
	$th->execPHP($i);
}

