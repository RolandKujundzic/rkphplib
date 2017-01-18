<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/JSON.class.php');
require_once(__DIR__.'/XML.class.php');

use rkphplib\Exception;


/**
 * API call wrapper. Use constructor or set() for configuration. 
 * After exec($data) result is saved in $this->result|info|status.
 * If $this->status != 200 call has failed. Default header are
 * { Content-Type: application/json, Accept: application/json, Accept-Charset: utf-8 }
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class APICall {

/** @var string $method [GET|POST|PUT|DELETE|PATCH] (default = GET) */
protected $method = 'GET';

/** @var string $uri e.g. some/action */
protected $uri = '';

/** @var string $url e.g. https://domain.tld/api/v1.0 */
protected $url = '';

/** @var string $auth [request|header|basic_auth] (default = request) */
protected $auth = 'request';

/** @var string $token e.g. iSFxH73p91Klm */
protected $token = '';

/** @var map $header (default: { Content-Type: application/json, Accept: application/json, Accept-Charset: utf-8 }) */
protected $header = [ 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'Accept-Charset' => 'utf-8' ];

/** @var map|string $result */
public $result = null;

/** @var map $info */
public $info = null;

/** @var int $status */
public $status = null;

/** @var string $dump */
public $dump = '';



/**
 * See set() for allowed option parameter.
 *
 * @param map $opt (default = [])
 */
public function __construct($opt = []) {
	foreach ($opt as $key => $value) {
		$this->set($key, $value);
	}
}


/**
 * Configure api call. Parameter:
 *
 *  - method: GET|POST|PUT|DELETE|PATCH
 *  - uri: request path e.g. some/action
 *  - url: required e.g. https://domain.tld/api/v1.0
 *  - token: required e.g. iSFxH73p91Klm
 *  - auth:  request|header|basic_auth
 *  - content: same as header[Content-Type] e.g. application/json=default|application/xml|image/jpeg|...
 *  - accept: same as header[Accept] = result format e.g application/json=default|application/xml|...
 *  - header: e.g. [ 'Content-Type' => 'application/json', ... ]
 *
 * @param string $name
 * @param string $value
 */
public function set($name, $value) {

	if ($name == 'content') {
		$this->header['Content-Type'] = $value;
	}
	else if ($name == 'accept') {
		$this->header['Accept'] = $value;
	}
	else if ($name == 'header') {
		foreach ($value as $hkey => $hval) {
			$this->header[$hkey] = $hval;
		}
	}
	else {
		$allow = [ 'method' => [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH' ], 'uri' => null, 'url' => null, 'token' => null, 
			'auth' => [ 'request', 'header', 'basic_auth' ] ];

		if (!array_key_exists($name, $allow)) {
			throw new Exception('invalid name', "$name=$value");
		}

		if (is_array($allow[$name]) && !in_array($value, $allow[$name])) {
			throw new Exception('invalid value', "$name=$value");
		}

		$this->$name = $value;
	}
}


/**
 * Short for set('method', $method); set('uri', $uri); exec($data).
 *
 * @param string $method see set('method', ...)
 * @param string $uri see set('uri', ...)
 * @param map|string $data
 * @return bool (status=200 true, otherwise false)
 */
public function call($method, $uri, $data = null) {
	$this->set('method', strtoupper($method));
	$this->set('uri', $uri);
	return $this->exec($data);
} 


/**
 * Execute API call.
 * 
 * If set('auth', 'basic_auth') use set('token, 'login:password').
 * Use set('accept', 'application/xml') and set('content', 'application/xml') to send and receive xml.
 * If data is map it will be auto-converted to content. If data is string use xml if content=application/xml or 
 * json_encode(...) for default content (application/json). If accept is application/json auto-convert result to map.
 *
 * @param map|string $data 
 * @return bool (status=200 true, otherwise false)
 */
public function exec($data = null) {

	$required = [ 'method', 'uri', 'url' ];
	foreach ($required as $key) {
		if (empty($this->$key)) {
			throw new Exception('missing parameter', $key);
		}
	}

	$header = $this->header;
	$options = [];

	if (!empty($this->token)) {
		if (empty($this->auth)) {
			throw new Exception('missing parameter', 'auth');
		}
		else if ($this->auth == 'request' && is_array($data)) {
			$data['api_token'] = $this->token;
		}
		else if ($this->auth == 'header') {
			$header['X-AUTH-TOKEN'] = $this->token;
		}
		else if ($this->auth == 'basic_auth') {
			$options['USERPWD'] = $conf['api_token'];
		}
	}

	$uri_append = '';

	if ($this->method != 'GET' && $this->method != 'POST') {
		$header['X-HTTP-Method-Override'] = $this->method;
	}

	if ($this->method == 'GET') {
		$options['HTTPGET'] = true;
	}
	else if ($this->method == 'POST') {
		$options['POST'] = true;
	}
	else {
		$options['CUSTOMREQUEST'] = $this->method;
	}

	if ($data !== null) {
		if ($this->method == 'GET') {
			if (is_array($data) && count($data) > 0) {
				$uri_append = '?'.http_build_query($data);
			}
		}
		else {
			if (is_array($data)) {
				if ($this->header['Content-Type'] == 'application/xml') {
					$data = XML::fromMap($data);
				}
				else if ($this->header['Content-Type'] == 'application/json') {
					$data = JSON::encode($data); 
				}
				else if ($this->header['Content-Type'] == 'application/x-www-form-urlencoded') {
					$data = http_build_query($data);
				}
				else {
					throw new Exception('invalid content type for array data', print_r($this->header, true));
				}
			}

			$options['POSTFIELDS'] = $data;
			$header['Content-Length'] = strlen($data);
		}
	}

	if (count($header) > 0) {
		$header_lines = [];

		foreach ($header as $key => $value) {
			array_push($header_lines, $key.': '.$value);
		}

		$options['HTTPHEADER'] = $header_lines;
	}

	$options['SSL_VERIFYPEER'] = false;
	$options['SSL_VERIFYHOST'] = false;
	$options['RETURNTRANSFER'] = true;

	$url = (mb_substr($this->uri, 0, 1) == '/' || mb_substr($this->url, -1) == '/') ? $this->url.$this->uri : $this->url.'/'.$this->uri;
	$options['URL'] = $url.$uri_append;

	$ch = curl_init();

	foreach ($options as $key => $value) {
		curl_setopt($ch, constant('CURLOPT_'.$key), $value);
	}

	$this->result = curl_exec($ch);
	$this->info = curl_getinfo($ch);
	$this->status = intval($this->info['http_code']);
	$success = $this->status >= 200 && $this->status < 300;
	curl_close($ch);
	
	$this->dump = print_r($options, true);
	
	if ($this->header['Accept'] == 'application/json') {
		$this->result = JSON::decode($this->result);
	}

	return $success;
}


}

