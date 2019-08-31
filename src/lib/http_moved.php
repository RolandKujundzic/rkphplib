<?php

namespace rkphplib\lib;


/**
 * Send new location header and exit. Use $code=310 for permanent (default)
 * and 302 for temporary moved resources.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function http_moved(string $url, int $code = 301) : void {
	http_response_code($code);
	header('Location: '.$url);
	exit();
}
