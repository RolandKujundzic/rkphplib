<?php

namespace rkphplib;

require_once __DIR__.'/traits/Options.php';
require_once __DIR__.'/JSON.class.php';
require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Dir.class.php';
require_once __DIR__.'/XML.class.php';


/**
 * Curl wrapper.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Curl {

use \rkphplib\traits\Options;

// @var string $cookiejar
private $cookiejar = '';

// @var string $url
private $url = ''; 

// @var array $header
private $header = [];

// @var array $opt
private $opt = [];

// @var array $cache 
private $cache = [];


/**
 * Options: cookiejar, url, header.name 
 */
public function __construct(array $options = []) {
	$this->setOptions($options);
}


/**
 *
 */
public function setCache($name, $ttl) {
	$this->cache[$name] = $ttl;
}


/**
 * Set header $name = $value. Use $keep = true to keep header in all requests.
 */
public function setHeader(string $name, string $value, bool $keep = false) : void {
	if ($keep) {
		$this->header[$name] = $value;
	}
	else {
		if (!isset($this->opt['HTTPHEADER'])) {
			$this->opt['HTTPHEADER'] = [];
		}

		array_push($this->opt['HTTPHEADER'], $name.': '.$value);
	}
}


/**
 * Set cookiejar (CURLOPT_COOKIEJAR, CURLOPT_COOKIEFILE).
 */
public function cookiejar(string $file) : void {
	Dir::create(dirname($file));
	$this->cookiejar = $file;
}


/**
 * Set method (reset after curl_call)
 */
public function method(string $method = 'GET') : void {
	if ($method == 'GET') {
		$this->opt['HTTPGET'] = true;
	}
	else if ($method == 'POST') {
		$this->opt['POST'] = true;
	}
	else if (in_array($method, [ 'CONNECT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH', 'PUT', 'TRACE' ])) {
		$this->header['X-HTTP-Method-Override'] = $method;
		$this->opt['CUSTOMREQUEST'] = $method;
	}
	else {
		throw new Exception("invalid method [$method]");
	}
}


/**
 * Set post hash (only valid for next request).
 */
public function post(array $data) : void {
	$this->method('POST');

	if (empty($this->header['Content-Type'])) {
		throw new Exception('empty Content-Type header');
	}

	$ct = $this->header['Content-Type'];
	
	if ($ct == 'application/xml') {
		$data = XML::fromMap($data);
	}
	else if ($ct == 'application/json') {
		$data = JSON::encode($data); 
	}
	else if ($ct == 'application/x-www-form-urlencoded') {
		$data = http_build_query($data);
	}
	else {
		throw new Exception('invalid Content-Type header', $ct);
	}

	$this->opt['POSTFIELDS'] = $data;
}


/**
 * Call authentication url and save cookies to cookiejar. 
 * If $this->header[REFERER] use cookiejar (call createCookiejar() first).
 * Use setCache('ttl', 600) to keep authentication valid for 10 min.
 */
public function auth(string $url) : void {
	if (empty($this->cookiejar)) {
		throw new Exception('cookiejar is not set');
	}

	if ($this->cacheOk('auth', $this->cookiejar)) {
		$this->opt = [];
		return;
	}

	if (!empty($this->header['REFERER'])) {
		File::exists($this->cookiejar, true);
		$this->opt['COOKIEFILE'] = $this->cookiejar;
	}
	else {
		$this->opt['COOKIEJAR'] = $this->cookiejar;
	}

	if (!empty($this->opt['POST'])) {
		$this->setPostString($url);
	}

	$this->call_curl($url);
	File::exists($this->cookiejar, true);
}


/**
 * Return true if HEAD request to $url result code matches $ok_rx.
 * Default ok result: 20[0-8], 30[0-8], 401 (unauthorized), 403 (forbidden), 404 (not found).
 */
public static function check(string $url, int $timeout = 3, string $ok_rx = '[23]0[0-8]|40[134]') : bool {
	$ch = curl_init($url);

	curl_setopt_array($ch, array(
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_NOBODY => true,
		CURLOPT_TIMEOUT => $timeout,
		CURLOPT_USERAGENT => 'check/1.0'
	));

	// \rkphplib\lib\log_debug("Curl::check:176> call $url");
	curl_exec($ch);

	if (curl_errno($ch)) {
		curl_close($ch);
		return false;
	}

	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	// \rkphplib\lib\log_debug("Curl::check:187> $code");
  return preg_match('/^('.$ok_rx.')$/', $code);
}


/**
 * Return url content.
 */
public function get(string $url) : string {
	if (!empty($this->cookiejar)) {
		if (File::exists($this->cookiejar)) {
			$this->opt['COOKIEFILE'] = $this->cookiejar;
		}
		else {
			$this->opt['COOKIEJAR'] = $this->cookiejar;
		}
	}

	$this->opt['BINARYTRANSFER'] = true;
	return $this->call_curl($url);
}


/**
 * Download file from url.
 * Use setCache('download', 3600 * 4) to keep download valid for 4 h.
 */
public function download(string $url, string $save_as) : void {
	if ($this->cacheOk('download', $save_as)) {
		$this->opt = [];
		return;
	}

	if (!empty($this->cookiejar)) {
		if (File::exists($this->cookiejar)) {
			$this->opt['COOKIEFILE'] = $this->cookiejar;
		}
		else {
			$this->opt['COOKIEJAR'] = $this->cookiejar;
		}
	}

	$this->opt['BINARYTRANSFER'] = true;

	Dir::create(dirname($save_as));
	File::save($save_as, $this->call_curl($url));
}


/**
 * Call $url and save cookies to cookiejar (use auth cache).
 */
public function createCookiejar(string $url) : void {
	if (empty($this->cookiejar)) {
		throw new Exception('empty cookiejar');
	}

	if ($this->cacheOk('auth', $this->cookiejar)) {
		$this->opt = [];
		return;
	}

	$this->opt['COOKIEJAR'] = $this->cookiejar;
	$this->call_curl($url);
	File::exists($this->cookiejar, true);
}


/**
 * Return true if lastModified($file) > NOW() - this.cache[$name]
 */
private function cacheOk(string $name, string $file) : bool {
	if (empty($this->cache[$name]) || !File::exists($file)) {
		return false;
	}
	
	$res = File::lastModified($file) > time() - $this->cache[$name];
	// \rkphplib\lib\log_debug("Curl.cacheOk:264> $file: ".intval($res));
	return $res;
}


/**
 * Return curl result
 */
private function call_curl(string $url) : string {
	$ch = curl_init();

	$this->opt['FOLLOWLOCATION'] = true;
	$this->opt['SSL_VERIFYHOST'] = false;
	$this->opt['SSL_VERIFYPEER'] = false;
	$this->opt['RETURNTRANSFER'] = true;

	if (count($this->header) > 0) {
		if (!isset($this->opt['HTTPHEADER'])) {
			$this->opt['HTTPHEADER'] = [];
		}

		foreach ($this->header as $key => $value) {
			array_push($this->opt['HTTPHEADER'], $key.': '.$value);
		}
	}

	if (empty($this->opt['URL'])) {
		$this->opt['URL'] = (empty($this->url) || substr($url, 0, 4) == 'http') ? $url : $this->url.$url;
	}

	foreach ($this->opt as $key => $value) {
		curl_setopt($ch, constant('CURLOPT_'.$key), $value);
	}

	// \rkphplib\lib\log_debug('Curl.call_curl:298> '.print_r($this->opt, true));
	$res = curl_exec($ch);

	$info = curl_getinfo($ch);
	$status = intval($info['http_code']);
	if ($status < 200 || $status >= 300) {
		throw new Exception('curl failed', $this->opt['URL']);
	}

	curl_close($ch);
	$this->opt = [];

	return $res;
}


/**
 * Use [http...|path]?key=value&... as post data.
 * Ignore if $url does not contain ?
 */
private function setPostString(string $url) : void {
	if (false === strpos($url, '?')) {
		return;
	}

	list ($path, $query) = explode('?', $url, 2);

	if (substr($path, 0, 4) == 'http') {
		$this->opt['URL'] = $path;
	}
	else if (empty($this->opt['URL'])) {
		if (empty($this->url)) {
			throw new Exception('url is empty');
		}

		$this->opt['URL'] = $this->url.$path;
	}


	$this->opt['POSTFIELDS'] = $query;
}


}

