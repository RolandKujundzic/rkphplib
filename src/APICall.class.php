<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/JSON.class.php');
require_once(__DIR__.'/XML.class.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/Dir.class.php');
require_once(__DIR__.'/traits/Map.trait.php');


/**
 * API call wrapper. Use constructor or set() for configuration. 
 * After exec($data) result is saved in $this->result|info|status.
 * If $this->status != 200 call has failed. Default header are
 * { Content-Type: application/json, Accept: application/json, Accept-Charset: utf-8 }
 *
 * Set Content-type=application/x-www-form-urlencoded for ordinary GET/POST calls.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class APICall {
use rkphplib\traits\Map;


/** 
 * @var map $opt default options: 
 *
 * - method = GET, (GET|POST|PUT|DELETE|PATCH)
 * - uri = '', some/action (with or without leading /)
 * - url = '', e.g. https://domain.tld/api/v1.0
 * - auth = request, (request|header|basic_auth|cookie:name)
 * - header = { Content-Type: application/json, Accept: application/json, Accept-Charset: utf-8 }
 * - token = '', e.g. iSFxH73p91Klm
 * - save = '', result save path
 * - tags = [], use as default !TAG_REPLACE! in test() mode
 * - decode = true, decode result if JSON/XML
 */
public $opt = [
	'method' => 'GET', 
	'uri' => '', 
	'url' => '', 
	'auth' => 'request', 
	'token' => '', 
	'header' => [ 'CONTENT-TYPE' => 'application/json', 'ACCEPT' => 'application/json', 'ACCEPT-CHARSET' => 'utf-8' ],
	'save' => '',
	'decode' => true,
	'tags' => []
	];

/** @var map|string $result */
public $result = null;

/** @var map $info */
public $info = null;

/** @var int $status */
public $status = null;

/** @var vector|null $error (if not decode error null, otherwiese [ error_message, error_object ] */
public $error = null;

/** @var string $dump */
public $dump = '';



/**
 * See set() for allowed option parameter.
 */
public function __construct(array $opt = []) {
	foreach ($opt as $key => $value) {
		$this->set($key, $value);
	}
}


/**
 * Configure api call. Parameter:
 *
 *  - method: GET|POST|PUT|DELETE|PATCH
 *  - uri|path: request path e.g. some/action
 *  - url: required e.g. https://domain.tld/api/v1.0
 *  - token: required e.g. iSFxH73p91Klm
 *  - auth:  request|header|basic_auth
 *  - content: same as header[Content-Type] e.g. application/json=default|application/xml|image/jpeg|...
 *  - accept: same as header[Accept] = result format e.g application/json=default|application/xml|...
 *  - header: e.g. [ 'Content-Type' => 'application/json', ... ]
 *  - save_as: Save result here
 *  - decode: decode JSON/XML result (default = true)
 */
public function set(string $name, string $value) : void {

	if (!is_string($name)) {
		throw new Exception('name is not string', print_r($name, true));
	}

	if ($name != 'header' && !is_string($value)) {
		throw new Exception('value is not string', "$name: ".print_r($value, true));
	}

	if ($name == 'path') {
		$name = 'uri';
	}

	if ($name == 'method') {
		$value = strtoupper($value);
	}

	if ($name == 'content') {
		if (empty($value) && isset($this->opt['header']['CONTENT-TYPE'])) {
			unset($this->opt['header']['CONTENT-TYPE']);
		}
		else {
			$this->opt['header']['CONTENT-TYPE'] = $value;
		}
	}
	else if ($name == 'accept') {
		if (empty($value) && isset($this->opt['header']['ACCEPT'])) {
      unset($this->opt['header']['ACCEPT']);
    }
		else {
			$this->opt['header']['ACCEPT'] = $value;
		}
	}
	else if ($name == 'header') {
		foreach ($value as $hkey => $hval) {
			$hkey = strtoupper($hkey);

			if (!is_string($hval)) {
				throw new Exception('header value is not string', print_r($hval, true));
			}

			if (empty($hval) && isset($this->opt['header'][$hkey])) {
				unset($this->opt['header'][$hkey]);
			}
			else {
				$this->opt['header'][$hkey] = $hval;
			}
		}
	}
	else {
		$allow = [ 'method' => [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH' ], 'uri' => null, 'url' => null, 'token' => null, 
			'auth' => [ 'request', 'header', 'basic_auth' ], 'save' => null, 'decode' => null ];

		if (!array_key_exists($name, $allow)) {
			throw new Exception('invalid name', "$name=$value");
		}

		if (is_array($allow[$name]) && !in_array($value, $allow[$name])) {
			throw new Exception('invalid value', "$name=$value");
		}

		$this->opt[$name] = $value;
	}
}


/**
 * Return set value (any). 
 */
public function get(string $name) {

	if ($name == 'path') {
		$name = 'uri';
	}

	$uname = strtoupper($name);

	if (isset($this->opt['header'][$uname])) {
		$res = $this->opt['header'][$uname];
	}
	else if (isset($this->opt[$name])) {
		$res = $this->opt[$name];
	}
	else {
		throw new Exception('invalid option name or unset header', "$name");
	}

	return $res;
}


/**
 * Short for set('method', $method); set('uri', $uri); exec($data).
 */
public function call(string $method, string $uri, ?array $data = null) : bool {
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
 */
public function exec(?array $data = null) : bool {

	$required = [ 'method', 'uri', 'url' ];
	foreach ($required as $key) {
		if (empty($this->opt[$key])) {
			throw new Exception('missing required option '.$key);
		}
	}

	$header = $this->opt['header'];
	$options = [ 'FOLLOWLOCATION' => true, 'SSL_VERIFYPEER' => false, 'TIMEOUT' => 30, 
		'SSL_VERIFYHOST' => false, 'RETURNTRANSFER' => true, 'BINARYTRANSFER' => true ];

	if (!empty($this->opt['token'])) {
		if (empty($this->opt['auth'])) {
			throw new Exception('missing parameter', 'auth');
		}
		else if ($this->opt['auth'] == 'request' && is_array($data)) {
			$data['api_token'] = $this->opt['token'];
		}
		else if ($this->opt['auth'] == 'header') {
			$header['X-AUTH-TOKEN'] = $this->opt['token'];
		}
		else if ($this->opt['auth'] == 'basic_auth') {
			$options['USERPWD'] = $this->opt['token'];
		}
		else if (mb_strpos($this->opt['auth'], 'cookie:') === 0) {
			$options['COOKIE'] = mb_substr($this->opt['auth'], 7).'='.$this->opt['token']; 
		}
	}

	if ($this->opt['method'] != 'GET' && $this->opt['method'] != 'POST') {
		$header['X-HTTP-Method-Override'] = $this->opt['method'];
	}

	if ($this->opt['method'] == 'GET') {
		$options['HTTPGET'] = true;
	}
	else if ($this->opt['method'] == 'POST') {
		$options['POST'] = true;
	}
	else {
		$options['CUSTOMREQUEST'] = $this->opt['method'];
	}

	$url = (mb_substr($this->opt['uri'], 0, 1) == '/' || mb_substr($this->opt['url'], -1) == '/') ? 
		$this->opt['url'].$this->opt['uri'] : $this->opt['url'].'/'.$this->opt['uri'];

	if ($data !== null && is_array($data) && count($data) > 0) {
		if ($this->opt['method'] == 'GET') {
			$url .= $this->curlGetData($url, $data);
		}
		else {
			$options['POSTFIELDS'] = $this->curlOtherData($data, $options);
		}
	}

	if (count($header) > 0) {
		$header_lines = [];

		foreach ($header as $key => $value) {
			array_push($header_lines, $key.': '.$value);
		}

		$options['HTTPHEADER'] = $header_lines;
	}

	$options['URL'] = $url;

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

	if ($this->opt['decode']) {	
		if ($this->opt['header']['ACCEPT'] == 'application/json') {
			try {
				$this->result = JSON::decode($this->result);
			}
			catch (\Exception $e) {
				$this->error = [ 'json decode failed', $e ];
			}
		}
		else if ($this->opt['header']['ACCEPT'] == 'application/xml') {
			try {
				$this->result = XML::toMap($this->result);
			}
			catch (\Exception $e) {
				$this->error = [ 'xml decode failed', $e ];
			}
		}
	}

	if ($this->opt['save']) {
		File::save($this->opt['save'], $this->result);
	}

	return $success;
}


/**
 * Return url encoded query string.
 */
private function curlGetData(string $url, ?array $data) : string {
	$append = '';

	if (is_array($data) && count($data) > 0) {
		if (($pos = strpos($url, '?')) === false) {
			$append = '?'.http_build_query($data);
		}
		else {
			$append = '&'.http_build_query($data);
		}
	}

	return $append;
}


/**
 * Return encoded data (string|hash). Options are modified if content is multipart/form-data.
 */
private function curlOtherData(array $data, array &$options) {

	if (!isset($this->opt['header']['CONTENT-TYPE'])) {
		throw new Exception('Content-Type is not set');
	}

	$ct = $this->opt['header']['CONTENT-TYPE'];
	if (is_array($ct)) {
		throw new Exception('Content-Type is array', print_r($ct, true));
	}

	if ($ct == 'application/xml') {
		$data = XML::fromMap($data);
	}
	else if ($ct == 'application/json') {
		$data = JSON::encode($data); 
	}
	else if ($ct == 'application/x-www-form-urlencoded') {
		$data = http_build_query($data);
	}
	else if ($ct == 'multipart/form-data') {
		// convert "@file" to curl file object - this was not necessary prior to php7
		foreach ($data as $key => $value) {
			if (is_string($value) && mb_substr($value, 0, 1) == '@') {
				$file = mb_substr($value, 1);
				File::exists($file, true);
				$data[$key] = new \CURLFile($file); 
			}
		}

		$options['POST'] = true;
		unset($options['CUSTOMREQUEST']);
	}
	else {
		throw new Exception('invalid content type for array data', print_r($this->opt['header'], true));
	}

	return $data;
}


/**
 * Run api test(s). Load method.input.json save result as method.status.json and compare with
 * method.ok.json. Input and ok files content is vector and last element is configuration. 
 * Configuration parameter:
 *
 * - url: required 
 * - test_num: default = 0
 * - input_json: required, input file (get|post|put|delete).input.json
 * - input_map: map - replace :TAG: with value (e.g. "header": { "Authorization": "Bearer !OAUTH2_TOKEN!" }
 */
public function test(array $config = []) {

	if (empty($config['url'])) {
		throw new Exception('url missing');
	}

	if (empty($config['input_json']) || !File::exists($config['input_json'])) {
		throw new Exception('invalid input file');
	}

	$test_num = empty($config['test_num']) ? 0 : inval($config['test_num']);
	$input_map = empty($config['input_map']) ? [] : $config['input_map']; 
	list ($input, $input_opt) = $this->loadDataOptions($config['input_json'], $test_num, $input_map); 

	$required = [ 'method', 'path' ];
	foreach ($required as $key) {
		if (empty($input_opt[$key])) {
			throw new Exception('invalid input file: '.$key.' missing');
		}
	}
	
	$api = new APICall([ 'url' => $config['url'] ]);
	$api->set('method', $input_opt['method']);
	$api->set('uri', $input_opt['path']);

	if (isset($input_opt['header'])) {
		$api->set('header', $input_opt['header']);
	}

	if (!$api->exec($input)) {
		if (empty($input_opt[$api->status.'_ok'])) {
			throw new Exception('API test '.$api->get('method').':'.$api->get('uri').' failed: '.$api->status, 
				'FILE: '.$config['input_json']."\nDUMP: ".$api->dump."\nRESULT: ".print_r($api->result, true)."\n\n");
		}
	}

	$base = dirname($config['input_json']).'/'.$input_opt['method'].'.';
  $output_json = $base.$api->status.'.json';

	if (!empty($input_opt['export_tags'])) {
		$this->exportTags($api->result, $input_opt['export_tags']);
	}

	if (!empty($input_opt['result_md5'])) {
		print $api->get('method').':'.$api->get('uri').'='.$api->status.', check md5 ... ';

		if (md5($api->result) == $input_opt['result_md5']) {
	  	print "ok\n";
		}
		else {
			$save_as = $base.$api->status.'result';
			File::save($save_as, $api->result);
			throw new Exception('md5 mismatch: md5('.$save_as.') != '.$input_opt['result_md5']);
		}
	}
	else {
		$compare_json = File::exists($base.$api->status.'.ok.json') ? $base.$api->status.'.ok.json' : $base.'ok.json';
		print $api->get('method').':'.$api->get('uri').'='.$api->status.', compare '.$output_json.' with '.basename($compare_json).' ... ';
		File::save($output_json, JSON::encode($api->result));

		list ($result_ok, $compare_opt) = $this->loadDataOptions($compare_json, $test_num, $input_map); 
		$ignore = $this->compare_result($api->result, $result_ok, $compare_opt);

		if (count($ignore) == 0) {
	  	print "ok\n";
		}
		else {
			print "\n".join("\n", $ignore)."\nResult ok\n";
		}

  	File::remove($output_json);
	}
}


/**
 * Export tag map.
 */
private function exportTags(array $data, array $tag_list) : array {
	$tags = [];

	foreach ($tag_list as $key => $tl_value) {
		$prefix = '';

		if (($pos = strpos($key, ':')) !== false) {
			$prefix = substr($key, $pos + 1);
			$key = substr($key, 0, $pos);
		}

		if (is_array($tl_value)) {
			foreach ($tl_value as $lkey) {
				$tags[$prefix.$lkey] = self::getMapPathValue($data, $key.'.'.$lkey);
			}
		}
		else {
			$tags[$prefix.$key] = $data[$key];
		}
	}

	foreach ($tags as $key => $value) {
		$key = strtoupper($key);
		$this->opt['tags'][$key] = $value;
	}
}

	
/**
 * Compare $curr with $ok. Ignore keys set in $opt['dont_compare'].
 * Return ignore message list. 
 */
private function compare_result(array $curr, array $ok, array $opt) : array {

	if (!isset($opt['key'])) {
    $opt['key'] = [];
  }

	if (!isset($opt['ignore'])) {
    $opt['ignore'] = [];
  }

	if (!is_array($curr) || !is_array($ok)) {
		if ($ok === "NULL") {
			$ok = null;
		}
	
		if ($curr !== $ok) {
			$key = array_pop($opt['key']);
			$path = join('.', $opt['key']);
 
			if (isset($opt['dont_compare']) && empty($path) && !empty($opt['dont_compare'][$key])) {
        array_push($opt['ignore'], "ignore $key: $curr != $ok");
			}
      else if (isset($opt['dont_compare']) && isset($opt['dont_compare'][$path]) && in_array($key, $opt['dont_compare'][$path])) {
        array_push($opt['ignore'], "ignore $path.$key: $curr != $ok");
			}
			else {
				throw new Exception('comparison failed', "$curr !== $ok (path=$path, key=$key)");
			}
		}
	}
	else {
		foreach ($ok as $key => $value) {
			if (isset($curr[$key])) {
				array_push($opt['key'], $key);
				$opt['ignore'] = $this->compare_result($curr[$key], $value, $opt);
				array_pop($opt['key']);
			}
			else if (!array_key_exists($key, $curr) || !is_null($value)) {
				throw new Exception('missing parameter', "$key = $value");
			}
		}
	}

	return $opt['ignore'];
}


/**
 * Return data (json[0]) and options (json[last]). Apply replace (!TAG!).
 */
private function loadDataOptions(string $file, int $pos = 0, array $replace = []) : array {
	$json_str = File::load($file);
	$json_orig = JSON::decode($json_str);
	$rtags = [];

	foreach ($replace as $key => $value) {
		if (mb_strpos($json_str, '!'.$key.'!') !== false) {
			$json_str = str_replace('!'.$key.'!', $value, $json_str);
			$rtags[$key] = $value;
		}
	}

	foreach ($this->opt['tags'] as $key => $value) {
		if (mb_strpos($json_str, '!'.$key.'!') !== false) {
			$json_str = str_replace('!'.$key.'!', $value, $json_str);
			$rtags[$key] = $value;
		}
	}

	$json = JSON::decode($json_str);

	if (!is_array($json) || !isset($json[$pos]) || $pos >= count($json) - 1) {
		throw new Exception("invalid json file $file (check test[$pos] and options");
	}

	$data = $json[$pos];
	$options = array_pop($json);

	if (count($rtags) > 0) {
		$last = count($json_orig) - 1;
		$update = false;

		if (!isset($json_orig[$last]['tags'])) {
			$json_orig[$last]['tags'] = $rtags;
			$update = true;
		}
		else {
			foreach ($rtags as $key => $value) {
				// only add new tags - ignore value changes
				if (!isset($json_orig[$last]['tags'][$key])) {
					$json_orig[$last]['tags'][$key] = $value;
					$update = true;
				}
			}
		}

		if ($update) {
			File::save($file, JSON::encode($json_orig));
		}
	}

	if (mb_strpos($json_str, '"!') !== false && mb_strpos($json_str, '!"') !== false && isset($options['tags'])) {
		// this->opt['tags'] not set
		foreach ($options['tags'] as $key => $value) {
			if (mb_strpos($json_str, '!'.$key.'!') !== false) {
				$json_str = str_replace('!'.$key.'!', $value, $json_str);
			}
		}

		$json = JSON::decode($json_str);
		$data = $json[$pos];
	}

	return [ $data, $options ];
}


}

