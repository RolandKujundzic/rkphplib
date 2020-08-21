<?php

namespace rkphplib\lib;

/**
 * Return true if connection is ssl secured.
 */
function is_ssl() : bool {
	return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ||
		(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
		(isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') ||
		(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') || 
		(isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on');
}

