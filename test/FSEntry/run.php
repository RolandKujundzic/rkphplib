<?php

require_once '../settings.php';
require_once PATH_SRC.'FSEntry.class.php';


function suffix_list(array $list) : void {
	global $suffix_list;
	$suffix_list = \rkphplib\FSEntry::fixSuffixList($list);
	print "\nsuffix_list: ".json_encode($suffix_list)."\n";
}


function check(string $file) : void {
	global $suffix_list;

	if (\rkphplib\FSEntry::hasSuffix($file, $suffix_list)) {
		print "match $file\n";
	}
	else {
		print "no match $file\n";
	}
}


global $th;

$th->run(1, 2);

