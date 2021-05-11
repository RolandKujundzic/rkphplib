<?php

require_once '../settings.php';

require_once __DIR__.'/../../function/category_sid_level.php';

function cat_tree(string $cat_str) : void {
	$cat = explode("\n", trim($cat_str));
	printf("%4s|%6s|%4s|%4s|%10s\n", 'ID', 'PID', 'SID', 'LVL', 'NAME');
	foreach ($cat as $line) {
		list ($id, $pid, $name) = explode(',', trim($line));
		list ($sid, $level) = category_sid_level($id, $pid);
		printf("%4s|%6s|%4s|%4s|%10s\n", $id, $pid, $sid, $level, $name);
	}
}

global $th;

$th->run(1, 2);
