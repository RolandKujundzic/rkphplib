<?php

/**
 * Return [ sid, level]. Call category_sid_level(null) to reset counter.
 * Max 57 entries per level.
 * @function category_sid
 */
function category_sid_level(?string $id, ?string $pid = null) : array {
	static $last_pid = null;
	static $last_level = 1;
	static $id_pid = [];
	static $id_sid = [];
	static $ln = [ 1 ];

	static $az = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
		'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K',
		'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V',
		'W', 'X', 'Y', 'Z',
		'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k',
		'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v',
		'w', 'x', 'y', 'z' ];

	if (is_null($id)) {
		$last_pid = null;
		$last_level = 1;
		$id_pid = [];
		$id_sid = [];
		$ln = [];

		return [];
	}

	if ($pid === '') {
		$pid = null;
	}

	$level = 1;
	$sid = '';

	if (array_key_exists($pid, $id_pid)) {
		$sid = $id_sid[$pid];
		$level = strlen($sid) + 1;
	}

	if ($last_level < $level) {
		array_push($ln, 1);
	}

	$n = $ln[$level - 1];
	$sid .= $az[$n];

	$ln[$level - 1]++;

	if ($last_level > $level) {
		array_pop($ln);
	}

	$id_pid[$id] = $pid;
	$id_sid[$id] = $sid;
	$last_level = $level;
	$last_pid = $pid;

	return [ $sid, $level ];
}

