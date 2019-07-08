<?php

namespace rkphplib\lib;


/**
 * Send "HTTP/1.0 $code $error" header and exit.
 *
 * @exit
 * @param int $code (=400|401|404)
 */
function http_error($code = 400) {
	$error = [ '400' => 'Bad Request', '401' => 'Unauthorized', '404' => 'Not Found'];

	if (!isset($error[$code])) {
		$code = 400;
	}

	if (php_sapi_name() == 'cli') {
		print "\nABORT: HTTP/1.0 ".$code.' '.$error[$code]."\n\n";
	}
	else {
		header('HTTP/1.0 '.$code.' '.$error[$code]);
	}

	exit(1);
}
