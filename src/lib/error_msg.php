<?php

namespace rkphplib\lib;

require_once(__DIR__.'/log_error.php');


/**
 * Return localized error message from error_msg.lang.php and save error to error_log.
 *
 * Adjust $settings_LANGUAGE and load __DIR__/error_msg.$lang.php, Example language file:
 *
 * <?php
 *
 * static $error_msg_map = ['@' => ['de', 'de', 'fr', ... ], 'msg_1' => ['Nachricht', 'Message', ... ], ... ] // @[0] = default if language doesn't exist
 *
 * Example call: error_msg("variable p1x < p2x", array('age', 18), 1)
 * -> return "Variable age < 18" and save to default error_log.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param string $text
 * @param array $param_replace
 * @param string 0|1|filename 
 * @return string
 */
function error_msg($msg, $plist = array(), $log_error = 1) {
	global $settings_LANGUAGE;

	$msg_file = __DIR__.'/error_msg.'.$settings_LANGUAGE.'php';

	if (file_exists($msg_file)) {
		include_once($msg_file);

		if (is_array($error_msg_map) && is_array($error_msg_map['@']) && is_array($error_msg_map[$msg])) {
			$lpos = array_search($settings_LANGUAGE, $error_msg_map['@']);
			$msg = $error_msg_map[$msg][$lpos];
		}
	}

	for ($i = 0; $i < count($plist); $i++) {
		$msg = str_replace('p'.($i + 1).'x', $plist[$i], $msg);
	}

	log_error($msg, $log_error);

	return $msg;
}

