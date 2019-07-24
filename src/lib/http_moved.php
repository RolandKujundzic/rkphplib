<?php

namespace rkphplib\lib;


/**
 * Send header and new location url.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @exit 0
 * @param string $url new resource url
 * @param integer $code 301=permanent=default|302=temporary
 */
function http_moved($url, $code = 301) {
	http_response_code($code);
	header('Location: '.$url);
	exit();
}
