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

/** @var map $options */
private $options = [];



/**
 * Update path_param index of data.paths.
 */
private function indexPath() {
	foreach ($this->data['paths'] as $path => $ignore) {
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
			$this->path_param[$url] = [ count($parameter), $path ];
		}
	}
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
 */
private function addPath($method, $path) {
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

		if (isset($this->path_param[$url]) && $this->path_param[$url][0] == count($plist)) {
			if ($path != $this->path_param[$url][1]) {
				$this->log("Parameter Names have changed:\n  SRC: $path\n  API: ".$this->path_param[$url][1], 1);
			}

			return;
		}

		$this->path_param[$url] = count($plist);
	}

	if (!isset($this->data['paths'][$path])) {
		$this->data['paths'][$path] = [];
	}

	if (!isset($this->data['paths'][$path][$method])) {
		$this->log("Add $method:$path", 2);
		$this->data['paths'][$path][$method] = $this->getMethod($parameter);
	}

	foreach ($parameter as $name) {
		if (isset($this->data['parameters']['path_'.$name])) {
			continue;
		}

		$this->log("Define parameters.path_$name", 3);
		$this->data['parameters']['path_'.$name] = [ 'in' => 'path', 'name' => $name, 'required' => true, 'type' => 'string' ];
	}
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
		else if (!$is_comment && $route_rx && preg_match($route_rx, $line, $match)) {
			$method = strtolower($match[1]);
			$path = substr($match[2], 1, -1);

			if (count($api) === 0) {
				throw new Exception('@api tag missing in previous line', "method=$method path=$path line=$line");
			}

			if (in_array('internal', $api)) {
				$this->log("SKIP internal $method:$path", 1);
			}
			else if (in_array('ignore', $api)) {
				$this->log("SKIP ignore $method:$path", 1);
			}
			else {
				$this->addPath($method, $path);
			}
		}

		if (!$set_api && count($api) > 0) {
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

	$this->indexPath();

	foreach ($this->options['scan_files'] as $file) {
		$this->scan($file);
	}

	if (count($this->options['tags']) > 0) {
		$this->setTags($this->options['tags']);
	}

	unset($this->data['paths']['@default']);

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


