<?php

namespace rkphplib;

require_once(__DIR__.'/XML.class.php');
require_once(__DIR__.'/lib/error_msg.php');
// require_once(__DIR__.'/lib/log_debug.php');



/**
 * Abstract rest api class.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ARestAPI {

const ERR_UNKNOWN = -1;

const ERR_INVALID_API_CALL = -2;

const ERR_INVALID_ROUTE = -3;

const ERR_JSON_TO_XML = -4;

/** @var map $req Request data */
protected $_req = array();

/** @var map $priv Request data */
protected $_priv = array();

/** @var string $xml_root XML Root node of api result */
public static $xml_root = '<api></api>';



/**
 * Return apache .htaccess configuration. 
 *
 * Allow GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS from anywhere.
 * Redirect everything to index.php. Unsupported: CONNECT, TRACE. 
 *
 * @param string $api_script
 * @return string
 */
public static function apacheHtaccess($api_script = '/api/v1.0/index.php') {
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
 * @param string $api_script
 * @return string
 */
public static function nginxLocation($api_script = '/api/v1.0/index.php') {
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
 * Return _SERVER.REQUEST_METHOD.
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

	return $method;
}


/**
 * Parse request and api_token.
 *
 * Fill $_req with merged data from _GET, _POST and php://input.
 * Input is parsed as Query-String unless either context-type "application/xml" or "application/json" is set.
 *
 *  Content-type: application/xml = XML Input
 *  Content-type: application/json = JSON Input
 *  Request_Method: GET = use $_GET
 *  Request_Method: POST = use $_POST
 *  Request_Method: PUT, DELETE, PATCH, HEAD, OPTIONS = use _REQUEST 
 *
 * Pass authentication token ($_req[api_token]) via _GET|_POST[api_token] (auth=request), _SERVER[HTTP_X_AUTH_TOKEN] (auth=header)
 * or basic authentication ($_req[api_token] = $_SERVER[PHP_AUTH_USER]:$_SERVER[PHP_AUTH_PW], auth=basic_auth).
 * If no credentials are passed exit with "401 - basic auth required".
 *
 * @return map keys: method, input, ip, content-type, auth, post, get, request
 */
public function parse() {

	$this->_req = array();

	$r = array();
	$r['content-type'] = empty($_SERVER['CONTENT_TYPE']) ? '' : $_SERVER['CONTENT_TYPE'];
	$r['method'] = static::getRequestMethod();
	$r['ip'] = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];

	$input = file_get_contents('php://input');

	if ($input) {
		$r['input'] = $input;

		if (strpos($r['content-type'], 'image/') !== false) {
			$this->_req['image'] = $input;
			$input = '';
		}
		else if (strpos($r['content-type'], 'application/xml') !== false) {
			// e.g. Content-type: application/xml; UTF-8
			$this->_req = XML::toJSON($input);
			$input = '';
		}
		else if (strpos($r['content-type'], 'application/json') !== false) {
			$this->_req = json_decode($input, true);
			$input = '';
		}
	}

	if ($r['method'] == 'POST' && count($_POST) > 0) {
		$this->_req = array_merge($this->_req, $_POST);
		$r['post'] = $_POST;
	}

	if ($r['method'] == 'GET' && count($_GET) > 0) {
		$this->_req = array_merge($this->_req, $_GET);
		$r['get'] = $_GET;
	}

	if (in_array($r['method'], array('PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'))) {
		if ($input) {
			// assume input is query string: a=b&...
    	parse_str($input, $this->_req);
		}

		if (count($_REQUEST) > 0) {
			$this->_req = array_merge($this->_req, $_REQUEST);
			$r['request'] = $_REQUEST;
		}
	}

	if (!empty($this->_req['api_token'])) {
		$r['auth'] = 'request';
	}
	else if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
		$this->_req['api_token'] = trim($_SERVER['HTTP_X_AUTH_TOKEN']);
		$r['auth'] = 'header';
	}
	else if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
		$this->_req['api_token'] = $_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'];
		$r['auth'] = 'basic_auth';
	}
	else if (!isset($_SERVER['PHP_AUTH_USER'])) {
		header('WWW-Authenticate: Basic realm="REST API"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Please enter REST API basic authentication credentials';
		exit;
	}

	return $r;
}


/**
 * Process api request.
 * 
 * Call $this->parse() and $this->route(). 
 */
abstract public function run();


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
 * Set $this->_req[api_call].
 * Set $this->_req[api_id] = $id if /url/:id is found.
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
	$base = str_replace(array('\\',' '), array('/','%20'), dirname($_SERVER['SCRIPT_NAME']));

	if (($pos = mb_strpos($r['url'], '?')) > 0) {
		$r['url'] = substr($r['url'], 0, $pos);
	}

	if (mb_strlen($base) > 0 && mb_strpos($r['url'], $base) === 0) {
		$r['url'] = substr($r['url'], mb_strlen($base));
	}

	if (substr($r['url'], 0, 1) == '/') {
		$r['url'] = substr($r['url'], 1);
	}

	$parent_url = dirname($r['url']);

	$api_map = static::apiMap();
	$func_id = '';
	$func = '';

	foreach ($api_map as $fname => $fconf) {

		if ($fconf[0] != $r['method']) {
			continue;
		}

		if ($fconf[1] == $r['url']) {
			$func = $fname;
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
			$output = XML::fromJSON($o);
		}
		catch (Exception $e) {
			$this->error(lib\error_msg('XML::fromJSON error: p1x', array($e->getMessage())), self::ERR_JSON_TO_XML);
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

