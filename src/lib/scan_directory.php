<?php

namespace rkphplib\lib;

/**
 * Scan directory. PHP 5.x compatible. Necessary for bin/toogle, because php 7.0 does not know about 
 * nullable types (e.g. ?array) and therefore File and Dir class can not be used in bin/toggle.
 */
function scan_directory(string $path, array $suffix = []) : array {
	if (!is_dir($path)) {
		return [];
	}

	$entries = scandir($path);
	$res = [];

	foreach ($entries as $entry) {
		$e_path = $path.'/'.$entry;

		if ($entry == '.' || $entry == '..') {
			// ignore
		}
		else if (is_file($e_path)) {
			$found = true;

			if (count($suffix) > 0) {
				$found = false;

				for ($i = 0; !$found && $i < count($suffix); $i++) {
					$sl = strlen($suffix[$i]);
					if (substr($entry, -1 * $sl) == $suffix[$i]) {
						$found = true;
					}
				}
			}

			if ($found) {
				array_push($res, $e_path);
			}
		}
		else if (is_dir($e_path)) {
			$res = array_merge($res, scan_directory($e_path));
		}
	}

	return $res;
}

