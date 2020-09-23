<?php

require_once '../settings.php';

function xml_tag(string $tag, string $text, array $attrib, string $path) {
	$text = strlen($text) > 0 ? ">$text</$tag>" : '/>';

	$attrib_str = '';
	foreach ($attrib as $key => $value) {
		$attrib_str .= " $key='$value'";
	}

	print "<$tag$attrib_str$text\n";
}


global $th;

$th->run(1, 6);

