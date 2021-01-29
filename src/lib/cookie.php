<?php
  
namespace rkphplib\lib;

/**
 * Set cookie value. Use expires = -1 hour or value = null to remove cookie.
 * Use strtotime expression for expires, e.g. +1 day|week|hour or 1 month 2 hour. 
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 * @hash $opt …
 * expires: 0 (or 300=5min, 365d, 24h)
 * path: '' (e.g. / or /subdir)
 * domain: '' (e.g. $_SERVER['HTTP_HOST'])
 * secure: autodetect (1 = allow only ssl)
 * httponly: 0 (1 = forbid javascript access to cookie)
 * samesite: Strict (None|Lax|Strict)
 * @eol
 */
function cookie(string $name, ?string $value, array $opt = []) : void {
	$copt = [
		'expires' => 0,
		'path' => '',
		'domain' => '',
		'secure' => $_SERVER['REQUEST_SCHEME'] == 'https',
		'httponly' => 0,
		'samesite' => 'strict'
	];
	
	if (!empty($opt['expires'])) {
		$opt['expires'] = strtotime($opt['expires']);
	}

	foreach ($copt as $key) {
		if (isset($opt[$key])) {
			$copt[$key] = $opt[$key];
		}
	}

	if (is_null($value)) {
		if (isset($_COOKIE[$name])) {
			$copt['expires'] = time() - 3600;
			// \rkphplib\lib\log_debug([ "cookie:42> remove $name <1>", $copt ]);
			setcookie($name, '', $copt);
			unset($_COOKIE[$name]);
		}
	}
	else {
		// \rkphplib\lib\log_debug([ "cookie:48> set '$name'='$value' <1>", $copt ]);
		setcookie($name, $value, $copt);
		$_COOKIE[$name] = $value;
	}
}

