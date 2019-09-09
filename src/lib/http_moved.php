<?php

namespace rkphplib\lib;


/**
 * Send new location header and exit. Use $code=310 for permanent (default)
 * and 302 for temporary moved resources.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function http_moved($url, $code = 301) {
	http_response_code($code);
	header('Location: '.$url);
	exit();
}
