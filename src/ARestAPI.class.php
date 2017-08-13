<?php

// ToDo: Accept-Language: de_DE + OpenID Connect | OAUTH2
// http://de.slideshare.net/jcleblanc/securing-restful-apis-using-oauth-2-and-openid-connect
// https://github.com/jcleblanc/oauth
// https://github.com/thephpleague/oauth2-server
// https://github.com/bshaffer/oauth2-server-php + https://github.com/bshaffer/oauth2-demo-php


namespace rkphplib;

require_once(__DIR__.'/XML.class.php');
require_once(__DIR__.'/JSON.class.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/Dir.class.php');
require_once(__DIR__.'/Database.class.php');
require_once(__DIR__.'/lib/log_error.php');
require_once(__DIR__.'/lib/translate.php');



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
 *
 * Avaliable HTTP methods are GET (retrieve), HEAD, POST (create new), PUT (update), PATCH (modify use with JSON|XML Patch - see
 * http://jsonpatch.com/), DELETE (return status 200 or 404), OPTIONS (return SWAGGER path).  
 * 
 * Subclass and implement checkRequest() and api callback methods ($method$Path e.g. getSomeAction, postUser, ... ).
 * Api callback methods throw exceptions on error and return result. 
 *  
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ARestAPI {

const ERR_UNKNOWN = 1;
const ERR_INVALID_API_CALL = 2;
const ERR_INVALID_ROUTE = 3;
const ERR_PHP = 4;
const ERR_CODE = 5;
const ERR_INVALID_INPUT = 6;
const ERR_NOT_IMPLEMENTED = 7;
const ERR_CONFIGURATION = 8;

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
 * Parse Content-Type header and set request.content-type and request.input-type.
 *
 * request.input-type: data, xml, json, urlencoded
 * request.content-type: application/xml|json|octet-stream|x-www-form-urlencoded, image|text|video|audio/*
 *
 * @throws
 */
private function parseContentType() {

	if (empty($_SERVER['CONTENT_TYPE']) && empty($_SERVER['HTTP_CONTENT_LENGTH'])) {
		$this->request['content-type'] = '';
		$this->request['input-type'] = '';
	}

	$type = empty($_SERVER['CONTENT_TYPE']) ? 'application/json' : strtolower($_SERVER['CONTENT_TYPE']);
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
	else if (mb_strpos($type, 'multipart/form-data') !== false) {
		$type = 'multipart/form-data';
		$input = 'multipart';
	}
	else {
		throw new RestServerException('unknown content-type', self::ERR_INVALID_INPUT, 400, "type=$type");
	}

	$this->request['content-type'] = $type;
	$this->request['input-type'] = $input;
}

	
/**
 * Catch all php errors. Activated in constructor:
 *
 * set_error_handler([$this, 'errorHandler']);
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

	$this->out([ 'error' => "PHP ERROR [$errNo] $errStr", 'error_code' => self::ERR_PHP, 
		'error_info' => "file=$errFile line=$errLine" ], 500);
}


/**
 * Catch all Exceptions. Activated in constructor:
 *
 * set_exception_handler([$this, 'errorHandler']);
 * 
 * @param Exception $e
 */
public function exceptionHandler($e) {
  $msg = $e->getMessage();
	$error_code = $e->getCode();
	$http_code = (property_exists($e, 'http_code') && $e->http_code >= 400) ? $e->http_code : 400; 
  $internal = property_exists($e, 'internal_message') ? $e->internal_message : '';

	$this->out([ 'error' => $e->getMessage(), 'error_code' => $e->getCode(), 'error_info' => $internal ], $http_code);
}


/**
 * Set Options:
 *
 * - Accept: allowed Content-Type (default = [application/json, application/xml, application/octet-stream, 
 *    multipart/form-data, application/x-www-form-urlencoded, image|text|audio|video/*])
 * - allow_route = [], e.g. [ get/token ]
 * - allow_method = [ put, get, post, delete, patch, head, options ]
 * - force_basic_auth = true
 * - xml_root: XML Root node of api result (default = '<api></api>')
 * - allow_auth = [ header, request, basic_auth, oauth2 ]
 * - log_dir = '' (if set save requests to this directory) 
 * - auth_query = optional, default = SELECT id, config FROM api_user WHERE token='{:=token}' AND status=1
 * 		if auth_dir is empty use api_user with token='default' as default.
 * - auth_dir = use auth_dir/config.json as default configuration. If auth_query.config=auth_dir 
 *		use auth_dir/config.ID.json. If auth_query is empty use auth_dir/config.MD5(token).json.
 * - internal_error = false
 *
 * @param map $options = []
 */
public function __construct($options = []) {
	$this->options = [];

	$this->options['accept'] = [ 'application/x-www-form-urlencoded', 'multipart/form-data',
		'application/json', 'application/xml', 'application/octet-stream', 
		'image/*', 'text/*', 'video/*', 'audio/*' ];

	$this->options['allow_route'] = [];
	$this->options['allow_method'] = [ 'get', 'post', 'put', 'delete', 'patch', 'head', 'options' ];
	$this->options['allow_auth'] = [ 'header', 'request', 'basic_auth', 'oauth2' ];
	$this->options['xml_root'] = '<api></api>';
	$this->options['log_dir'] = '';
	$this->options['auth_query'] = "SELECT * FROM api_user WHERE token='{:=token}' AND status=1";
	$this->options['auth_dir'] = '';
	$this->options['force_basic_auth'] = true;
	$this->options['internal_error'] = false;

	foreach ($options as $key => $value) {
		$this->options[$key] = $value;
	}

	if (!empty($this->options['log_dir'])) {
		$log_dir = $this->options['log_dir'].'/'.date('Ym').'/'.date('dH');
		Dir::create($log_dir, 0, true);
		$unique_id = sprintf("%08x", abs(crc32($_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_TIME_FLOAT'] . $_SERVER['REMOTE_PORT'])));
		$this->options['log_prefix'] = $log_dir.'/api_'.$unique_id;
	}

	set_error_handler([$this, 'errorHandler']);
	set_exception_handler([$this, 'exceptionHandler']);
}


/** 
 * Check authentication token, set request[auth] and request[token] accordingly:
 *
 * 	- _GET|_POST[api_token] (auth=request)
 *  - _SERVER[HTTP_X_AUTH_TOKEN] (auth=header)
 *  - basic authentication, token = $_SERVER[PHP_AUTH_USER]:$_SERVER[PHP_AUTH_PW] (auth=basic_auth)
 *  - OAuth2 Token = Authorization header (auth=oauth2)
 * 
 * Default options.allow_auth is [ header, request, oauth2, basic_auth ].
 * 
 * @exit If no credentials are passed basic_auth is allowed and required exit with "401 - basic auth required"
 */
private function checkApiToken() {
	$res = [ '', '' ];

	$allow_basic_auth = in_array('basic_auth', $this->options['allow_auth']);

	if (!empty($_REQUEST['api_token']) && in_array('request', $this->options['allow_auth'])) {
		$res = [ $_REQUEST['api_token'], 'request' ];
	}
	else if (!empty($_SERVER['HTTP_X_AUTH_TOKEN']) && in_array('header', $this->options['allow_auth'])) {
		$res = [ $_SERVER['HTTP_X_AUTH_TOKEN'], 'header' ];
	}
	else if (!empty($_SERVER['AUTHORIZATION']) && in_array('oauth2', $this->options['allow_auth'])) {
		$res = [ $_SERVER['HTTP_AUTHORIZATION'], 'oauth2' ];
	}
	else if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW']) && $allow_basic_auth) {
		$res = [ $_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'], 'basic_auth' ];
	}
	else if ($this->options['force_basic_auth'] && $allow_basic_auth) {
		header('WWW-Authenticate: Basic realm="REST API"');
		header('HTTP/1.0 401 Unauthorized');
		print \rkphplib\lib\translate('Please enter REST API basic authentication credentials');
		$this->logRequest(401);
		exit;
	}

	list ($this->request['token'], $this->request['auth']) = $res;
}


/**
 * Parse _GET, _POST and php://input (= Input) depending on (Content-Type header and Accept option).
 *
 * Content-type: application/xml = XML decode Input
 * Content-type: application/json = JSON decode Input
 * Content-type: application/x-www-form-urlencoded = Input is Query-String
 *
 * Request_Method: GET = use $_GET
 * Request_Method: POST(, PUT, DELETE, PATCH, HEAD, OPTIONS) = use $_POST
 *
 * Set this.request keys:
 *
 * ip, data, map, content-type and input-type
 *
 * @throws
 */
public function parse() {

	$this->request['timestamp'] = date('Y-m-d H:i:s').':'.substr(microtime(), 2, 3);
	$this->request['port'] = empty($_SERVER['REMOTE_PORT']) ? '' : $_SERVER['REMOTE_PORT'];
	$this->request['ip'] = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
	$this->request['data'] = null;
	$this->request['map'] = [];

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
			mb_parse_str($input, $this->request['map']);
		}
		else if ($this->request['input-type'] == 'multipart') {
			throw new RestServerException('ToDo: parse multipart/form-data input', self::ERR_NOT_IMPLEMENTED, 501);
		}
		else {
			throw new RestServerException('unknown input type', self::ERR_NOT_IMPLEMENTED, 501, "content=".$this->request['content-type']." input=".$this->request['input-type']);
		}
	}

	if ($this->request['content-type'] == 'multipart/form-data' && count($_FILES) > 0) {
		$this->request['map'] = array_merge($_POST, $_FILES);
	}
	else if ($this->request['content-type'] == 'application/x-www-form-urlencoded') {
		if ($this->request['method'] == 'get') {
			$this->request['map'] = array_merge($this->request['map'], $_GET);
		}
		else {
			$this->request['map'] = array_merge($this->request['map'], $_POST);

			if (count($_GET) > 0) {
				// always use query parameter - but prefer map parameter
				$this->request['map'] = array_merge($_GET, $this->request['map']);
			}
		}
	}
	else {
		if (count($_GET) > 0) {
			// always use query parameter - but prefer map parameter
			$this->request['map'] = array_merge($_GET, $this->request['map']);
		}

		if ($this->request['method'] != 'get' && count($_POST) > 0) {
			// always use post data unless method is GET - but prefer map parameter
			$this->request['map'] = array_merge($_POST, $this->request['map']);
		}
	}
}


/**
 * Determine api call from url and request method.
 *
 * Set request.path, request.method, request.api_call 
 * (e.g. getXaYbZc if URL=xa/yb/zc and getXyYbZc() exists) 
 * and request.api_call_parameter 
 * (e.g. [ $id1, id2 ] if URL=/do/:id1/:id2 and getDo() exists).
 * 
 * @throws if route does not exists and $must_exist is true
 * @param bool $must_exist = true
 * @return bool return true if route exists
 */
public function route($must_exist = true) {

	$method = $this->request['method'];
	$url = $_SERVER['REQUEST_URI'];

	if (($pos = mb_strpos($url, '?')) > 0) {
		$url = substr($url, 0, $pos);
	}

	if ($_SERVER['SCRIPT_NAME'] != $_SERVER['REQUEST_URI']) {
		$base = dirname($_SERVER['SCRIPT_NAME']);
		if (mb_strpos($url, $base) === 0) {
			$url = mb_substr($url, mb_strlen($base)); 
		}
	}

	if (substr($url, 0, 1) == '/') {
		$url = substr($url, 1);
	}

	$this->request['path'] = $url;

	$path = explode('/', ucwords($url, '/'));
	$func_param = [];
	$func = '';

	while (count($path) > 0 && !$func) {
		$fname = $method.join('', $path);

		if (method_exists($this, $fname)) {
			$func = $fname;
		}
		else {
			array_unshift($func_param, array_pop($path));
		}
	}

	if (!empty($func)) {
		$this->request['api_call'] = $func;
		$this->request['api_call_parameter'] = (count($func_param) > 0) ? $func_param : [];
	}
	else {
		$this->request['api_call'] = '';
		$this->request['api_call_parameter'] = [];
	}

	return !empty($func);
}


/**
 * Return api call result. 
 *
 * Default result format is JSON (change with HTTP_ACCEPT: application/xml to XML).
 * Overwrite for custom modification. If request.status is set and $code == 200 use 
 * request.status instead of $code. 
 *
 * If error occured return error (localized error message), error_code and error_info and send http code >= 400.
 * 
 * @param map $o
 * @param int $code (default = 200, use 400 if error)
 * @exit print JSON|JSONP|XML
 */
public function out($o, $code = 200) {

	if ($code == 200 && !empty($this->request['status'])) {
		$code = $this->request['status'];
	}

	$jsonp = empty($this->request['map']['jsonpCallback']) ? '' : $this->request['map']['jsonpCallback'];

	http_response_code($code);

	if (!$this->options['internal_error'] && isset($o['error_info'])) {
		unset($o['error_info']);
	}

	if (!empty($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/xml') {
		header('Content-Type: application/xml');
		$output = XML::fromMap($o);
	}
	else if (!empty($jsonp)) {
		header('Content-Type: application/javascript');
		$output = $jsonp.'('.JSON::encode($o).')';
	}
	else {
		header('Content-Type: application/json');
		$output = JSON::encode($o);
	}

	$this->logResult($code, $o, $output);

	header('Content-Length: '.mb_strlen($output));
	print $output;
	exit(0);
}


/**
 * Save request and data from $_SERVER, $_GET, $_POST, php://input to options.log_dir.
 * If options.log_dir is empty do nothing.
 *
 * @param string $stage e.g. in, 401, 200, ...
 */
protected function logRequest($stage) {

	if (empty($this->options['log_dir'])) {
		return;
	}

	if (!isset($this->request['_SERVER'])) {
		$log_server = [ 'REQUEST_URI', 'SCRIPT_NAME' ];
		$this->request['_SERVER'] = [];

		foreach ($_SERVER as $key => $value) {
			if (substr($key, 0, 5) == 'HTTP_' || in_array($key, $log_server)) {
				$this->request['_SERVER'][$key] = $value;
			}
		}
	}

	if (!isset($this->request['_GET'])) {
		$this->request['_GET'] = $_GET;
	}

	if (!isset($this->request['_POST'])) {
		$this->request['_POST'] = $_POST;
	}

	if (!isset($this->request['_INPUT'])) {
		$this->request['_INPUT'] = file_get_contents('php://input');
	}

	File::save($this->options['log_prefix'].'.'.$stage.'.json', JSON::encode($this->request));
}


/**
 * Set request.method (as lowerstring), request.content-type and request.input-type.
 * Overwrite method with header "X-HTTP-METHOD[-OVERRIDE]".
 *
 * @throws if invalid
 */
private function checkMethodContent() {
	$method = empty($_SERVER['REQUEST_METHOD']) ? 'get' : $_SERVER['REQUEST_METHOD'];

	if (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
		$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
	}
	else if (!empty($_SERVER['HTTP_X_HTTP_METHOD'])) {
		$method = $_SERVER['HTTP_X_HTTP_METHOD'];
	}

	if (empty($method)) {
		throw new RestServerException('empty method', self::ERR_INVALID_INPUT, 400);
	}

	$this->request['method'] = strtolower($method);

	if (!in_array($this->request['method'], $this->options['allow_method'])) {
		throw new RestServerException('invalid method', self::ERR_INVALID_INPUT, 400, 'method='.$this->request['method'].
			' allowed='.join(', ', $this->options['allow_method']));
	}

	$this->parseContentType(); 

	if (!empty($this->request['content-type']) && !in_array($this->request['content-type'], $this->options['accept'])) {
		throw new RestServerException('invalid content-type', self::ERR_INVALID_INPUT, 400, 
			'type='.$this->request['content-type'].' allowed='.join(', ', $this->options['accept']));
	}
}


/**
 * Read api request. Use options.log_dir to save parsed input.
 * Use this.request to retrieve input (information). 
 */
public function readInput() {
	$this->checkMethodContent();
	$this->checkApiToken(); 
	$this->parse();
	$this->route();
	$this->logRequest('in');
}


/**
 * Process api request. Example: 
 *
 * request = [ 'method' => 'get', 'path' => 'user/3832', 'api_call' => 'getUser', api_call_parameter => [ 3832 ] ]
 * call this.getUser(3832)  (up to three parameter otherwise use array as first parameter)
 * 
 * Call $this->parse() and $this->route(). 
 */
public function run() {
	$this->readInput();

	if (empty($this->request['api_call'])) {
		throw new RestServerException('invalid route', self::ERR_INVALID_INPUT, 400, 
			"url=".$this->request['path']." method=".$this->request['method']);
	}
 
  $config = $this->checkRequest();

	$this->prepareApiCall($config);	

	if (!empty($config['call_before'])) {
		$pre_process = $config['call_before'];
		$this->$pre_process();
	}

	$method = $this->request['api_call'];
	$p = $this->request['api_call_parameter'];
	$pn = count($p);
	$res = null;

	if ($pn > 3) {
		$res = $this->$method($p);
	}
	else if ($pn == 3) {
		$res = $this->$method($p[0], $p[1], $p[2]);
	}
	else if ($pn == 2) {
		$res = $this->$method($p[0], $p[1]);
	}
	else if ($pn == 1) {
		$res = $this->$method($p[0]);
	}
	else {
		$res = $this->$method();
	}

	if (!empty($config['call_after'])) {
		$post_process = $config['call_after'];
		$res = $this->$post_process($res);
	}

	if ($res === null) {
		$res = $this->apiCallNotImplemented();
	}

	$this->out($res);
}


/**
 * Called if method has no result. Subclass if necessary.
 * 
 * @return this.request
 */
protected function apiCallNotImplemented() {
	return $this->request;
}


/**
 * Prepare api call. Subclass this method if you need to intercept.
 * Apply request.map operations according to p.set, p.preset, p.check map
 * and p.required vector.
 *
 * @see this.checkRequest() 
 * @param map $p configuration (set, preset, input)
 */
protected function prepareApiCall($p) {

	if (isset($p['preset']) && is_array($p['preset'])) {
		foreach ($p['preset'] as $key => $value) {
			if (!isset($this->request['map'][$key])) {
				$this->request['map'][$key] = $value;
			}
    }
  }

	if (isset($p['set']) && is_array($p['set'])) {
		foreach ($p['set'] as $key => $value) {
			$this->request['map'][$key] = $value;
    }
  }

  if (isset($p['input']) && is_array($p['input'])) {
    foreach ($p['input'] as $key => $require_check) {
			if (!empty($require_check[0]) && empty($this->request['map'][$key])) {
      	throw new RestServerException('missing required parameter', self::ERR_INVALID_INPUT, 403, 'parameter='.$key);
			}

			if (!empty($require_check[1]) && isset($this->request['map'][$key])) {
				if (!ValueCheck::run($key, $this->request['map'][$key], $require_check[1])) {
      		throw new RestServerException('parameter check failed', self::ERR_INVALID_INPUT, 403, $key.'=['.$this->request['map'][$key].'] check='.$require_check[1]);
				}
			}
    }
  }
}


/**
 * Overwrite for api logging. This function calls lib\log_error if $code >= 400.
 *
 * @param int $code
 * @param map $p
 * @param string $out
 */
protected function logResult($code, $p, $out) {

	$this->logRequest($code);

	if ($code >= 400) {
		$info = empty($p['error_info']) ? '' : "\n".$p['error_info'];
		\rkphplib\lib\log_error("API ERROR ".$p['error_code']."/$code: ".$p['error'].$info);
	}
}


/**
 * Subclass if you want custom authentication. Otherwise adjust option.auth_dir or option.auth_query
 * if necessary. Check if request.api_token is valid and request.route call is allowed.
 * Return map with preset, set, required, check, call_before and call_after keys. Example:
 *
 * - preset = { "country": "de", ... }
 * - set = { "country": "de", ... }
 * - input = { "firstname": ["1", ""], "age": ["0", ""], "email": ["1", "isEmail"], ... }
 * - output = { "col": "alias", ... } or [ "col", ... ]
 * - call_before = if set call $this->$call_before() before request.api_call 
 * - call_after = if set call $this->$call_after($output) after request.api_call
 *
 * Apply preset|set|required|check to request.map.
 *
 * @throws if api_token is invalid or access is forbidden
 * @return map 
 */
public function checkRequest() {

	if (empty($this->request['token']) || strtolower($this->request['token']) == 'default') { 
		if (!empty($this->options['allow_route'])) {
			$route = mb_strtolower($this->request['method']).'/'.$this->request['path'];
			if (in_array($route, $this->options['allow_route'])) {
				return [];
			}
		}

		throw new RestServerException('invalid api token', self::ERR_INVALID_INPUT, 400);
	}

	$user = $this->user_config();

	$method = $this->request['method'];

	if (!in_array($method, $user['config']['allow'])) {
		throw new RestServerException('forbidden', self::ERR_INVALID_API_CALL, 403);
	}

	$res = $user['default'][$method];
	$res['allow'] = $user['config']['allow'];

	// overwrite default with custom
	$use_keys = [ 'input', 'output', 'set', 'preset', 'call_before', 'call_after' ];
	foreach ($use_keys as $key) {
		if (isset($user['config'][$method][$key])) {
			$res[$key] = $user['config'][$method][$key];
		}
	}

	// resolve "@ref" keys
	$use_keys = [ 'set', 'preset', 'input', 'output' ];
	foreach ($use_keys as $key) {
		if (isset($res[$key])) {
			foreach ($res[$key] as $skey => $sval) {
				if (mb_substr($skey, 0, 1) == '@') {
					unset($res[$key][$skey]);
					foreach ($user['default'][$key] as $dkey => $dval) {
						$res[$key][$dkey] = $dval;
					}
				}
			}
		}
	}

	return $res;
}


/**
 * Return user data for current api call. Either options.auth_query or 
 * options.auth_dir (or both) must be defined. 
 *
 * @throws
 * @return map
 */
private function user_config() {

	if (!empty($this->options['auth_query'])) {
		$db = Database::getInstance(SETTINGS_DSN, [ 'check_token' => $this->options['auth_query'] ]);
		$user = $db->select($db->getQuery('check_token', $this->request));

		if (count($user) != 1) {
			throw new RestServerException('invalid api token', self::ERR_INVALID_INPUT, 401);
		}

		$user['config'] = JSON::decode($user['config']);

		if (!empty($this->options['auth_dir'])) {
			$user['default'] = JSON::decode(File::load($this->options['auth_dir'].'/config.json'));

			if (is_string($user['config']) && $user['config'] == 'auth_dir') {
				$user['config'] = JSON::decode(File::load($this->options['auth_dir'].'/config.'.$user['id'].'.json'));
			}
		}
		else {
			$tmp = $db->selectOne($db->getQuery('check_token', [ 'token' => 'default' ]));
			$user['default'] = JSON::decode($tmp['config']);
		}
	}
	else if (!empty($this->options['auth_dir'])) {
		$user_json = $this->options['auth_dir'].'/config.'.md5($this->request['token']).'.json';
		if (!File::exists($user_json)) {
			throw new RestServerException('invalid api token', self::ERR_INVALID_INPUT, 401);
		}

		$user = JSON::decode(File::load($user_json));
		$user['default'] = JSON::decode(File::load($this->options['auth_dir'].'/config.json'));
	}
	else {
		throw new RestServerException('auth_query and auth_dir empty', self::ERR_CONFIGURATION, 501);
	}

	if (!is_array($user['default']) || !is_array($user['config']) || !isset($user['config']['allow']) || !is_array($user['config']['allow'])) {
		throw new RestServerException('user_config invalid', self::ERR_CONFIGURATION, 501);
	}

	return $user;
}


}

