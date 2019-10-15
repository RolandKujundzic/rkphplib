<?php

namespace rkphplib\lib;


/**
 * Send new location header and exit. Use $code=310 for permanent (default)
 * and 302 for temporary moved resources. Print header in cli mode 
 * (use CLI_NO_EXIT=1 to display exit).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function http_moved(string $url, int $code = 301) : void {
	if (php_sapi_name() == 'cli') {
		switch ($code) {
			case 300: $text = 'Multiple Choices'; break;
			case 301: $text = 'Moved Permanently'; break;
			case 302: $text = 'Moved Temporarily'; break;
			case 303: $text = 'See Other'; break;
			case 304: $text = 'Not Modified'; break;
			case 305: $text = 'Use Proxy'; break;
			default:
				exit('Unknown http status code "' . htmlentities($code) . '"');
		}

		print "HTTP/1.1 $code $text\r\nLocation: $url\r\n\r\n";
		(defined('CLI_NO_EXIT') && !empty(CLI_NO_EXIT)) || exit();
		return;
	}

	http_response_code($code);
	header('Location: '.$url);
	exit();
}
