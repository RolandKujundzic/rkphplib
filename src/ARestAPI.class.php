<?php

// ToDo: Accept-Language: de_DE + OpenID Connect | OAUTH2
// http://de.slideshare.net/jcleblanc/securing-restful-apis-using-oauth-2-and-openid-connect
// https://github.com/jcleblanc/oauth
// https://github.com/thephpleague/oauth2-server
// https://github.com/bshaffer/oauth2-server-php + https://github.com/bshaffer/oauth2-demo-php


namespace rkphplib;

require_once(__DIR__.'/XML.class.php');
require_once(__DIR__.'/JSON.class.php');
require_once(__DIR__.'/lib/error_msg.php');



/**
 * Custom exception with public properties http_error and internal_message.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class RestServerException extends \Exception {

/** @var int $http_error */
public $http_code = 400;

/** @var string error $internal_mesage error detail you don't want to expose */
public $internal_message = '';


/**
 * Class constructor.
 *
 * @param string $message error message
 * @param int $error_no
 * @param int $http_error = 400
 * @param string $internal_message error message detail (default = '')
 */
public function __construct($message, $error_no, $http_error = 400, $internal_message = '') {
  parent::__construct($message, $error_no);
	$this->http_error = $http_error;
  $this->internal_message = $internal_message;
}


}



/**
 * Abstract rest api class. Example:
 *
 * class MyRestServer extends \rkphplib\ARestServer {
 *   ...
 * }
 *
 * $rs = new MyRestServer();
 * set_exception_handler(call_user_func($rs, 'exceptionHandler'));
 * set_error_handler(call_user_func($rs, 'errorHandler'));
 *
 * Avaliable HTTP methods are GET (retrieve), HEAD, POST (create new), PUT (update), PATCH (modify use with JSON|XML Patch - see
 * http://jsonpatch.com/), DELETE (return status 200 or 404), OPTIONS (return SWAGGER path).  
 * 
 * Status Code Success:
 * - 200 (OK): e.g. GET /visit/:id (return list of customer visits, allow pagination, sorting and filtering)
 * - 201 (Created): e.g. POST /customer (create customer and set 'Location' header with link to /customers/{id})
 * - 202 (Accepted): operation has been initialized - result must be requested separely
 * - 204 (No Content): 
 * - 205 (Reset Content):
 * - 206 (Partial Content):
 * - 208 (Already Reported):
 *
 * Status Code Error (Client is responsible):
 * - 400 (Bad Request): request failed
 * - 401 (Unauthorized): 
 * - 403 (Forbidden): invalid url (no such api call)
 * - 404 (Not Found): resource not found e.g. GET /customer/:id but id is not found
 * - 405 (Method Not Allowed): invalid method (will send "Allow" Header with valid methods, e.g. Allow: GET, HEAD, PUT, DELETE)
 * - 409 (Conflict): e.g. POST /customer but unique parameter email already exists
 * - 406 (Not Acceptable): invalid data submited (will send "Content-Type" Header as hint)
 * - 408 (Request Timeout): 
 * - 409 (Conflict):
 * - 410 (Gone): This API call is deprecated and has been removed
 * - 411 (Length Required): missing „Content-Length“ in Request
 * - 412 (Precondition Failed): use „If-Match“-Header
 * - 413 (Request Entity Too Large): 
 * - 415 (Unsupported Media Type):
 * - 416 (Requested range not satisfiable):
 * - 417 (Expectation Failed): use „Expect“-Header
 * - 422 (Unprocessable Entity): validation failed
 * - 423 (Locked): right now not available
 * - 424 (Failed Dependency):
 * - 426 (Upgrade Required): you must use SSL
 * - 428 (Precondition Required): e.g. we need ETag-Header for file removal
 * - 429 (Too Many Requests):
 * - 431 (Request Header Fields Too Large): 
 *
 * Status Code Error (Server is responsible):
 * - 500 (Internal Server Error): 
 * - 501 (Not Implemented):
 * - 503 (Service Unavailable): send „Retry-After“-Header
 * - 504 (Gateway Time-out):
 * 
 * Status Code (custom): 900 - 999 available
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ARestAPI {

const ERR_UNKNOWN = 1;
const ERR_INVALID_API_CALL = 2;
const ERR_INVALID_ROUTE = 3;
const ERR_JSON_TO_XML = 4;
const ERR_PHP = 5;
const ERR_INVALID_INPUT = 6;


/** @var map $options */
protected $options = [];

/** @var map $request */
protected $request = [];



/**
 * Use php buildin webserver as API server. Return routing script source.
 * Start webserver on port 10080 and route https from 10443:
 * 
 * php -S localhost:10080 www/api/routing.php
 *
 * Enable https://localhost:10443/ with stunnel (e.g. stunnel3 -d 10443 -r 10080)
 *
 * @return string
 */
public static function phpAPIServer() {

	$index_php = $_SERVER['argv'][0];

	$php = '<'.'?php'."\n\n// Start API server: php -S localhost:10080 ".dirname($index_php)."/routing.php\n".
		'if (preg_match(\'/\.(?:png|jpg|jpeg|gif)$/\', $_SERVER["REQUEST_URI"])) {'."\n\treturn false;\n}\nelse {\n\t".
		'include __DIR__.\'/index.php\';'."\n}\n";

	return $php;
}


/**
 * Return apache .htaccess configuration. 
 *
 * Allow GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS from anywhere.
 * Redirect everything to index.php. Unsupported: CONNECT, TRACE. 
 *
 * @param string $api_script = /api/index.php
 * @return string
 */
public static function apacheHtaccess($api_script = '/api/index.php') {
	$res = <<<END
Header add Access-Control-Allow-Origin "*"
Header add Access-Control-Allow-Headers "origin, x-requested-with, content-type"
Header add Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS"

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ {$api_script} [QSA,L]
END;
	return $res;
}


/**
 * Return nginx location configuration.
 *
 * @param string $api_script = /api/index.php
 * @return string
 */
public static function nginxLocation($api_script = '/api/index.php') {
	$dir = dirname($api_script);
	$res = <<<END
location {$dir} {
add_header Access-Control-Allow-Origin "*";
add_header Access-Control-Allow-Headers "origin, x-requested-with, content-type";
add_header Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS";
try_files $uri $uri/ {$api_script}$is_args$args;
}
END;
	return $res;
}


/**
 * Return _SERVER.REQUEST_METHOD as lowerstring.
 *
 * Overwrite with header "X-HTTP-METHOD[-OVERRIDE]".
 * 
 * @return string
 */
public static function getRequestMethod() {
	$method = empty($_SERVER['REQUEST_METHOD']) ? 'GET' : $_SERVER['REQUEST_METHOD'];

	if (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
		$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
	}
	else if (!empty($_SERVER['HTTP_X_HTTP_METHOD'])) {
		$method = $_SERVER['HTTP_X_HTTP_METHOD'];
	}

	return strtolower($method);
}


/**
 * Parse Content-Type header, check options.allow and return normalized Content-Type and Input-Type.
 *
 * Input-Type: data, xml, json, urlencoded
 * Normalized Content-Type: application/xml|json|octet-stream|x-www-form-urlencoded, image|text|video|audio/*
 *
 * @throws
 * @return vectory<string,string> [Normalized Content-Type, Input-Type] 
 */
public static function getContentType() {

	if (empty($_SERVER['CONTENT_TYPE'])) {
		throw new RestServerException(lib\error_msg('empty $p1x header', [ 'Content-Type' ]), self::ERR_INVALID_INPUT, 400);
	}

	$type = strtolower($_SERVER['CONTENT_TYPE']);
	$input = '';

	// mb_strpos is necessary because "Content-type: application/xml; UTF-8" is valid header
	if (mb_strpos($type, 'image/') !== false) {
		$type = 'image/*';
		$input = 'data';
	}
	else if (mb_strpos($type, 'text/') !== false) {
		$type = 'text/*';
		$input = 'data';
	}
	else if (mb_strpos($type, 'video/') !== false) {
		$type = 'video/*';
		$input = 'data';
	}
	else if (mb_strpos($type, 'audio/') !== false) {
		$type = 'audio/*';
		$input = 'data';
	}
	else if (mb_strpos($type, 'application/octet-stream') !== false) {
		$type = 'application/octet-stream';
		$input = 'data';
	}
	else if (mb_strpos($type, 'application/xml') !== false) {
		$type = 'application/xml';
		$input = 'xml';
	}
	else if (mb_strpos($type, 'application/json') !== false) {
		$type = 'application/json';
		$input = 'json';
	}
	else if (mb_strpos($type, 'application/x-www-form-urlencoded') !== false) {
		$type = 'application/x-www-form-urlencoded';
		$input = 'urlencoded';
	}
	else {
		throw new RestServerException(lib\error_msg('unknown content-type [$p1x]', [ $type ]), self::ERR_INVALID_INPUT, 400);
	}

	return [ $type, $input ];
}

	
/**
 * Catch all php errors. Activate with:
 *
 * set_error_handler(call_user_func($this, 'errorHandler'));
 *
 * @exit 500:ERR_PHP 
 * @param int $errNo
 * @param string $errStr
 * @param string $errFile
 * @param int $errLine
 */
public function errorHandler($errNo, $errStr, $errFile, $errLine) {

  if (error_reporting() == 0) {
    // @ suppression used, ignore it
    return;
  }

	$msg = lib\error_msg('PHP ERROR [$p1x] $p2x (on line $p3x in file $p4x)', [ $errNo, $errStr, $errLine, $errFile ]);
	$this->out([ 'error' => $msg, 'error_code' => self::ERR_PHP ], 500);
}


/**
 * Catch all Exceptions.
 * 
 * @param Exception $e
 */
public function exceptionHandler($e) {
  $msg = $e->getMessage();
	$error_code = $e->getCode();
	$http_code = property_exists($e, 'http_code') ? $e->http_code : 400; 
  $internal = property_exists($e, 'internal_message') ? $e->internal_message : '';

	$this->out([ 'error' => $e->getMessage(), 'error_code' => $e->getCode(), 'error_info' => $internal ], $http_code);
}


/**
 * Set Options:
 *
 * - Accept: allowed Content-Type (default = [application/json, application/xml, application/octet-stream, image|text|audio|video/*])
 * - allow_method = [ PUT, GET, POST, DELETE, PATCH, HEAD, OPTIONS ]
 * - xml_root: XML Root node of api result (default = '<api></api>')
 *
 * @param map $options 
 */
public function __construct($options) {
	$this->options = [];

	$this->options['Accept'] = [ 'application/json', 'application/xml', 'application/octet-stream', 
		'image/*', 'text/*', 'video/*', 'audio/*' ];

	$this->options['allow_method'] = [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS' ];

	$this->options['xml_root'] = '<api></api>';

	foreach ($options as $key => $value) {
		$this->options[$key] = $value;
	}
}


/** 
 * Check authentication token, set request[auth] and request[token] accordingly:
 *
 * 	- _GET|_POST[api_token] (auth=request)
 *  - _SERVER[HTTP_X_AUTH_TOKEN] (auth=header)
 *  - basic authentication, token = $_SERVER[PHP_AUTH_USER]:$_SERVER[PHP_AUTH_PW] (auth=basic_auth)
 *  - OAuth2 Token = Authorization header (auth=oauth2)
 *
 * 
 * @exit If no credentials are passed exit with "401 - basic auth required"
 * @throws
 */
public function getApiToken() {

	if (!empty($_REQUEST['api_token'])) {
		$this->request['token'] = $_REQUEST['api_token'];
		$this->request['auth'] = 'request';
	}
	else if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
		$this->request['token'] = trim($_SERVER['HTTP_X_AUTH_TOKEN']);
		$this->request['auth'] = 'header';
	}
	else if (!empty($_SERVER['AUTHORIZATION'])) {
		$this->request['token'] = trim($_SERVER['HTTP_X_AUTH_TOKEN']);
		$this->request['auth'] = 'oauth2';
	}
	else if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
		$this->request['token'] = $_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'];
		$this->request['auth'] = 'basic_auth';
	}
	else if (!isset($_SERVER['PHP_AUTH_USER'])) {
		header('WWW-Authenticate: Basic realm="REST API"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Please enter REST API basic authentication credentials';
		exit;
	}
}


/**
 * Fill this.request, set keys: ip, method, input (json, xml, image, post or get), map, data (if type=image)
 * Parse _GET, _POST and php://input depending on (Content-Type header and Accept option).
 *
 * Content-type: application/xml = XML decode Input
 * Content-type: application/json = JSON decode Input
 * Content-type: application/x-www-form-urlencoded = Input is Query-String
 *
 * Request_Method: GET = use $_GET
 * Request_Method: POST = use $_POST
 * Request_Method: PUT, DELETE, PATCH, HEAD, OPTIONS = use _REQUEST 
 *
 * @throws
 */
public function parse() {

	$this->request['ip'] = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
	$this->request['method'] = static::getRequestMethod();
	$this->request['data'] = null;
	$this->request['map'] = [];

	if (!in_array($this->request['method'], $this->options['allow_method'])) {
		throw new RestServerException(lib\error_msg('invalid method [$p1x]', [ $this->request['method'] ]), 
			self::ERR_INVALID_INPUT, 400);
	}

	list ($this->request['content-type'], $this->request['input-type']) = self::getContentType(); 

	if (!in_array($this->request['content-type'], $this->options['Accept'])) {
		throw new RestServerException(lib\error_msg('invalid content-type [$p1x]', [ $this->request['content-type'] ]), 
			self::ERR_INVALID_INPUT, 400);
	}

	if (($input = file_get_contents('php://input'))) {
		if ($this->request['input-type'] == 'data') {
			$this->request['data'] = $input;
		}
		else if ($this->request['input-type'] == 'xml') {
			$this->request['map'] = XML::toMap($input);
		}
		else if ($this->request['input-type'] == 'json') {
			$this->request['map'] = JSON::decode($input);
		}
		else if ($this->request['input-type'] == 'urlencoded') {
    	parse_str($input, $this->request['map']);
		}
	}

	if ($this->request['method'] == 'POST' && count($_POST) > 0) {
		$this->request['map'] = array_merge($this->request['map'], $_POST, $_GET);
	}
	else if ($this->request['method'] == 'GET' && count($_GET) > 0) {
		$this->request['map'] = array_merge($this->request['map'], $_GET);
	}
	else if (count($_REQUEST) > 0) {
		$this->request['map'] = array_merge($this->request['map'], $_REQUEST);
	}
}


/**
 * Run api call.
 *
 * @param map $req required keys: api_call
 * @param map $priv
 */
public function call($req, $priv = array()) {
	$this->_req = $req;
	$this->_priv = $priv;

	if (empty($this->_req['api_call']) || !method_exists($this, $this->_req['api_call'])) {
		$this->error(lib\error_msg('invalid api call p1x', array($this->_req['api_call'])), self::ERR_INVALID_API_CALL);
	}

	$method = $this->_req['api_call'];
	$this->$method();
}


/**
 * Process api request.
 * 
 * Call $this->parse() and $this->route(). 
 */
abstract public function run();


/**
 * Return all possible api calls.
 * 
 * Return: [ api_method => [http_method, url, has_id], ... ]
 *
 * http_method = GET|POST|PUT|DELETE|PATCH|HEAD|OPTIONS
 * url = e.g. a/b/c
 * has_id = 0|1|2, 0 = no id, 1 = only url/:id, 2 = url/:id and url + _req[id]
 * 
 * @return map e.g. ['postUser' => ['POST', 'user', 0], 'getUser' => ['GET', 'user', 2], ... ] 
 */
abstract public static function apiMap();


/**
 * Return allowed keys from complete_map. Example:
 * 
 * static::allow(static::apiMap(), $allow)
 *
 * @param map $complete_map
 * @param vector $allow
 * @return $amp
 */
public static function allow($complete_map, $allow) {
	$res = [];

	foreach ($allow as $key) {
		if (isset($complete_map[$key])) {
			$res[$key] = $complete_map[$key];
		}
	}

	return $res;
}


/**
 * If priv.custom is set and private method _$_priv.custom_$method() exists execute it.
 * You can not use reference parameter in plist. Example:
 *
 * $this->_call_custom('postUserOrder', array($p, $user));
 *
 * @param string $method
 * @param vector $plist
 */
protected function _call_custom($method, $plist = array()) {

	if (empty($this->_priv['custom'])) {
		return;
	}

	$cc = '_'.$this->_priv['custom'].'_'.$method;
	if (method_exists($this, $cc)) {
		call_user_func_array(array($this, $cc), $plist);
	}
}


/**
 * Check $_req[api_token] and return api user configuration.
 *
 * API Configuration is list with allowed method calls and further configuration (e.g. allowed cols, ...)
 *
 * Return keys: 
 *  - allow = list of allowed api calls
 * 
 * @exit this::out(lib\error_msg(...)) if error
 * @return map 
 */
abstract public function checkToken();


/**
 * Determine api call from url and request method.
 *
 * Set $this->_req[api_call] (e.g. getXaYbZc if URL= xa/yb/zc and getXyYbZc() exists) and 
 * $this->_req[api_id] ([] or [ $id1, ... ] if /url/:id1/:id2/... is found).
 * Return api method call and other route information.
 * 
 * @exit this::out(lib\error_msg('invalid route')) if no route is found
 * @param map $api_map allowed api calls e.g. static::allow(static::apiMap(), $allow)
 * @return map keys: method, url, base.
 */
public function route($api_map) {

	$r = array();
	$r['method'] = static::getRequestMethod();
	$r['url'] = $_SERVER['REQUEST_URI'];

	if (($pos = mb_strpos($r['url'], '?')) > 0) {
		$r['url'] = substr($r['url'], 0, $pos);
	}

	if (substr($r['url'], 0, 1) == '/') {
		$r['url'] = substr($r['url'], 1);
	}

	$api_map = static::apiMap();
	$func_list = [];
	$path = explode('/', ucwords($r['url'], '/'));


	$func_id = '';
	$func = '';

	foreach ($api_map as $fname => $fconf) {

		if ($fconf[0] != $r['method']) {
			continue;
		}

		if ($fconf[1] == $r['url']) {
			$func = $fname;
			break;
		}
		else if ($parent_url && ($fconf[2] == 1 || $fconf[2] == 2) && $fconf[1] == $parent_url) {
			$func_id = $fname;
		}
	}

	if ($func) {
		$this->_req['api_call'] = $func;
	}
	else if ($func_id) {
		$this->_req['api_id'] = basename($r['url']);
		$this->_req['api_call'] = $func_id;
		$r['url'] = $parent_url;
	}

	if (empty($this->_req['api_call']) || !method_exists($this, $this->_req['api_call'])) {
		$this->error(lib\error_msg('invalid route p1x:/p2x', array($r['method'], $r['url'])), self::ERR_INVALID_ROUTE);
	}

	error_log(print_r($r, true), 3, '/tmp/php.log');

	return $r;
}


/**
 * Return api error message. Example:
 * 
 * $this->error('error message') = $this->out(['error' => 'error message', 'error_code' => 1], 400)
 * $this->error(lib\error_msg('error msg p1x', array('unknown')), 4) = $this->out(['error' => 'error msg unknown', 'error_code' => 4], 400)
 *
 * @param string $msg
 * @param int $error_code (default = -1 = unknown error)
 */
public function error($msg, $error_code = -1) {
	$this->out([ 'error' => $msg, 'error_code' => $error_code ], 400);
}


/**
 * Return api call result. 
 *
 * Default result format is JSON (change with HTTP_ACCEPT: application/xml to XML).
 * Overwrite for custom modification. 
 *
 * @param map $o
 * @param int $code (default = 200, use 400 if error)
 * @exit print JSON|JSONP|XML
 */
public function out($o, $code = 200) {

	$is_jsonp = empty($this->_req['jsonpCallback']) ? false : true;

	if (!empty($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/xml') {
		try {
			$output = XML::fromMap($o);
		}
		catch (\Exception $e) {
			$this->error(lib\error_msg('XML::fromMap error: p1x', array($e->getMessage())), self::ERR_JSON_TO_XML);
		}

		header('Content-Type: application/xml');
	}
	else if ($is_jsonp) {
		header('Content-Type: application/javascript');
		$output = $this->_req['jsonpCallback'].'('.json_encode($o, 448).')';
	}
	else {
		header('Content-Type: application/json');
		$output = json_encode($o, 448);
	}

	if ($code == 200) {
		header('Status: 200 OK');
	}
	else if ($code == 400) {
		header('Status: 400 Bad Request');
	}

	header('Content-Length: '.mb_strlen($output));
	print $output;
	exit(0);
}


}

