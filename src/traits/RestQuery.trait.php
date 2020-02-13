<?php

namespace rkphplib\traits;

$parent_dir = dirname(__DIR__);
require_once $parent_dir.'/RestException.class.php';
require_once $parent_dir.'/File.class.php';
require_once $parent_dir.'/XML.class.php';
require_once $parent_dir.'/JSON.class.php';

use rkphplib\RestException;
use rkphplib\File;


/**
 * Parse Rest Query.
 * 
 * @code:
 * require_once(PATH_RKPHPLIB.'trait/RestQuery.trait.php');
 *
 * class SomeClass {
 * use \rkphplib\trait\RestQuery;
 * @:
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 */
trait RestQuery {

/**
 * Print CORS (allow-all) header.
 * 
 * Access-Control-Allow-Origin: *
 * Access-Control-Allow-Headers: *
 * Access-Control-Allow-Methods: GET,POST,PUT,DELETE (if parameter $methods is empty/not-used)
 * Access-Control-Max-Age: 600
 */
public static function CorsAllowAll(string $methods = 'GET,POST,PUT,DELETE') : void {
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: *');
	header('Access-Control-Allow-Methods: '.$methods);
	header('Access-Control-Max-Age: 600');  // max. 60 * 10 sec = 10 min cachable
}


/**
 * Return method (default = get). Try $_SERVER[HTTP_X_HTTP_METHOD_OVERRIDE], $_SERVER[HTTP_X_HTTP_METHOD], 
 * $_SERVER[REQUEST_METHOD], $_REQUEST[method] to determine method (apply strtolower).
 */
public static function getMethod() : string {
	$method = 'get';

	if (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
		$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
	}
	else if (!empty($_SERVER['HTTP_X_HTTP_METHOD'])) {
		$method = $_SERVER['HTTP_X_HTTP_METHOD'];
	}
	else if (!empty($_SERVER['REQUEST_METHOD'])) {
		$method = $_SERVER['REQUEST_METHOD'];
	}
	else if (!empty($_REQUEST['method'])) {
		$method = $_REQUEST['method'];
	}

	return strtolower($method);
}


/**
 * Return content-type. Use $_SERVER[CONTENT_TYPE] to determine content-type.
 * Result is empty, [image|text|video|audio]/*, multipart/form-data or 
 * application/[xml|json|octet-stream|x-www-form-urlencoded].
 */
public static function getContentType() : string {
	if (empty($_SERVER['CONTENT_TYPE'])) {
		return '';
	}

	$ctype = mb_strtolower($_SERVER['CONTENT_TYPE']);
	$content_type = $ctype;

	if (mb_strpos($ctype, 'image/') !== false) {
		$content_type = 'image/*';
	}
	else if (mb_strpos($ctype, 'text/') !== false) {
		$content_type = 'text/*';
	}
	else if (mb_strpos($ctype, 'video/') !== false) {
		$content_type = 'video/*';
	}
	else if (mb_strpos($ctype, 'audio/') !== false) {
		$content_type = 'audio/*';
	}
	else if (mb_strpos($ctype, 'application/octet-stream') !== false) {
		$content_type = 'application/octet-stream';
	}
	else if (mb_strpos($ctype, 'application/xml') !== false) {
		$content_type = 'application/xml';
	}
	else if (mb_strpos($ctype, 'application/json') !== false) {
		$content_type = 'application/json';
	}
	else if (mb_strpos($ctype, 'application/x-www-form-urlencoded') !== false) {
		$content_type = 'application/x-www-form-urlencoded';
	}
	else if (mb_strpos($ctype, 'multipart/form-data') !== false) {
		$content_type = 'multipart/form-data';
	}

	return $content_type;
}


/**
 * Return input-type of content-type (empty, data, xml, json, urlencoded or multipart).
 */
public static function getInputType(string $content_type) : string {
	$ct6 = substr($content_type, 0, 6);
	$ct5 = substr($content_type, 0, 5);
	$input_type = $content_type;

	$map = [
		'application/octet-stream' => 'data',
		'application/xml' => 'xml',
		'application/json' => 'json',
		'application/x-www-form-urlencoded' => 'urlencoded',
		'multipart/form-data' => 'multipart'
	];
	
	if ($ct6 == 'image/' || $ct6 == 'video/' || $ct6 == 'audio/' || $ct5 == 'text/') {
		$input_type = 'data';
	}
	else if (isset($map[$content_type])) {
		$input_type = $map[$content_type];
	}

	return $input_type;
}


/** 
 * Return [ authentication token, authentication method ]:
 *
 * 	- _GET|_POST[token|api_token] (auth=request)
 *  - _SERVER[HTTP_X_AUTH_TOKEN] (auth=header)
 *  - basic authentication, token = $_SERVER[PHP_AUTH_USER]:$_SERVER[PHP_AUTH_PW] (auth=basic_auth)
 *  - OAuth2 Token = Authorization header (auth=oauth2)
 * 
 * Default $allow_auth is [ header, request, oauth2, basic_auth ].
 */
public static function getTokenAndAuth(array $allow_auth) : array {
	$res = [ '', '' ];

	if (in_array('request', $allow_auth)) {
		$token_keys = [ 'token', 'api_token' ];

		foreach ($token_keys as $key) {
			if (!empty($_REQUEST[$key])) {
				$res = [ $_REQUEST[$key], 'request' ];
			}
		}
	}
	else if (!empty($_SERVER['HTTP_X_AUTH_TOKEN']) && in_array('header', $allow_auth)) {
		$res = [ $_SERVER['HTTP_X_AUTH_TOKEN'], 'header' ];
	}
	else if (!empty($_SERVER['AUTHORIZATION']) && in_array('oauth2', $allow_auth)) {
		$res = [ $_SERVER['HTTP_AUTHORIZATION'], 'oauth2' ];
	}
	else if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW']) && in_array('basic_auth', $allow_auth)) {
		$res = [ $_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'], 'basic_auth' ];
	}

	return $res;
}


/**
 * Set $request[method|content-type|input-type|token|auth].
 * If $options[force_basic_auth] is true and token was not determined call askBasicAuth (overwrite if necessary).
 *
 * @see getMethod
 * @see getContentType
 * @see getInputType
 * @see getTokenAndAuth
 */
public static function parseHeader(array &$request, array $options = [
		'allow_method' => [ 'put', 'get', 'post', 'delete', 'patch', 'head', 'options' ],
		'allow_auth' => [ 'header', 'request', 'basic_auth', 'oauth2', 'cli' ],
		'require_auth' => false,
		'force_basic_auth' => true
		]) : void {

	$request['method'] = self::getMethod();

	if (empty($request['method'])) {
		throw new RestException('empty method', RestException::ERR_INVALID_INPUT, 400);
	}
	else if (!in_array($request['method'], $options['allow_method'])) {
		throw new RestException('invalid method', RestException::ERR_INVALID_INPUT, 400,
			'method='.$request['method'].' allowed='.join(', ', $options['allow_method']));
	}

	$request['content-type'] = self::getContentType();
	$request['input-type'] = self::getInputType($request['content-type']);

	if (empty($request['content-type']) || empty($request['input-type']) || $request['content-type'] == $request['input-type']) {
		throw new RestException('unknown content-type', RestException::ERR_INVALID_INPUT, 400, $request['content-type']);
	}

	if (!in_array($request['content-type'], $options['accept'])) {
		throw new RestException('invalid content-type', RestException::ERR_INVALID_INPUT, 400, 
			'content-type='.$request['content-type'].' allowed='.join(', ', $options['accept']));
	}

	list ($request['token'], $request['auth']) = self::getTokenAndAuth($options['allow_auth']);

	if (empty($request['token']) && $options['force_basic_auth'] && in_array('basic_auth', $options['allow_auth'])) {
		$this->askBasicAuth();
	}

	if (empty($request['token']) && $options['require_auth']) {
		throw new RestException('invalid authentication', RestException::ERR_INVALID_INPUT, 400, 'allow_auth='.join(', ', $options['allow_auth']));
	}
}


/**
 * Send basic auth request to browser. Overwrite this if necessary otherwise RestException::ERR_NOT_IMPLEMENTED is thrown.
 *
 * @exit 401 - basic auth required
 */
private function askBasicAuth() : void {
	throw new RestException('invalid authentication', RestException::ERR_NOT_IMPLEMENTED, 501, 'overwrite RestQuery->askBasicAuth()');
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
 * Set this.request keys: ip, data, map, content-type and input-type
 */
public static function parse(array &$request) : void {

	$request['timestamp'] = date('Y-m-d H:i:s').':'.substr(microtime(), 2, 3);
	$request['port'] = empty($_SERVER['REMOTE_PORT']) ? '' : $_SERVER['REMOTE_PORT'];
	$request['ip'] = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
	$request['data'] = null;
	$request['map'] = [];

	if (($input = file_get_contents('php://input'))) {
		if (empty($request['input-type'])) {
			throw new RestException('input-type missing call parseHeader first', RestException::ERR_NOT_IMPLEMENTED, 501);
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
			throw new RestException('ToDo: parse multipart/form-data input', RestException::ERR_NOT_IMPLEMENTED, 501);
		}
		else {
			throw new RestException('unknown input type', RestException::ERR_NOT_IMPLEMENTED, 501, 
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
 * Decode and save base64 data. Change value into file path.
 */
public static function saveBase64(array &$map, string $save_dir) : void {
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


}

