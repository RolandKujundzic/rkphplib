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
 * set_exception_handler([$rs, 'exceptionHandler']);
 * set_error_handler([$rs, 'errorHandler']);
 *
 * Avaliable HTTP methods are GET (retrieve), HEAD, POST (create new), PUT (update), PATCH (modify use with JSON|XML Patch - see
 * http://jsonpatch.com/), DELETE (return status 200 or 404), OPTIONS (return SWAGGER path).  
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

	if (empty($method)) {
		throw new RestServerException('empty method', self::ERR_INVALID_INPUT, 400);
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

	if (empty($_SERVER['CONTENT_TYPE']) && empty($_SERVER['HTTP_CONTENT_LENGTH'])) {
		return [ '', '' ];
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
		throw new RestServerException('unknown content-type', self::ERR_INVALID_INPUT, 400, "type=$type");
	}

	return [ $type, $input ];
}

	
/**
 * Catch all php errors. Activate with:
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
 * Catch all Exceptions. Activate with:
 *
 * set_exception_handler([$this, 'errorHandler']);
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
 * - allow_method = [ put, get, post, delete, patch, head, options ]
 * - xml_root: XML Root node of api result (default = '<api></api>')
 * - allow_auth = [ header, request, basic_auth, oauth2 ]
 *
 * @param map $options = []
 */
public function __construct($options = []) {
	$this->options = [];

	$this->options['Accept'] = [ 'application/json', 'application/xml', 'application/octet-stream', 
		'image/*', 'text/*', 'video/*', 'audio/*' ];

	$this->options['allow_method'] = [ 'get', 'post', 'put', 'delete', 'patch', 'head', 'options' ];

	$this->options['allow_auth'] = [ 'header', 'request', 'basic_auth', 'oauth2' ];

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
 * Default options.allow_auth is [ header, request, oauth2, basic_auth ]
 * 
 * @exit If no credentials are passed basic_auth is allowed and required exit with "401 - basic auth required"
 * @param bool $force_basic_auth = true
 * @return vector<string,string> [ token, auth ] 
 */
public function getApiToken($force_basic_auth = true) {
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
	else if ($force_basic_auth && $allow_basic_auth) {
		header('WWW-Authenticate: Basic realm="REST API"');
		header('HTTP/1.0 401 Unauthorized');
		print lib\translate('Please enter REST API basic authentication credentials');
		exit;
	}

	list ($this->request['token'], $this->request['auth']) = $res;

	return $res;
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
    	parse_str($input, $this->request['map']);
		}
	}

	if (count($_GET) > 0) {
		// always use query parameter
		$this->request['map'] = array_merge($this->request['map'], $_GET);
	}

	if ($this->request['method'] != 'GET' && count($_POST) > 0) {
		// use post data unless method is GET
		$this->request['map'] = array_merge($this->request['map'], $_POST);
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
	else if ($must_exist) {
		throw new RestServerException('invalid route', self::ERR_INVALID_INPUT, 400, "url=$url method=$method");
	}

	return !empty($func);
}


/**
 * Return api call result. 
 *
 * Default result format is JSON (change with HTTP_ACCEPT: application/xml to XML).
 * Overwrite for custom modification. 
 *
 * If error occured return error (localized error message), error_code and error_info and send http code >= 400.
 * 
 * @param map $o
 * @param int $code (default = 200, use 400 if error)
 * @exit print JSON|JSONP|XML
 */
public function out($o, $code = 200) {

	$jsonp = empty($this->request['map']['jsonpCallback']) ? '' : $this->request['map']['jsonpCallback'];

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

	// 2xx = OK, 4xx = Client Error, 5xx = Server Error
	$status_code = [ '200' => 'OK', '201' => 'Created', '202' => 'Accepted', '204' => 'No Content',
		'205' => 'Reset Content', '206' => 'Partial Content', '208' => 'Already Reported', 
 		'400' => 'Bad Request', '401' => 'Unauthorized', '403' => 'Forbidden', '404' => 'Not Found',
 		'405' => 'Method Not Allowed', '406' => 'Not Acceptable', '408' => 'Request Timeout',
 		'409' => 'Conflict', '410' => 'Gone', '411' => 'Length Required', '412' => 'Precondition Failed',
 		'413' => 'Request Entity Too Large', '415' => 'Unsupported Media Type', '416' => 'Requested range not satisfiable',
		'417' => 'Expectation Failed', '422' => 'Unprocessable Entity', '423' => 'Locked',
		'424' => 'Failed Dependency', '426' => 'Upgrade Required', '428' => 'Precondition Required',
 		'429' => 'Too Many Requests', '431' => 'Request Header Fields Too Large', 
		'500' => 'Internal Server Error', '501' => 'Not Implemented', '503' => 'Service Unavailable',
		'504' => 'Gateway Time-out' ];
 
	if (!isset($status_code[$code])) {
		if ($code >= 400) {
			header('Status: 501 Not Implemented');
		}
		else {
			throw new RestServerException('invalid http status code', self::ERR_CODE, 500, "code=$code");
		}
	}
	else {
		header('Status: '.$code.' '.$status_code[$code]);
	}

	$this->logResult($code, $o, $output);

	header('Content-Length: '.mb_strlen($output));
	print $output;
	exit(0);
}


/**
 * Set request.method, request.content-type and request.input-type.
 *
 * @throws if invalid
 */
public function checkMethodContent() {
	$this->request['method'] = self::getRequestMethod();

	if (!in_array($this->request['method'], $this->options['allow_method'])) {
		throw new RestServerException('invalid method', self::ERR_INVALID_INPUT, 400, 'method='.$this->request['method'].
			' allowed='.join(', ', $this->options['allow_method']));
	}

	list ($this->request['content-type'], $this->request['input-type']) = self::getContentType(); 

	if (!empty($this->request['content-type']) && !in_array($this->request['content-type'], $this->options['Accept'])) {
		throw new RestServerException('invalid content-type', self::ERR_INVALID_INPUT, 400, 
			'type='.$this->request['content-type'].' allowed='.join(', ', $this->options['Accept']));
	}
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
	$this->checkMethodContent();
	$this->getApiToken(); 
	$this->parse();
	$this->route(); 
  $this->checkRequest();
	$method = $this->request['api_call'];

	$p = $this->request['api_call_parameter'];
	$pn = count($p);

	if ($pn > 3) {
		$this->$method($p);
	}
	else if ($pn == 3) {
		$this->$method($p[0], $p[1], $p[2]);
	}
	else if ($pn == 2) {
		$this->$method($p[0], $p[1]);
	}
	else if ($pn == 1) {
		$this->$method($p[0]);
	}
	else {
		$this->$method();
	}
}


/**
 * Check if request.api_token is valid and request.route call is allowed.
 *
 * @throws if api_token is invalid or access is forbidden
 */
abstract public function checkRequest();


/**
 * Overwrite for api logging. This function calls lib\log_error if $code >= 400.
 *
 * @param int $code
 * @param map $p
 * @param string $out
 */
public function logResult($code, $p, $out) {
	if ($code >= 400) {
		$info = empty($p['error_info']) ? '' : "\n".$p['error_info'];
		lib\log_error("API ERROR ".$p['error_code']."/$code: ".$p['error'].$info);
	}

	$logfile = '/tmp/api/'.date('YmdHis').'-'.$code.'-'.$this->request['auth'].'-'.$this->request['api_call'].'.json';
	File::save($logfile, JSON::encode($this->request));
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
/**
protected function _call_custom($method, $plist = array()) {

  if (empty($this->_priv['custom'])) {
    return;
  }

  $cc = '_'.$this->_priv['custom'].'_'.$method;
  if (method_exists($this, $cc)) {
    call_user_func_array(array($this, $cc), $plist);
  }
}
*/

}

