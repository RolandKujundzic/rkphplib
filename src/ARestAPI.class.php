<?php

namespace rkphplib;

require_once(__DIR__.'/lib/error_msg.php');
// require_once(__DIR__.'/lib/log_debug.php');


/**
 * Abstract rest api class.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ARestAPI {

/** @var map $req Request data */
protected $req = array();

/** @var map $priv Request data */
protected $priv = array();

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
	$r['method'] = self::getRequestMethod();
	$r['ip'] = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];

	$input = file_get_contents('php://input');

	if ($input) {
		$r['input'] = $input;

		if ($r['content-type'] == 'application/xml') {
			$xml = simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA);
			$this->_req = json_decode(json_encode((array)$xml), true);
			$input = '';
		}
		else if ($content_type == 'application/json') {
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
		$err_msg = lib\error_msg('invalid api call p1x', array($this->_req['api_call']));
		$this->out(['error' => $err_msg], 400);
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
 * Return allowed keys from complete_map.
 *
 * @param map $complete_map
 * @param vector $allow
 * @return $amp
 */
protected function allow($complete_map, $allow) {
	$res = [];

	foreach ($allow as $key) {
		if (isset($complete_map[$key])) {
			$res[$key] = $complete_map[$key];
		}
	}

	return $res;
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
 * @param map $api_map allowed api calls e.g. $this->allow(self::apiMap(), $allow)
 * @return map keys: method, url, base.
 */
public function route($api_map) {

	$r = array();
	$r['method'] = self::getRequestMethod();
	$r['url'] = $_SERVER['REQUEST_URI'];
	$base = str_replace(array('\\',' '), array('/','%20'), dirname($_SERVER['SCRIPT_NAME']));

	if (mb_strlen($base) > 0 && mb_strpos($r['url'], $base) === 0) {
		$r['url'] = substr($r['url'], mb_strlen($base));
	}

	if (substr($r['url'], 0, 1) == '/') {
		$r['url'] = substr($r['url'], 1);
	}

	if (($pos = mb_strpos($r['url'], '?')) > 0) {
		$r['url'] = substr($r['url'], 0, $pos);
	}

	$parent_url = dirname($r['url']);

	foreach ($api_map as $fname => $fconf) {

		if ($fconf[0] != $r['method']) {
			continue;
		}

		if ($fconf[1] == $r['url']) {
			$this->_req['api_call'] = $fname;
			break;
		}
		else if ($parent_url && ($fconf[2] == 1 || $fconf[2] == 2) && $fconf[1] == $parent_url) {
			$this->_req['api_id'] = basename($r['url']);
			$this->_req['api_call'] = $fname;
			$r['url'] = $parent_url;
			break;
		}
	}

	if (empty($this->_req['api_call']) || !method_exists($this, $this->_req['api_call'])) {
		$err_msg = lib\error_msg('invalid route p1x:/p2x', array($r['method'], $r['url']));
		$this->out(['error' => $err_msg], 400); 
	}

	return $r;
}


/**
 * Return api call result. 
 *
 * Default result format is JSON (change with HTTP_ACCEPT: application/xml to XML).
 * Overwrite for custom modification.
 *
 * @param map $o
 * @param int $code (default = 200, use 400 if error)
 * @return JSON|JSONP|XML
 */
public function out($o, $code = 200) {

	$is_jsonp = empty($this->_req['jsonpCallback']) ? false : true;

	if (!empty($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/xml') {
		header('Content-Type: application/xml');
		$output = self::json2xml($o);
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


/**
 * Convert JSON to XML.
 *
 * @param map $json
 * @return string
 */
protected static function json2xml($json, SimpleXMLElement $xml = null) {

  if (is_null($xml)) {
    $xml = new SimpleXMLElement('<'.'?'.'xml version="1.0" encoding="UTF-8"'.'?'.'>'.self::$xml_root);
	}

	foreach ($json as $key => $value){
		if (is_array($value)) {
			self::json2xml($value, $xml->addChild($key));
    }
    else {
      $xml->addChild("$key", "$value");
    }
  }

  return $xml->asXML();
}


}

