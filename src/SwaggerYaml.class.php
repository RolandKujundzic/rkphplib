<?php

namespace rkphplib;

require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/Dir.class.php');
require_once(__DIR__.'/YAML.class.php');
require_once(__DIR__.'/lib/split_str.php');

use \rkphplib\Exception;


/**
 * Create swagger yaml documentation. Every route needs "@api" Annotation. 
 * Annotation Parameter:
 * 
 * - route GET:/action = method=GET, endpoint=/action (optional if options.route_rx is used)
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
 * - api_param: @api_param $name, $type, $required (1|0), $in (body|path|header), $default, $desc
 * 
 * @see YAML
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class SwaggerYaml {

/**
 * @var map $data
 */
public $data = [ 'parameters' => [], 'paths' => [] ];

/** @var map $path_param - avoid adding same path with different parameter names */
private $path_param = [ ];

/** @var map $param - avoid adding same parameter */
private $param = [];

/** @var map $options */
private $options = [];

/** @var string $api_call - current api call */
private $api_call = '';

/** @var string $last_api_call - previous api call */
private $last_api_call = '';


/**
 * Check parameter map. Add to param.
 *
 * @throws
 * @param string $pname
 * @param map $info
 */
private function checkParameter($pname, $info) {
	$keys = [ 'in', 'name', 'description', 'required', 'type', 'default', 'enum' ];
	$required = [ 'in', 'name', 'type', 'required' ]; 
	$allow_type = [ 'string', 'integer', 'file' ];

	foreach ($info as $key => $value) {
		if (!in_array($key, $keys)) {
			throw new Exception('unkown parameter key', $key);
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

	$this->log("Define parameters.$pname", 3);
	$this->data['parameters'][$pname] = $info;
}


/**
 * Extract parameter names from path. Update path_param index. Return parameter list.
 * 
 * @return vector 
 */
private function pathParameter($path) {
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
 *
 * @param string $method
 * @param string $path
 * @param vector<string> $api
 */
private function addPath($method, $path, $api) {

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

	$api_info = $this->apiInfo($api);
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
 *
 * @param vector $api
 * @return map
 */
private function apiInfo($api) {
	$info = [];

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
			$this->addNewParameter($info, \rkphplib\lib\split_str(',', substr($value, 6)));
		}
	}

	if (!isset($info['description']) && !empty($info['summary'])) {
		$info['description'] = $info['summary'].'.';
	}

	return $info;
}


/**
 * Add new parameter to info (and data[parameters]). 
 * 
 * @param map-reference &$info
 * @param vector $pinfo (name, type, required, in, default, desc, extra)
 */
private function addNewParameter(&$info, $pinfo) {
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
 * Convert parameter vector (name, type, required, in, default, description, extra) to map.
 * 
 * @param vector $pinfo
 * @return map
 */
private static function param2map($pinfo) {

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

	return $res;
}


/**
 * Add existing parameter to info (and data[parameters]).
 *
 * @param map-reference &$info
 * @param string $name
 * @param string $in
 */
private function addExistingParameter(&$info, $name, $in) {

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
 *
 * @param map-reference &$info
 * @param vector $pinfo
 */
private function addToBody(&$info, $pinfo) {

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
 *
 * @param string $message
 * @param string $level
 */
private function log($message, $level) {
	if ($level <= $this->options['log_level']) {
		print $message."\n";
	}
}


/**
 * Return paths.$path.$method map. Use data.paths.__default.post as template:
 *
 * @throws if data[paths][__default][post][parameters] is not array
 * @param vector $parameter
 * @return map
 */
private function getMethod($parameter) {

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
 *
 * @param map $prefix_tag [ '/path' => Tag, ... ]
 */
private function setTags($prefix_tag) {
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
 * Scan $file for @api-route or options.route_rx (after "@api") and call "$this->addPath($method, $path)".
 *
 * @throws 
 * @param string $file 
 */
private function scan($file) {
	$lines = File::loadLines($file);
	$is_comment = false; // true = we are inside multiline comment block
	$route_rx = empty($this->options['route_rx']) ? '' : $this->options['route_rx'];
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
		else if (!$is_comment && preg_match('/^\/\/\s*@api\s+(.+)$/', $line, $match)) {
			$api = lib\split_str(',', trim($match[1]));
			$set_api = true;
		}
		else if (!$is_comment && count($api) > 0 && preg_match('/^\/\/\s*@api_(.+?)\s+(.+)$/', $line, $match)) {
			// @api_[desc|summary|produces|consumes|param]
			array_push($api, $match[1].': '.trim($match[2]));

			if ($match[1] == 'exit') {
				print_r($this->data);
				exit(1);
			}

			$set_api = true;
		}
		else if (!$is_comment && $route_rx && preg_match($route_rx, $line, $match)) {
			$method = strtolower($match[1]);
			$path = substr($match[2], 1, -1);

			if (count($api) === 0) {
				throw new Exception('@api tag missing in previous line', "method=$method path=$path line=$line");
			}

			$this->addPath($method, $path, $api);
			$api = [];
		}

		if (!$set_api && count($api) > 0) {
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
 * Update options.save_yaml. Load options.load_yaml as template. Scan options.scan_files and add options.tags.
 */
public function update() {

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

	YAML::save($this->options['save_yaml'], $this->data);
}


/**
 * Constructor. Set Options:
 *
 * - load_yaml = path to yaml template
 * - save_yaml = path to yaml output
 * - route_rx = regular expression for route parsing (catch: method, path) - default = ''
 * - scan_files = list of (php)files with @api tags
 * - tags = map with prefix => tag 
 * - log_level = 1 (1,2,3)
 *
 * @param map $options = = [ 'log_level' => 1 ]
 */
public function __construct($options = [ 'log_level' => 1 ]) {
	$this->options = [ 
		'load_yaml' => '!',
		'save_yaml' => '!',
		'route_rx' => '',
		'scan_files' => [],
		'tags' => [],
		'log_level' => 1 
	];

	foreach ($this->options as $key => $value) {
		if (!empty($options[$key])) {
			$this->options[$key] = $options[$key];
		}
		else if ($value == '!') {
			throw new Exception('missing option '.$key);
		}
	}
}


}


