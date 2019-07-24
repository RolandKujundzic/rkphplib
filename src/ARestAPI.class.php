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
require_once(__DIR__.'/ValueCheck.class.php');
require_once(__DIR__.'/Database.class.php');
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
 * Implement api callback methods ($method$Path e.g. getSomeAction, postUser, ... ).
 * Api callback methods throw exceptions on error and return result.
 * Subclass setUser(), setUserConfig() if necessary.
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

/** @var map current user data */
protected $user = [];

/** @var map user configuration */
protected $config = [];



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
 * Print CORS (allow-all) header.
 * 
 * Access-Control-Allow-Origin: *
 * Access-Control-Allow-Headers: *
 * Access-Control-Allow-Methods: GET,POST,PUT,DELETE (if parameter $methods is empty/not-used)
 * Access-Control-Max-Age: 600
 *
 * @param string $methods
 */
public static function CorsAllowAll($methods = 'GET,POST,PUT,DELETE') {
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: *');
	header('Access-Control-Allow-Methods: '.$methods);
	header('Access-Control-Max-Age: 600');  // max. 60 * 10 sec = 10 min cachable
}


/**
 * Parse Content-Type header and set request[input-type], request[method] and request[content-type].
 *
 * input: data, xml, json, urlencoded or multipart
 * mime_type: application/xml|json|octet-stream|x-www-form-urlencoded, [image|text|video|audio/]*
 *
 * @throws if content-type header is empty or unknown
 * @param array-reference &$request
 */
public static function parseHeader(&$request) {
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

	$request['method'] = strtolower($method);

	if (empty($_SERVER['CONTENT_TYPE'])) {
		$request['content-type'] = '';
		$request['input-type'] = '';
		return;
	}

	$type = mb_strtolower($_SERVER['CONTENT_TYPE']);
	$input = null;

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

	$request['content-type'] = $type;
	$request['input-type'] = $input;
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

	$result = [ 'error' => "PHP ERROR [$errNo] $errStr", 
		'error_code' => self::ERR_PHP, 
		'error_info' => "file=$errFile line=$errLine",
		'api_call' => $this->request['api_call']
	];

	$this->out($result, 500);
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

	$result = [ 'error' => $e->getMessage(), 
		'error_code' => $e->getCode(), 
		'error_info' => $internal,
		'api_call' => $this->request['api_call']
	];

	$this->out($result, $http_code);
}


/**
 * Set Options:
 *
 * - Accept: allowed Content-Type (default = [application/json, application/xml, application/octet-stream, 
 *    multipart/form-data, application/x-www-form-urlencoded, image|text|audio|video/*])
 * - allow_api_call = [], e.g. [ getToken ] = allow GET /token without valid token
 * - allow_method = [ put, get, post, delete, patch, head, options ]
 * - force_basic_auth = true
 * - xml_root: XML Root node of api result (default = '<api></api>')
 * - allow_auth = [ header, request, basic_auth, oauth2 ]
 * - log_dir = '' (if set save requests to this directory) 
 * - base64_scan = bool (if set use base64_dir = log_dir)
 * - base64_dir = '' (if set decode and save values with "data:image/([a-z0-9+]);base64,..." prefix)
 * - auth_query = optional, e.g. SELECT id, token, config FROM api_user WHERE token='{:=token}' AND valid > NOW() AND status=1
 * - internal_error = false
 *
 * @param map $options = []
 */
public function __construct($options = []) {
	$this->options = [];

	$this->options['accept'] = [ 'application/x-www-form-urlencoded', 'multipart/form-data',
		'application/json', 'application/xml', 'application/octet-stream', 
		'image/*', 'text/*', 'video/*', 'audio/*' ];

	$this->options['allow_api_call'] = [];
	$this->options['allow_method'] = [ 'get', 'post', 'put', 'delete', 'patch', 'head', 'options' ];
	$this->options['allow_auth'] = [ 'header', 'request', 'basic_auth', 'oauth2' ];
	$this->options['xml_root'] = '<api></api>';
	$this->options['log_dir'] = '';
	$this->options['auth_query'] = "SELECT * FROM api_user WHERE token='{:=token}' AND valid > NOW() AND status=1";
	$this->options['force_basic_auth'] = true;
	$this->options['internal_error'] = false;

	foreach ($options as $key => $value) {
		$this->options[$key] = $value;
	}

	if (!empty($this->options['log_dir'])) {
		$log_dir = $this->options['log_dir'].'/'.date('Ym').'/'.date('dH');
		Dir::create($log_dir, 0, true);
		$unique_id = sprintf("%08x", abs(crc32($_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_TIME_FLOAT'] . $_SERVER['REMOTE_PORT'])));
		$this->options['log_prefix'] = $log_dir.'/api_'.date('is').'_'.$unique_id;
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
 * @throws if request['input-type'] or request['content-type'] is empty.
 * @param array-reference &$request
 */
public static function parse(&$request) {

	$request['timestamp'] = date('Y-m-d H:i:s').':'.substr(microtime(), 2, 3);
	$request['port'] = empty($_SERVER['REMOTE_PORT']) ? '' : $_SERVER['REMOTE_PORT'];
	$request['ip'] = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
	$request['data'] = null;
	$request['map'] = [];

	if (($input = file_get_contents('php://input'))) {
		if (empty($request['input-type'])) {
			throw new RestServerException('input-type missing call parseHeader first', self::ERR_NOT_IMPLEMENTED, 501);
		}

		if ($request['input-type'] == 'data') {
			$request['data'] = $input;
		}
		else if ($request['input-type'] == 'xml') {
			$request['map'] = XML::toMap($input);
		}
		else if ($request['input-type'] == 'json') {
			$request['map'] = JSON::decode($input);
		}
		else if ($request['input-type'] == 'urlencoded') {
			mb_parse_str($input, $request['map']);
		}
		else if ($request['input-type'] == 'multipart') {
			throw new RestServerException('ToDo: parse multipart/form-data input', self::ERR_NOT_IMPLEMENTED, 501);
		}
		else {
			throw new RestServerException('unknown input type', self::ERR_NOT_IMPLEMENTED, 501, 
				"content=".$request['content-type']." input=".$request['input-type']);
		}
	}

	if ($request['content-type'] == 'multipart/form-data' && count($_FILES) > 0) {
		$request['map'] = array_merge($_POST, $_FILES);
	}
	else if ($request['content-type'] == 'application/x-www-form-urlencoded') {
		if ($request['method'] == 'get') {
			$request['map'] = array_merge($GET);
		}
		else {
			$request['map'] = array_merge($request['map'], $_POST);

			if (count($_GET) > 0) {
				// always use query parameter - but prefer map parameter
				$request['map'] = array_merge($_GET, $request['map']);
			}
		}
	}
	else {
		if (count($_GET) > 0) {
			// always use query parameter - but prefer map parameter
			$request['map'] = array_merge($_GET, $request['map']);
		}

		if ($request['method'] != 'get' && count($_POST) > 0) {
			// always use post data unless method is GET - but prefer map parameter
			$request['map'] = array_merge($_POST, $request['map']);
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

	self::parseHeader($this->request); 

	if (!in_array($this->request['method'], $this->options['allow_method'])) {
		throw new RestServerException('invalid method', self::ERR_INVALID_INPUT, 400, 'method='.$this->request['method'].
			' allowed='.join(', ', $this->options['allow_method']));
	}

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
	self::parse($this->result);

	if (!empty($this->options['base64_scan']) && empty($this->options['log_dir'])) {
		$this->options['base64_dir'] = $this->options['log_dir'];
	}

	if (!empty($this->options['base64_dir'])) {
		self::saveBase64($this->request['map'], $this->options['base64_dir']);
	}

	$this->route();

	if (empty($this->request['token']) && !empty($this->request['map']['api_token'])) {
		$this->request['token'] = $this->request['map']['api_token'];
		unset($this->request['map']['api_token']);
	}

	$this->logRequest('in');
}


/**
 * Decode and save base64 data. Change value into file path.
 *
 * @param array $map
 * @param string $save_dir
 */
public static function saveBase64(&$map, $save_dir) {
	Dir::create($save_dir, 0, true);

	foreach ($map as $key => $value) {
  	if (is_string($value) && strlen($value) > 21 && preg_match('/^data\:image\/([a-z0-9]+);base64,/', $value, $match)) {
			$suffix = ($match[1] == 'jpeg') ? '.jpg' : '.'.$match[1];
			$skip = strlen($match[1]) + 19;
			File::save($save_dir.'/'.$key.$suffix, base64_decode(substr($value, $skip)));
			$map[$key] = $save_dir.'/'.$key.$suffix;
		}
  }
}


/**
 * Define this.config[default] and this.config[token].
 *
 */
abstract protected function setConfig();


/**
 * Process api request. Example: 
 *
 * request = [ 'method' => 'get', 'path' => 'user/3832', 'api_call' => 'getUser', api_call_parameter => [ 3832 ] ]
 * call this.getUser(3832)  (up to three parameter otherwise use array as first parameter)
 * 
 * Call self::parse() and $this->route().
 *
 * @throws 
 */
public function run() {
	$this->setConfig();
	$this->readInput();

	if (empty($this->request['api_call'])) {
		throw new RestServerException('invalid route', self::ERR_INVALID_INPUT, 400, 
			"url=".$this->request['path']." method=".$this->request['method']);
	}
 
	$this->setUser();
	$this->setUserConfig();
	$this->prepareApiCall();
	$this->checkRequest();

	if (!empty($user['config']['call_before'])) {
		$pre_process = $user['config']['call_before'];
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

	if (!empty($user['config']['call_after'])) {
		$post_process = $user['config']['call_after'];
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
 * Prepare api call. Apply this.user.config.preset and this.user.config.set 
 * to this.request.map.
 * 
 */
protected function prepareApiCall() {

	if (isset($this->user['config']['preset']) && is_array($this->user['config']['preset'])) {
		foreach ($this->user['config']['preset'] as $key => $value) {
			if (!isset($this->request['map'][$key])) {
				$this->request['map'][$key] = $value;
			}
    }
  }

	if (isset($this->user['config']['set']) && is_array($this->user['config']['set'])) {
		foreach ($this->user['config']['set'] as $key => $value) {
			$this->request['map'][$key] = $value;
    }
  }
}


/**
 * Overwrite for api logging. Call Exception::httpError($code) if $code >= 400.
 *
 * @param int $code
 * @param map $p
 * @param string $out
 */
protected function logResult($code, $p, $out) {

	$this->logRequest($code);

	if ($code >= 400) {
		$info = empty($p['error_info']) ? '' : "\n".$p['error_info'];
		Exception::httpError($code, "API ERROR ".$p['error_code']."/$code: ".$p['error'].$info);
	}
}


/**
 * Return this.request.map.key value.
 *
 * @param string $key
 * @return string
 */
public function get($key) {
	return isset($this->request['map'][$key]) ? $this->request['map'][$key] : '';
}


/**
 * Apply required and check to api parameter.
 *
 * @throws if parameter is invalid
 */
protected function checkRequest() {

	if (isset($this->user['config']['required']) && is_array($this->user['config']['required'])) {
		foreach ($this->user['config']['required'] as $key) {
			if (empty($this->request['map'][$key])) {
      	throw new RestServerException('missing required parameter '.$key, self::ERR_INVALID_INPUT, 403, 'parameter='.$key);
			}
		}
	}

	if (isset($this->user['config']['check']) && is_array($this->user['config']['check'])) {
		foreach ($this->user['config']['check'] as $key => $check) {
			if (!ValueCheck::run($key, [ $this, 'get' ], $check)) {
				throw new RestServerException("parameter $key check failed", self::ERR_INVALID_INPUT, 403, "$key=$check");
			}
		}
	}
}


/**
 * Set this.user (this.user.config.allow). Check if request.token if valid. Check if api_call is allowed. 
 * If token or options.auth_query is empty user has only token and config keys. 
 * 
 * @throws
 */
protected function setUser() {

	if (!isset($this->config['default']) || !is_array($this->config['default']) || !is_array($this->config['default']['allow'])) {
		throw new RestServerException('config.default missing or invalid', self::ERR_CODE, 501);
	}

	$token = $this->request['token'];
	$api_call = $this->request['api_call'];
	$user = [ 'token' => $token, 'config' => [] ];

	if (empty($token)) {
		if (!empty($this->options['allow_api_call']) && in_array($api_call, $this->options['allow_api_call'])) {
			$this->user = [ 'token' => $token, 'config' => [ 'allow' => [ $api_call ] ] ];
			return;
		}
		else {
			throw new RestServerException('invalid api token', self::ERR_INVALID_INPUT, 401);
		}
	}
	else if (!empty($this->options['auth_query'])) {
		$db = Database::getInstance(SETTINGS_DSN, [ 'check_token' => $this->options['auth_query'] ]);
		$dbres = $db->select($db->getQuery('check_token', $this->request));
		if (count($dbres) != 1) {
			throw new RestServerException('invalid api token', self::ERR_INVALID_INPUT, 401);
		}

		$user = $dbres[0];

		if (!empty($user['config'])) {
			$this->config[$token] = JSON::decode($user['config']);
		}
	}

	$allow = isset($this->config[$token]) && isset($this->config[$token]['allow']) ? 
		$this->config[$token]['allow'] : $this->config['default']['allow'];

	if (!in_array($api_call, $allow)) {
		throw new RestServerException('forbidden', self::ERR_INVALID_API_CALL, 403);
	}

	$this->user = $user;
}


/**
 * Set this.user.config = checks for current api call. Merge this.config[default] 
 * with this.user[config]. User config keys:
 *
 * - set = { "country": "de", ... }
 * - preset = { "country": "de", ... }
 * - required = [ "firstname", "lastname", ... ]
 * - check = { "firstname": ["1", ""], "age": ["0", ""], "email": ["1", "isEmail"], ... }
 * - call_before = if set call $this->$call_before() before request.api_call 
 * - call_after = if set call $this->$call_after($output) after request.api_call
 * - output = { "col": "alias", ... } or [ "col", ... ]
 *
 * @throws
 */
protected function setUserConfig() {

	$token = $this->request['token'];
	$api_call = $this->request['api_call'];

	if (!isset($this->config['default'][$api_call])) {
		throw new RestServerException('missing config.default.'.$api_call, self::ERR_INVALID_API_CALL, 501);
	}

	$this->user['config'] = $this->config['default'][$api_call];

	if (!isset($this->config[$token]) && !isset($this->config[$token][$api_call])) {
		return;
	}

	$use_keys = [ 'set', 'preset', 'check', 'required', 'call_before', 'call_after', 'output' ];
	$default = $this->config['default'][$api_call];
	$custom = $this->config[$token][$api_call];

	if (!isset($this->user['config']) || !is_array($this->user['config'])) {
		$this->user['config'] = [];
	}

	foreach ($use_keys as $key) {
		if (!isset($custom[$key]) && !isset($default[$key])) {
			// ignore $key
		}
		else if (isset($custom[$key])) {
			$this->user['config'][$key] = $custom[$key];
		}
		else if (isset($default[$key])) {
			$this->user['config'][$key] = $default[$key];
		}
	}
}


}

