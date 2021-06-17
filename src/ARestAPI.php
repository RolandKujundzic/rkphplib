<?php

namespace rkphplib;

// ToDo: Accept-Language: de_DE + OpenID Connect | OAUTH2
// http://de.slideshare.net/jcleblanc/securing-restful-apis-using-oauth-2-and-openid-connect
// https://github.com/jcleblanc/oauth
// https://github.com/thephpleague/oauth2-server
// https://github.com/bshaffer/oauth2-server-php + https://github.com/bshaffer/oauth2-demo-php

require_once __DIR__.'/traits/RestQuery.php';
require_once __DIR__.'/RestException.php';
require_once __DIR__.'/XML.php';
require_once __DIR__.'/JSON.php';
require_once __DIR__.'/File.php';
require_once __DIR__.'/Dir.php';
require_once __DIR__.'/ValueCheck.php';
require_once __DIR__.'/Database.php';
require_once __DIR__.'/lib/translate.php';
require_once __DIR__.'/lib/http_code.php';

use function rkphplib\lib\http_code;
use function rkphplib\lib\translate;


/**
 * Abstract rest api class. Example:
 *
 * class MyRestServer extends \rkphplib\ARestAPI {
 * protected function setConfig() : void { ... }
 * ...
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
use \rkphplib\traits\RestQuery;

// @var map $options
protected $options = [];

// @var map $request
protected $request = [];

// @var map current user data
protected $user = [];

// @var map user configuration
protected $config = [];


/**
 * @see RestQuery->askBasicAuth
 */
private function askBasicAuth() : void {
	header('WWW-Authenticate: Basic realm="REST API"');
	header('HTTP/1.0 401 Unauthorized');
	print translate('Please enter REST API basic authentication credentials');
	$this->logRequest((string)401);
	exit;
}


/**
 * Use php buildin webserver as API server. Return routing script source.
 * Start webserver on port 10080 and route https from 10443:
 * 
 * php -S localhost:10080 www/api/routing.php
 *
 * Enable https://localhost:10443/ with stunnel (e.g. stunnel3 -d 10443 -r 10080)
 */
public static function phpAPIServer() : string {

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
 */
public static function apacheHtaccess(string $api_script = '/api/index.php') : string {
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
 */
public static function nginxLocation(string $api_script = '/api/index.php') : string {
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
 * Catch all php errors. Activated in constructor: set_error_handler([$this, 'errorHandler']);
 * Exit with 500:ERR_PHP.
 */
public function errorHandler(int $errNo, string $errStr, string $errFile, int $errLine) : void {

  if (error_reporting() == 0) {
    // @ suppression used, ignore it
    return;
  }

	$result = [ 'error' => "PHP ERROR [$errNo] $errStr", 
		'error_code' => RestException::ERR_PHP, 
		'error_info' => "file=$errFile line=$errLine",
		'api_call' => $this->request['api_call']
	];

	$this->out($result, 500);
}


/**
 * Catch all Exceptions. Activated in constructor: set_exception_handler([$this, 'errorHandler']);
 */
public function exceptionHandler(\Exception $e) : void {
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
 * - require_auth = true
 * - xml_root: XML Root node of api result (default = '<api></api>')
 * - allow_auth = [ header, request, basic_auth, oauth2 ]
 * - log_dir = '' (if set save requests to this directory) 
 * - base64_scan = bool (if set use base64_dir = log_dir)
 * - base64_dir = '' (if set decode and save values with "data:image/([a-z0-9+]);base64,..." prefix)
 * - auth_query = optional, e.g. SELECT id, token, config FROM api_user WHERE token='{:=token}' AND valid > NOW() AND status=1
 * - internal_error = false
 */
public function __construct(array $options = []) {
	$this->options = [];

	$this->options['accept'] = [ 'application/x-www-form-urlencoded', 'multipart/form-data',
		'application/json', 'application/xml', 'application/octet-stream', 
		'image/*', 'text/*', 'video/*', 'audio/*' ];

	$this->options['allow_api_call'] = [];
	$this->options['allow_method'] = [ 'get', 'post', 'put', 'delete', 'patch', 'head', 'options' ];
	$this->options['allow_auth'] = [ 'header', 'request', 'basic_auth', 'oauth2', 'cli' ];
	$this->options['xml_root'] = '<api></api>';
	$this->options['log_dir'] = '';
	$this->options['auth_query'] = "SELECT * FROM api_user WHERE token='{:=token}' AND valid > NOW() AND status=1";
	$this->options['force_basic_auth'] = true;
	$this->options['require_auth'] = true;
	$this->options['internal_error'] = false;

	$this->options = array_merge($this->options, $options);

	if (!empty($this->options['log_dir'])) {
		$log_dir = $this->options['log_dir'].'/'.date('Ym').'/'.date('dH');
		Dir::create($log_dir, 0, true);
		if (php_sapi_name() == 'cli') {
			$unique_id = sprintf("%08x", abs(crc32($_SERVER['USERNAME'] . $_SERVER['REQUEST_TIME_FLOAT'] . $_SERVER['SESSION_MANAGER'])));
		}
		else {
			$unique_id = sprintf("%08x", abs(crc32($_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_TIME_FLOAT'] . $_SERVER['REMOTE_PORT'])));
		}

		$this->options['log_prefix'] = $log_dir.'/api_'.date('is').'_'.$unique_id;
	}

	set_error_handler([$this, 'errorHandler']);
	set_exception_handler([$this, 'exceptionHandler']);
}


/**
 * Determine api call from url and request method.
 *
 * Set request.path, request.method, request.api_call 
 * (e.g. getXaYbZc if URL=xa/yb/zc and getXyYbZc() exists) 
 * and request.api_call_parameter 
 * (e.g. [ $id1, id2 ] if URL=/do/:id1/:id2 and getDo() exists).
 */
public function route(bool $must_exist = true) : bool {

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
 * Return api call result (exit and print JSON|JSONP|XML).
 *
 * Default result format is JSON (change with HTTP_ACCEPT: application/xml to XML).
 * Overwrite for custom modification. If request.status is set and $code == 200 use 
 * request.status instead of $code. 
 *
 * If error occured return error (localized error message), error_code and error_info and send http code >= 400.
 */
public function out(array $o, int $code = 200) : void {

	if ($code == 200 && !empty($this->request['status'])) {
		$code = $this->request['status'];
	}

	$jsonp = empty($this->request['map']['jsonpCallback']) ? '' : $this->request['map']['jsonpCallback'];

	$header = [];

	if (!$this->options['internal_error'] && isset($o['error_info'])) {
		unset($o['error_info']);
	}

	if (!empty($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/xml') {
		$header['Content-Type'] = 'application/xml';
		$header['@output'] = XML::fromMap($o);
	}
	else if (!empty($jsonp)) {
		$header['Content-Type'] = 'application/javascript';
		$header['@output'] = $jsonp.'('.JSON::encode($o).')';
	}
	else {
		$header['Content-Type'] = 'application/json';
		$header['@output'] = JSON::encode($o);
	}

	$this->logResult($code, $o);
	http_code($code, $header);
}


/**
 * Save request and data from $_SERVER, $_GET, $_POST, php://input to options.log_dir.
 * If options.log_dir is empty do nothing.
 */
protected function logRequest(string $stage) : void {

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
 * Read api request. Use options.log_dir to save parsed input.
 * Use this.request to retrieve input (information). 
 */
public function readInput() : void {
	self::parseHeader($this->request, $this->options);
	self::parse($this->request);

	if (!empty($this->options['base64_scan']) && empty($this->options['log_dir'])) {
		$this->options['base64_dir'] = $this->options['log_dir'];
	}

	if (!empty($this->options['base64_dir'])) {
		self::saveBase64($this->request['map'], $this->options['base64_dir']);
	}

	$this->route();
print "<pre>request: ".print_r($this->request, true)."</pre>";

	$this->logRequest('in');
}


/**
 * Map cli input to request. Parameter are --name=value (default: --req-method=get). First parameter is path.
 */
protected function cliInput() : void {
	if (!in_array('cli', $this->options['allow_method'])) {
		throw new RestException('cli is not allowed', RestException::ERR_INVALID_INPUT, 400);
	}

	foreach ($_SERVER['argv'] as $parameter) {
		if (mb_substr($parameter, 0, 2) == '--' && ($pos = mb_strpos($parameter, '=')) > 0) {
			$name = mb_substr($parameter, 2, $pos - 2);
			$_REQUEST[$name] = mb_substr($parameter, $pos + 1);
		}
	}

	if (empty($_REQUEST['--req-method'])) {
		$_SERVER['REQUEST_METHOD'] = 'get';
	}
	else {
		$_SERVER['REQUEST_METHOD'] = $_REQUEST['--req-method'];
		unset($_REQUEST['--req-method']);
	}
}


/**
 * Define this.config[default] and this.config[token].
 */
abstract protected function setConfig() : void;


/**
 * Process api request. Example: 
 *
 * request = [ 'method' => 'get', 'path' => 'user/3832', 'api_call' => 'getUser', api_call_parameter => [ 3832 ] ]
 * call this.getUser(3832)  (up to three parameter otherwise use array as first parameter)
 * 
 * Call self::parse() and $this->route().
 */
public function run() : void {
	$this->setConfig();

	if (php_sapi_name() == 'cli') {
		$this->cliInput();
	}

	$this->readInput();

	if (empty($this->request['api_call'])) {
		throw new RestException('invalid route', RestException::ERR_INVALID_INPUT, 400, 
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
 */
protected function apiCallNotImplemented() : array {
	return $this->request;
}


/**
 * Prepare api call. Apply this.user.config.preset and this.user.config.set to this.request.map.
 * 
 */
protected function prepareApiCall() : void {

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
 */
protected function logResult(int $code, array $p) : void {

	$this->logRequest((string)$code);

	if ($code >= 400) {
		$info = empty($p['error_info']) ? '' : "\n".$p['error_info'];
		Exception::httpError($code, "API ERROR ".$p['error_code']."/$code: ".$p['error'].$info);
	}
}


/**
 * Return this.request.map.key value.
 */
public function get(string $key) : string {
	return isset($this->request['map'][$key]) ? $this->request['map'][$key] : '';
}


/**
 * Apply required and check to api parameter.
 */
protected function checkRequest() : void {

	if (isset($this->user['config']['required']) && is_array($this->user['config']['required'])) {
		foreach ($this->user['config']['required'] as $key) {
			if (empty($this->request['map'][$key])) {
      	throw new RestException('missing required parameter '.$key, RestException::ERR_INVALID_INPUT, 403, 'parameter='.$key);
			}
		}
	}

	if (isset($this->user['config']['check']) && is_array($this->user['config']['check'])) {
		foreach ($this->user['config']['check'] as $key => $check) {
			if (!ValueCheck::run($key, [ $this, 'get' ], $check)) {
				throw new RestException("parameter $key check failed", RestException::ERR_INVALID_INPUT, 403, "$key=$check");
			}
		}
	}
}


/**
 * Set this.user (this.user.config.allow). Check if request.token if valid. Check if api_call is allowed. 
 * If token or options.auth_query is empty user has only token and config keys. 
 */
protected function setUser() : void {

	if (!isset($this->config['default']) || !is_array($this->config['default']) || !is_array($this->config['default']['allow'])) {
		throw new RestException('config.default missing or invalid', RestException::ERR_CODE, 501);
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
			throw new RestException('invalid api token', RestException::ERR_INVALID_INPUT, 401);
		}
	}
	else if (!empty($this->options['auth_query'])) {
		$db = Database::getInstance(SETTINGS_DSN, [ 'check_token' => $this->options['auth_query'] ]);
		$dbres = $db->select($db->getQuery('check_token', $this->request));
		if (count($dbres) != 1) {
			throw new RestException('invalid api token', RestException::ERR_INVALID_INPUT, 401);
		}

		$user = $dbres[0];

		if (!empty($user['config'])) {
			$this->config[$token] = JSON::decode($user['config']);
		}
	}

	$allow = isset($this->config[$token]) && isset($this->config[$token]['allow']) ? 
		$this->config[$token]['allow'] : $this->config['default']['allow'];

	if (!in_array($api_call, $allow)) {
		throw new RestException('forbidden', RestException::ERR_INVALID_API_CALL, 403);
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
 */
protected function setUserConfig() : void {

	$token = $this->request['token'];
	$api_call = $this->request['api_call'];

	if (!isset($this->config['default'][$api_call])) {
		throw new RestException('missing config.default.'.$api_call, RestException::ERR_INVALID_API_CALL, 501);
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

