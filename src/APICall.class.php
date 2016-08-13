<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/XML.class.php');

use rkphplib\Exception;


/**
 * API call wrapper. Use constructor or set() for configuration. 
 * After exec($data) result is saved in $this->result|info|status.
 * If $this->status != 200 call has failed. 
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

/** @var string $content [application/json|application/xml|image/jpeg|...] */
protected $content = '';

/** @var string $accept [application/json|application/jsonp|application/xml|...] (default = application/json) */
protected $accept = 'application/json';

/** @var string $token e.g. iSFxH73p91Klm */
protected $token = '';

/** @var map|string $result */
public $result = null;

/** @var map $info */
public $info = null;

/** @var int $status */
public $status = null;



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
 *  - content: GET/POST or php://input (application/json or application/xml)
 *  - accept: Result format (application/json = default, application/jsonp, or application/xml)
 *
 * @param string $name
 * @param string $value
 */
public function set($name, $value) {
	$allow = [ 'method' => [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH' ], 'uri' => null, 'url' => null, 'token' => null, 
		'auth' => [ 'request', 'header', 'basic_auth' ], 'content' => null, 'accept' => null ];

	if (!array_key_exists($name, $allow)) {
		throw new Exception('invalid name', "$name=$value");
	}

	if (is_array($allow[$name]) && !in_array($value, $allow[$name])) {
		throw new Exception('invalid value', "$name=$value");
	}

	$this->$name = $value;
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
	$this->set('method', $method);
	$this->set('uri', $uri);
	return $this->exec($data);
} 


/**
 * Execute API call.
 * 
 * If set('auth', 'basic_auth') use set('token, 'login:password').
 * Use set('accept', 'application/xml') and set('content', 'application/xml') to send and receive xml.
 *
 * @param map|string $data use xml if content=application/xml (default = null)
 * @return bool (status=200 true, otherwise false)
 */
public function exec($data = null) {

	$required = [ 'method', 'uri', 'url' ];
	foreach ($required as $key) {
		if (empty($this->$key)) {
			throw new Exception('missing parameter', $key);
		}
	}

	$ch = curl_init();
	$header = array();

	if (!empty($this->token)) {
		if (empty($this->auth)) {
			throw new Exception('missing parameter', 'auth');
		}
		else if ($this->auth == 'request' && is_array($data)) {
			$data['api_token'] = $this->token;
		}
		else if ($this->auth == 'header') {
			array_push($header, 'X-AUTH-TOKEN: '.$this->token);
		}
		else if ($this->auth == 'basic_auth') {
			curl_setopt($ch, CURLOPT_USERPWD, $conf['api_token']);
		}
	}

	if (!empty($this->accept)) {
		array_push($header, 'ACCEPT: '.$this->accept);
	}

	if (!empty($this->content)) {
		array_push($header, 'CONTENT-TYPE: '.$this->content);
		if (is_string($data)) {
			// raw data request
			array_push($header, 'X-HTTP-Method-Override: '.$this->method);

			if ($this->content == 'application/xml' && is_array($data)) {
				$data = XML::fromJSON($data);
			}

			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
	}

	if (count($header) > 0) {
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	}
	
	$uri_append = '';

	if ($this->method == 'GET') {
		if (is_array($data) && count($data) > 0) {
			$uri_append = '?'.http_build_query($data);
		}
	}
	else {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);

		if (($this->method == 'PUT' || $this->method == 'DELETE') && is_array($data) && count($data) > 0) {
			$data = http_build_query($data);
		}

		if (!is_null($data)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
	}

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_URL, $this->url.'/'.$this->uri.$uri_append);

	$this->result = curl_exec($ch);
	$this->info = curl_getinfo($ch);
	$this->status = intval($this->info['http_code']);

	curl_close($ch);

	if ($this->accept == 'application/json') {
		$this->result = json_decode($this->result, true);
	}

	return $this->status === 200;
}


}

