<?php

namespace rkphplib;

require_once __DIR__.'/File.php';
require_once __DIR__.'/Dir.php';
require_once __DIR__.'/YAML.php';
require_once __DIR__.'/JSON.php';
require_once __DIR__.'/lib/split_str.php';

use function rkphplib\lib\split_str;



/**
 * Create swagger yaml documentation. Every route needs "@api" Annotation. 
 * Annotation Parameter:
 * 
 * - route GET:/action = method=GET, endpoint=/action (optional if options.route_rx captures file path,
 * 		required if route_rx does not capture path/to/endpoint but e.g. pathToEndpoint)
 * - route_rx_check = required for multiline block (1 = default) - use camel_case if "function methodPathToEndpoint(...)"
 * - version 1.0 = available since version 1.0
 * - internal = use only in "internal" documentation
 * - ignore = don't use in documentation
 * - prefer GET:/other/action = prefer this API call
 * - deprecated = API call is deprecated
 * - param = e.g. param $user_id:body (@api_param user_id, ... must occure before)
 *  
 * Optional API-Annotations are "@api_desc", "@api_param".
 *
 * - api_desc: @api_desc [description]
 * - api_summary: @api_summary [summary]
 * - api_consumes: e.g. multipart/form-data
 * - api_produces: e.g. image/jpeg
 * - api_param: @api_param $name, $type, $required (1|0), $in (body|formData|path|header|query), $default, $desc
 * - api_example: @api_example name=value[, name2=value2, ...]
 * 
 * @see http://swagger.io/specification/ 
 * @see YAML
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class SwaggerYaml {

// @var hash $data
public $data = [ 'parameters' => [], 'paths' => [] ];

// @var hash $path_param - avoid adding same path with different parameter names
private $path_param = [ ];

// @var hash $param - avoid adding same parameter
private $param = [];

// @var hash $options
private $options = [];

// @var string $api_call - current api call
private $api_call = '';

// @var string $last_api_call - previous api call
private $last_api_call = '';


/**
 * Check parameter map. Add to param.
 */
private function checkParameter(string $pname, array $info) : void {
	$keys = [ 'in', 'name', 'description', 'required', 'type', 'default', 'enum', 'example' ];
	$required = [ 'in', 'name', 'type', 'required' ]; 
	# byte = base64 encoded characters, password = obscured input
	$allow_type = [ 'string', 'integer', 'long', 'double', 'byte', 'binary', 'boolean', 'date', 'dateTime', 'file', 'password' ];

	foreach ($info as $key => $value) {
		if (!in_array($key, $keys)) {
			throw new Exception('unkown parameter key', "key=$key value=$value info: ".print_r($info, true));
		}
	}

	foreach ($required as $key) {
		if (!isset($info[$key])) {
			throw new Exception('missing required parameter key', $key);
		}
	}

	if ($pname != $info['in'].'_'.$info['name']) {
		throw new Exception('invalid parameter key', "pname=$pname in=".$info['in'].' name='.$info['name']);
	}

	if (!in_array($info['type'], $allow_type)) {
		throw new Exception('invalid parameter type', $info['type']);
	}

	$info['required'] = !empty($info['required']); 
	$name = $info['name'];

	if (!isset($this->param[$name])) {
		$this->param[$name] = $info;
	}
	else {
		foreach ($this->param[$name] as $key => $value) {
			if ($key != 'in' && $info[$key] != $value) {
				throw new Exception('parameter has changed', "$name.$key: [$value] != [".$info[$key].']');
			}
		}
	}

	if (isset($this->data['parameters'][$pname])) {
		return;
	}

	if (isset($info['description']) && strlen($info['description']) == 0) {
		// remove empty description
		unset($info['description']);
	}

	if (isset($info['example']) && $info['type'] != 'object') {
		// example only allowed in object properties
		unset($info['example']);
	}

	$this->log("Define parameters.$pname", 3);
	$this->data['parameters'][$pname] = $info;
}


/**
 * Extract parameter names from path. Update path_param index. Return parameter list.
 */
private function pathParameter(string $path) : array {
	$parameter = [];

	if (substr($path, 0, 1) != '/') {
		$path = '/'.$path;
	}

	if (($pos = strpos($path, '/{')) > 0) {
		$plist = explode('/', substr($path, $pos + 1));
		$url = substr($path, 0, $pos);

		foreach ($plist as $value) {
			array_push($parameter, substr($value, 1, -1));
		}
	}

	if (count($parameter) > 0) {
		if (isset($this->path_param[$url]) && $this->path_param[$url][0] == count($parameter) &&
				$path != $this->path_param[$url][1]) {
			throw new Exception('Path parameter have changed', "$url: $path != ".$this->path_param[$url][1]);
		}

		$this->path_param[$url] = array_merge([ count($parameter), $path ], $parameter);
	}

	return $parameter;
}


/**
 * Add information for paths and parameters to this.data.
 *
 * data.parameters:
 *  path_$name:
 *   in: path
 *   name: $name
 *   required: true
 *   type: string
 */
private function addPath(string $method, string $path, array $api) : void {

	$method = strtolower($method);

	if (substr($path, 0, 1) != '/') {
		$path = '/'.$path;
	}

	if (in_array('internal', $api)) {
		$this->log("SKIP internal $method:$path", 1);
		return;
	}
	else if (in_array('ignore', $api)) {
		$this->log("SKIP ignore $method:$path", 1);
		return;
	}

	$this->api_call = preg_replace('/[^0-9a-zA-Z]/', '', $method.$path);

	if ($this->last_api_call && $this->last_api_call != $this->api_call) {
		$dkey = 'body_'.$this->last_api_call;

		if (isset($this->data['definitions'][$dkey]) && count($this->data['definitions'][$dkey]['required']) == 0) {
			unset($this->data['definitions'][$dkey]['required']);
		}
	}

	$this->last_api_call = $this->api_call;

	$api_info = $this->apiInfo($api, $path);
	$path_param = $this->pathParameter($path);

	if (!isset($this->data['paths'][$path])) {
		$this->data['paths'][$path] = [];
	}

	if (!isset($this->data['paths'][$path][$method])) {
		$this->log("Add $method:$path", 2);
		$info = $this->getMethod($path_param);

		if (isset($api_info['parameters'])) {
			foreach ($api_info['parameters'] as $ref) {
				if (!in_array($ref, $info['parameters'])) {
					array_push($info['parameters'], $ref);
				}
			}
			unset($api_info['parameters']);
		}

		if (isset($api_info['remove_param'])) {
			foreach ($api_info['remove_param'] as $ref) {
				for ($i = 0; $i < count($info['parameters']); $i++) {
					if (isset($info['parameters'][$i]['$ref']) && strpos($info['parameters'][$i]['$ref'], '/'.$ref) > 0) {
						array_splice($info['parameters'], $i, 1);
						break;
					}
				}
			}

			unset($api_info['remove_param']);
		}

		if (isset($api_info['remove_security'])) {
			$remove_security = $api_info['remove_security'];
			unset($api_info['remove_security']);

			foreach ($remove_security as $key) {
				$n = -1;
				for ($i = 0; $n == -1 && $i < count($info['security']); $i++) {
					if (isset($info['security'][$i][$key])) {
						$n = $i;
					}
				}

				if ($n != -1) {
					array_splice($info['security'], $n, 1);
				}
			}

			if (count($info['security']) == 0) {
				unset($info['security']);
			}
		}

		$this->data['paths'][$path][$method] = array_merge($info, $api_info);;
	}
}


/**
 * Parse @api[_xxx] information. Result map:
 *
 *  - description: text
 *  - summary: text
 *  - consumes: string
 *  - parameter: 
 */
private function apiInfo(array $api, string $path) : array {
	$info = [];
	$remove_param = [];
	$remove_security = [];

	foreach ($api as $value) {
		if (strpos($value, 'desc:') === 0) {
			$info['description'] = trim(substr($value, 5));
			if (substr($info['description'], -1) != '.') {
				throw new Exception('api description must end with "."', print_r($api, true));
			}
		}
		else if (strpos($value, 'summary:') === 0) {
			$info['summary'] = trim(substr($value, 8));
			if (substr($info['summary'], -1) == '.') {
				throw new Exception('api summary must not end with "."', print_r($api, true));
			}
		}
		else if (strpos($value, 'consumes:') === 0) {
			$info['consumes'] = explode(':', trim(substr($value, 9)));
		}
		else if (strpos($value, 'produces:') === 0) {
			$info['produces'] = explode(':', trim(substr($value, 9)));
		}
		else if (strpos($value, 'no_ref ') === 0) {
			array_push($remove_param, substr($value, 7));
		}
		else if (strpos($value, 'no_security ') === 0) {
			array_push($remove_security, substr($value, 12));
		}
		else if (strpos($value, 'param $') === 0) {
			$name = substr($value, 7);
			$in = '';

			if (($pos = strpos($name, ':')) !== false) {
				list ($name, $in) = explode(':', $name);
			}
			else {
				throw new Exception('invalid parameter variable', "value=$value");
			}

			if (!in_array($in, [ 'body', 'header', 'path', 'query', 'formData' ])) {
				throw new Exception('invalid parameter variable', "value=$value");
			}

			$this->addExistingParameter($info, $name, $in);
		}
		else if (strpos($value, 'param:') === 0) {
			$pval = trim(substr($value, 6));

			if (substr($pval, 0, 1) == '@') {
				$this->addParametersFromInput($info, $pval, $path);
			}
			else {
				$this->addNewParameter($info, split_str(',', $pval));
			}
		}
	}

	if (!isset($info['description']) && !empty($info['summary'])) {
		$info['description'] = $info['summary'].'.';
	}

	if (count($remove_param) > 0) {
		$info['remove_param'] = $remove_param;
	}

	if (count($remove_security) > 0) {
		$info['remove_security'] = $remove_security;
	}

	return $info;
}


/**
 * Add new parameter $pval (file:test_nr:in) to info (and data[parameters]). Load parameter list from input.json.  
 */
private function addParametersFromInput(array &$info, string $pval, string $path) : void {
	list ($file, $test_nr, $in) = explode(':', substr($pval, 1));

	if (!in_array($in, [ 'body', 'header', 'path', 'query', 'formData' ])) {
		throw new Exception('invalid parameter description', "pval=$pval");
	}

	if (empty($this->options['test_dir'])) {
		throw new Exception('options.test_dir is empty');
	}

	$data = $this->loadJSON($this->options['test_dir'].$path.'/'.$file, intval($test_nr));

	if (isset($data['header']) && is_array($data['header'])) {
		$header = $data['header'];
		unset($data['header']);
	}

	foreach ($data as $key => $value) {
		$tinfo = $this->getYamlType($value);
		$pinfo = [ $key, $tinfo['type'], 0, $in, '', '', 'example:'.$value ]; 
		$this->addNewParameter($info, $pinfo);
	}
}


/**
 * Return json from $file. Replace !TAG!.
 */
private function loadJSON(string $file, string $test_nr) : array {
	
	$json_str = File::load($file);
	$json = JSON::decode($json_str);
	$last = count($json) - 1;

	if (isset($json[$last]['tags'])) {
		foreach ($json[$last]['tags'] as $key => $value) {
			$json_str = str_replace('!'.$key.'!', $value, $json_str);
		}

		$json = JSON::decode($json_str);
	}

	if (!isset($json[$test_nr])) {
		throw new Exception('invalid json vector', "element [$test_nr] not set");
	}

	return $json[$test_nr];
}


/**
 * Add new parameter $pinfo (name, type, required, in, default, desc, extra) to info (and data[parameters]). 
 */
private function addNewParameter(array &$info, array $pinfo) : void {
	if (count($pinfo) < 5 || !in_array($pinfo[3], [ 'body', 'header', 'path', 'query', 'formData' ])) {
		throw new Exception('invalid parameter description', print_r($pinfo, true));
	}

	if ($pinfo[3] == 'body') {
		$this->addToBody($info, $pinfo);
		return;
	}

	if (!isset($info['parameters'])) {
		$info['parameters'] = [];
	}

	$name = $pinfo[0];
	$px = self::param2map($pinfo);

	$pname = $pinfo[3].'_'.$name;
	$this->checkParameter($pname, $px);
	array_push($info['parameters'], [ '$ref' => '#/parameters/'.$pname ]);
}


/**
 * Convert parameter vector (name, type, required, in, default, description, extra) to map. Extra examples:
 *
 *  - default:DEFAULT VALUE
 *  - enum:A:B:C:D
 *  - example:EXAMPLE, example:|\nMULTILINE\nEXAMPLE
 */
private static function param2map(array $pinfo) : array {

	$res = [ 
		'name' => $pinfo[0],
		'type' => $pinfo[1],
		'required' => !empty($pinfo[2]), 
		'in' => $pinfo[3]
	];

	if (count($pinfo) > 4 && strlen($pinfo[4]) > 0) {
		$res['default'] = $pinfo[4];
	}

	if (count($pinfo) > 5) {
		$res['description'] = $pinfo[5];
	}

	if (count($pinfo) > 6) {
		for ($i = 6; $i < count($pinfo); $i++) {
			if (($pos = strpos($pinfo[$i], 'default:')) === 0 || ($pos = strpos($pinfo[$i], 'example:')) === 0) {
				$tmp = split_str(':', $pinfo[$i], false, 2);
			}
			else {
				$tmp = split_str(':', $pinfo[$i]);
			}

			$key = array_shift($tmp);
			$res[$key] = (count($tmp) == 1) ? $tmp[0] : $tmp; 
		}
	}

	return $res;
}


/**
 * Add existing parameter to info (and data[parameters]).
 */
private function addExistingParameter(array &$info, string $name, string $in) : void {

	if (empty($in) || empty($name)) {
		throw new Exception('invalid parameter reference', "in=$in name=$name info: ".print_r($info, true));
	}

	if ($in == 'body') {
		$this->addToBody($info, [ $name ]);
		return;
	}

	if (!isset($this->param[$name])) {
		throw new Exception('parameter is not defined', $name);
	}

	if ($this->param[$name]['in'] != $in) {
		$tmp = $this->param[$name];
		$tmp['in'] = $in;
		$this->log('Define parameters'.$in.'_'.$name, 3);
		$this->data['parameters'][$in.'_'.$name] = $tmp;
	}

	if (!isset($info['parameters'])) {
		$info['parameters'] = [];
	}

	array_push($info['parameters'], [ '$ref' => '#/parameters/'.$in.'_'.$name ]); 
}


/**
 * Add parameter to body object.
 */
private function addToBody(array &$info, array $pinfo) : void {

	if (!isset($info['parameters'])) {
		$info['parameters'] = [];
	}

	if (!isset($this->data['definitions'])) {
		$this->data['definitions'] = [];
	}

	$dkey = 'body_'.$this->api_call;

	$pos = -1;
	for ($i = 0; $pos == -1 && $i < count($info['parameters']); $i++) {
		if ($info['parameters'][$i]['name'] == 'body' && $info['parameters'][$i]['in'] == 'body') {
			$pos = $i;
		}
	}

	if ($pos == -1) {
		$pos = count($info['parameters']);
		$schema = [ 'type' => 'object', 'required' => [], 'properties' => [] ];
		array_push($info['parameters'], [ 'name' => 'body', 'in' => 'body', 'required' => true ]);
		$info['parameters'][$pos]['schema'] = [ '$ref' => '#/definitions/'.$dkey ];
	}

	if (isset($this->data['definitions'][$dkey])) {
		$schema = $this->data['definitions'][$dkey];
	}

	if (count($pinfo) == 1) {
		$name = $pinfo[0];
		if (!isset($this->param[$name])) {
			throw new Exception('parameter is not defined', $name);
		}

		$px = $this->param[$name];
		$px['in'] = 'body';
	}
	else {
		$px = self::param2map($pinfo);
	}

	$name = $px['name'];

	unset($px['name']);
	unset($px['in']);

	if ($px['required']) {
		array_push($schema['required'], $name);
	}

	unset($px['required']);

	$schema['properties'][$name] = $px;
	$this->data['definitions'][$dkey] = $schema;
}


/**
 * Log message if $level < options.log_level.
 */
private function log(string $message, int $level) : void {
	if ($level <= $this->options['log_level']) {
		print $message."\n";
	}
}


/**
 * Return paths.$path.$method map. Use data.paths.__default.post as template.
 */
private function getMethod(array $parameter) : array {

	if (!isset($this->data['paths']) || 
			!isset($this->data['paths']['__default']) || 
			!isset($this->data['paths']['__default']['post']) || 
			!isset($this->data['paths']['__default']['post']['parameters']) || 
			!is_array($this->data['paths']['__default']['post']['parameters'])) {
		throw new Exception('missing data.paths.__default.post.parameters');
	}

	$info = $this->data['paths']['__default']['post'];

	foreach ($parameter as $name) {
		array_push($info['parameters'], [ '$ref' => '#/parameters/path_'.$name ]); 
	}

	return $info;
}


/**
 * Add tags to data.paths.path.method map.
 */
private function setTags(array $prefix_tag) : void {
	foreach ($this->data['paths'] as $path => $ignore) {
		foreach ($this->data['paths'][$path] as $method => $info) {
			$tags = [];

			if (isset($info['tags'])) {
				unset($info['tags']);
			}

			foreach ($prefix_tag as $prefix => $tname) {
				if (strpos($path, $prefix) === 0) {
					array_push($tags, $tname);
				}
			}

			if (count($tags) > 0) {
				if (!isset($info['tags'])) {
					$info['tags'] = [];
				}

				foreach ($tags as $tag) {
					if (!in_array($tag, $info['tags'])) {
						array_push($info['tags'], $tag);
					}
				}
			}

			$this->data['paths'][$path][$method] = $info;            
		}
	}
}


/**
 * Scan $file for @api-route or options.route_rx (after "@api") and call "$this->addPath($method, $path)". Either escape with // or *.
 */
private function scan(string $file) : void {
	$lines = File::loadLines($file);
	$is_comment = false; // true = we are inside multiline comment block
	$route_rx = empty($this->options['route_rx']) ? '' : $this->options['route_rx'];
	$rx_check = !empty($this->options['route_rx_check']);
	$api = [];

	foreach ($lines as $line) {
		$line = trim($line);
		$set_api = false;

		if (!$is_comment && substr($line, 0, 2) === '/*') {
			if (substr($line, -2) !== '*/') {
				$is_comment = true;
			}
		}
		else if ($is_comment && substr($line, 0, 2) === '*/') {
			$is_comment = false;
		}
		else if ((!$is_comment && preg_match('/^\/\/\s*@api\s+(.+)$/', $line, $match)) ||
						($is_comment && $rx_check && preg_match('/^\s*\*\s*@api\s+(.+)$/', $line, $match))) {
			$api = split_str(',', trim($match[1]));
			$set_api = true;
		}
		else if ((!$is_comment && count($api) > 0 && preg_match('/^\/\/\s*@api_(.+?)\s+(.+)$/', $line, $match)) ||
						($is_comment && $rx_check && count($api) > 0 && preg_match('/^\s*\*\s*@api_(.+?)\s+(.+)$/', $line, $match))) {
			// @api_[desc|summary|produces|consumes|param]
			array_push($api, $match[1].': '.trim($match[2]));

			if ($match[1] == 'exit') {
				print_r($this->data);
				exit(1);
			}

			$set_api = true;
		}
		else if (!$is_comment && $route_rx && preg_match($route_rx, $line, $match)) {
			$this->route_rx($match, $api);
			$api = [];
		}

		if (!$rx_check && !$set_api && count($api) > 0) {
			foreach ($api as $value) {
				if (preg_match('/^route\s+([A-Z]+)\:(.+)/', $value, $match)) {
					$this->addPath($match[1], trim($match[2]), $api);
				}
			}

			$api = [];
		}
	}
}


/**
 * Execute $this->addPath($method, $path, $api) if $match check is successfull.
 *
 * @param vector $match
 * @param map $api
 */
private function route_rx(array $match, array $api) : void {
	$rx_check = empty($this->options['route_rx_check']) ? '' : $this->options['route_rx_check'];
	$method = strtolower($match[1]);
	$path = $match[2];

	if ((substr($path, 0, 1) == "'" && substr($path, -1) == "'") ||
			(substr($path, 0, 1) == '"' && substr($path, -1) == '"')) {
		$path = substr($path, 1, -1);
	}

	if (!empty($rx_check)) {
		$old = [];

		foreach ($api as $value) {
			if (preg_match('/^route\s+([A-Z]+)\:(.+)/', $value, $match)) {
				$old = [ $method, $path ];
				$method = strtolower($match[1]);
				$path = trim($match[2]);
			}
		}

		if (count($old) != 2) {
			throw new Exception('@api route is missing', "method=$method path=$path");
		}

		if ($rx_check == 'camel_case') {
			// sanity check 
			if ($method != $old[0]) {
				throw new Exception('@api function name method mismatch', "method=$method path=$path route_rx: ".print_r($old, true));
			}

			// route GET:/user/{id}: /user/{id} use /user 
			if (($pos = strpos($old[2], '/{')) !== false) {
				$old[2] = substr($old[2], 0, $pos);
			}

			if ($path != join('', explode('/', ucwords($old[2], '/')))) {
				throw new Exception('@api function name path mismatch', "method=$method path=$path route_rx: ".print_r($old, true));
			}
		}
	}

	if (count($api) === 0) {
		throw new Exception('@api tag missing in previous line', "method=$method path=$path line=$line");
	}

	$this->addPath($method, $path, $api);
}


/**
 * Update options.save_yaml. Load options.load_yaml as template. Scan options.scan_files and add options.tags.
 */
public function update() : void {

	$required_opt = [ 'load_yaml', 'save_yaml' ];
	foreach ($required_opt as $key) {
		if (empty($this->options[$key])) {
			throw new Exception('missing option '.$key);
		}
	}

	$this->data = YAML::load($this->options['load_yaml']);

	if (!is_array($this->data) || empty($this->data['swagger']) || empty($this->data['info']) ||
			!is_array($this->data['info']) || empty($this->data['info']['title']) || empty($this->data['info']['description'])) {
		throw new Exception('invalid swagger yaml file', $this->options['load_yaml']);
	}

	foreach ($this->data['paths'] as $path => $ignore) {
		$this->pathParameter($path);
	}

	foreach ($this->data['parameters'] as $pname => $info) {
		$this->checkParameter($pname, $info);
	}

	foreach ($this->options['scan_files'] as $file) {
		$this->scan($file);
	}

	if (count($this->options['tags']) > 0) {
		$this->setTags($this->options['tags']);
	}

	unset($this->data['paths']['__default']);

	if (!empty($this->options['test_dir'])) {
		$this->addResponses($this->options['test_dir']);
	}

	YAML::save($this->options['save_yaml'], $this->data);
}


/**
 * Return yaml type of $any.
 */
private function getYamlType($any) : array {
	$type = '';

	if (is_null($any)) {
		$type = [ 'type' => 'string' ];
	}
	else if (is_bool($any)) {
		$type = [ 'type' => 'boolean' ];
	}
	else if (is_integer($any)) {
		$type = [ 'type' => 'integer', 'example' => $any ];
	}
	else if (is_float($any)) {
		$type = [ 'type' => 'float', 'example' => $any ];
	}
	else if (is_string($any)) {
		$type = [ 'type' => 'string', 'example' => $any ];
	}
	else if (is_array($any)) {
		$type = $this->isMap($any) ? [ 'type' => 'object', 'properties' => $this->getYamlArray($any, 'properties') ] : [ 'type' => 'array', 'items' => $this->getYamlArray($any, 'items') ]; 
	}
	else {
		throw new Exception('unmatched type', $key.': '.print_r($value, true));
	}

	return $type;
}


/**
 * Return yaml swagger array description ($type = items|properties).
 */
private function getYamlArray(array $arr, string $type) : array {
	$res = [];

	if ($type == 'properties') {
		foreach ($arr as $key => $value) {
			$res[$key] = $this->getYamlType($value);
		}
	}
	else if ($type == 'items') {
		// all array elements have same value
		$res = (count($arr) > 0) ? $this->getYamlType($arr[0]) : [ 'type' => 'object', 'properties' => [] ];
	}

	return $res;
}


/**
 * Return true if array is map.
 */
private function isMap(array $arr) : bool {
	if (array() === $arr) {
		return false;
	}

	return array_keys($arr) !== range(0, count($arr) - 1);
}


/**
 * Scan options.test_dir for path1/path2/method.200.json.
 */
private function addResponses(string $test_dir) : void {

	if (empty($this->options['test_dir'])) {
		return;
	}

	foreach ($this->data['paths'] as $path => $pinfo) {
		if (!Dir::exists($test_dir.$path)) {
			continue;
		}

		foreach ($pinfo as $method => $info) {
			foreach ($info['responses'] as $status => $rinfo) {
				$status = intval($status);

				if ($status < 200 || $status > 200) {
					continue;
				}

				$ok_json = $test_dir.$path.'/'.$method.'.ok.json';
				if (File::exists($ok_json)) {
					$name = 'result'.$status.'_'.$method.str_replace('/', '_', $path);
					if (!isset($rinfo['schema'])) {
						$this->data['paths'][$path][$method]['responses'][$status]['schema'] = [ '$ref' => '#/definitions/'.$name ];
						if (!isset($this->data['definitions'][$name])) {
							if (isset($this->options['use_response'])) {
								$json = $this->loadJSON($ok_json, intval($this->options['use_response']));
							}
							else {
								$json = JSON::decode(File::load($ok_json));
							}

							$this->data['definitions'][$name] = $this->getYamlType($json);
						}
					}
				}
			}
		}
	}
}


/**
 * Constructor. Set Options:
 *
 * - load_yaml = path to yaml template
 * - save_yaml = path to yaml output
 * - route_rx = regular expression for route parsing (catch: method, path) - default = ''
 * - route_rx_check = 0|1 = default|camel_case (non-empty required if multiline comments are used)
 * - scan_files = list of (php)files with @api tags
 * - tags = map with prefix => tag 
 * - log_level = 0 (0, 1,2,3)
 * - test_dir: if set scan for methodPATH.[input|output].json
 * - use_response: default = 0 (set only if method.ok.json is multi response vector)
 */
public function __construct(array $options = [ 'log_level' => 1 ]) {
	$this->options = [ 
		'load_yaml' => '',
		'save_yaml' => '',
		'route_rx' => '',
		'route_rx_check' => '1',
		'scan_files' => [],
		'tags' => [],
		'log_level' => 0,
		'test_dir' => '',
		'use_response' => 0
	];

	foreach ($this->options as $key => $value) {
		if (isset($options[$key])) {
			$this->options[$key] = $options[$key];
		}
	}
}


}


