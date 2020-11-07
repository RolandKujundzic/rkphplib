<?php

namespace rkphplib\lib;


/**
 * Output $html and close connection but continue processing
 * @param $html
 */
function close_connection(string $html, int $responseCode = 200) : void {
	set_time_limit(0);
	session_write_close();
	ignore_user_abort(true);
	ob_end_clean();

	ob_start();
	print $html;
	$size = ob_get_length();

	header("Connection: close\r\n");
	header("Content-Encoding: none\r\n");
	header("Content-Length: $size");

	http_response_code($responseCode);
	ob_end_flush();
	@ob_flush();
	flush();
}

