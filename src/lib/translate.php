<?php

namespace rkphplib\lib;


/**
 * Return translation from translation.$lang.php (e.g. translation.de.php).
 * Adjust SETTINGS_LANGUAGE (default = de) and load __DIR__/translation.$lang.php, 
 *
 * // Example language file (@[0] = default if language doesn't exist)
 * static $translation_map = ['@' => ['de', 'de', 'fr', ... ], 'msg_1' => ['Nachricht', 'Message', ... ], ... ] 
 *
 * // Example call: 
 * \rkphplib\lib\translate("variable p1x < p2x", [ 'age', 18 ]) -> return "Variable age < 18".
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function translate(string $msg, array $plist = []) : string {

	if (!defined('SETTINGS_LANGUAGE')) {
		define('SETTINGS_LANGUAGE', 'de');
	}

	$map_file = __DIR__.'/translation.'.SETTINGS_LANGUAGE.'php';

	if (file_exists($map_file)) {
		include_once $map_file;

		if (is_array($translation_map) && is_array($translation_map['@']) && is_array($translation_map[$msg])) {
			$lpos = array_search(SETTINGS_LANGUAGE, $translation_map['@']);
			$msg = $translation_map[$msg][$lpos];
		}
	}

	for ($i = 0; $i < count($plist); $i++) {
		$msg = str_replace('p'.($i + 1).'x', $plist[$i], $msg);
	}

	return $msg;
}
