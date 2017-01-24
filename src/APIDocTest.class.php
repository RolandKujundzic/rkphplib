<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/APICall.class.php');
require_once(__DIR__.'/JSON.class.php');
require_once(__DIR__.'/lib/execute.php');

use \rkphplib\Exception;


/**
 * Use APIDocTest class to create swagger documentation and test.json file from source code.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class APIDocTest {

/** 
 * Configuration map for api testing.
 * 
 * @var map $config = [] */
protected $config = [];

/**
 * Options.
 *
 * @see __construct()
 * @var map $options = { php_file: $base.php, config_file: $base.config.json, swagger_file: $base.swagger.json }
 */
protected $options = [];

/**
 * @var map $parser = [ 'line' => '', 'custom' => '', 'json' => '', 'name' => '' ]
 */
private $parser = [ 'line' => '', 'custom' => '', 'json' => '', 'name' => '' ];



/**
 * Constructor. Option parameter:
 * 
 * - php_file: default = $_SERVER[0]
 * - config_file: default = File::basename($_SERVER[0]).'.config.json'
 * - swagger_file: default = File::basename($_SERVER[0]).'.swagger.json'
 * - swagger_bin: default = ./vendor/bin/swagger
 *
 * @param map $opt = [] 
 */
public function __construct($opt = []) {
	$base = File::basename($_SERVER['argv'][0], true);
	$dir = dirname($_SERVER['argv'][0]);

	$default = [];
	$default['php_file'] = $_SERVER['argv'][0];
	$default['config_file'] = $dir.'/'.$base.'.config.json';
	$default['swagger_file'] = $dir.'/'.$base.'.swagger.json';
	$default['swagger_bin'] = $dir.'/vendor/bin/swagger';

	$this->options = $opt;

	foreach ($default as $key => $value) {
		if (!isset($this->options[$key])) {
			$this->options[$key] = $value;
		}
	}
}


/**
 * Extract $value from [$name="$value"] or [$name={"$value"] in $text.
 * 
 * @throws
 * @param string $key
 * @param string $text
 * @param bool $required = true
 * @return string
 */
protected function get_value($key, $text, $required = true) {

	if (!$required && mb_strpos($text, $key) === false) {
		return '';
	}

	$rx = $required ? '.+' : '.*';
	if (!preg_match('/[\{\,]?\s*'.$key.'\=\{?"('.$rx.'?)"/', $text, $match)) {
		throw new Exception('could not find parameter value', "search [$key] in [$text]");
	}

	return $match[1];
}


/**
 * If "=@SWGCustom:".parser[custom] is found, set config[parser.name][parser.custom] = parser.json. 
 */
private function _scan_custom() {
	$line = $this->parser['line'];

	// remove leading *
	if (mb_substr($line, 0, 1) == '*') {
		$line = mb_substr($line, 1);
	}

	if (($pos = mb_strpos($line, '=@S'.'WGCustom:'.$this->parser['custom'])) !== false) {
		// end of custom block found 
		$this->parser['json'] .= mb_substr($line, 0, $pos);
		$this->config[$this->parser['name']][$this->parser['custom']] = JSON::decode($this->parser['json']);
		$this->parser['custom'] = '';
		$this->parser['json'] = '';
	}
	else {
		$this->parser['json'] .= $line;
	}
}


/**
 * Set parser block name.
 *
 * @param string $name
 */
private function _parser_set_name($name) {
	$this->parser['name']	= $name;
	
	if (!isset($this->config[$name])) {
		$this->config[$name] = [];
	}
			
	if (!isset($this->config[$name]['call'])) {
		$this->config[$name]['call'] = [];
	}

	if (!isset($this->config[$name]['example'])) {
		$this->config[$name]['example'] = [ 'header' => [], 'body' => [], 'path' => [] ];
	}
}


/**
 * Extract host, schema, prefix, consumes and produces from line.
 * 
 * @param string $line
 */
private function _scan_swagger($line) {
	$host = $this->get_value('host', $line);
	$schema = $this->get_value('schemes', $line);
	$prefix = $this->get_value('basePath', $line, false);

	if (empty($schema)) {
		throw new Exception('empty schema');
	}

	if (empty($host)) {
		throw new Exception('empty host');
	}

	$this->config['@api'] = [ 'header' => [], 'url' => $schema.'://'.$host.$prefix ];
	$this->config['@api']['header']['Accept'] = $this->get_value('consumes', $line);
	$this->config['@api']['header']['Content-Type'] = $this->get_value('produces', $line);
}


/**
 * Scan for body, header and path paraemter.
 *
 * @param string $line
 */
private function _scan_swg($line) {

	$name = $this->parser['name'];

	if (preg_match('/\*.+?Property\(property\="(.+?)".+?default\="(.+?)"/', $line, $match)) {
		$this->config[$name]['example']['body'][$match[1]] = $match[2];
	}
	else if (preg_match('/\*.+?Parameter\(in\="(.+?)".+?name\="(.+?)".+?default\="(.+?)"/', $line, $match)) {
		$this->config[$name]['example'][$match[1]][$match[2]] = $match[3];
	}
	else if (($val = $this->get_value('consumes', $line, false))) {
		$this->config[$name]['example']['header']['Accept'] = $val;
	}
	else if (($val = $this->get_value('produces', $line, false))) {
		$this->config[$name]['example']['header']['Content-Type'] = $val;
	}
	else if (preg_match('/\*.+?@SWG\\\(.+?)\(path\="(.+?)"/', $line, $match)) {
		$this->config[$name]['call']['method'] = strtoupper($match[1]);
		$this->config[$name]['call']['path'] = $match[2];
	}
}


/**
 * Update options.config_file. Extract configuration parameters from config.php_file documentation.
 */
public function updateConfigFile() {

	$this->config = File::exists($this->options['config_file']) ? JSON::decode(File::load($this->options['config_file'])) : [];

	$lines = File::loadLines($this->options['php_file']);;
	$this->parser = [ 'line' => '', 'name' => '', 'json' => '', 'custom' => '' ];

	foreach ($lines as $line) {
		$line = trim($line);
		$this->parser['line'] = $line;

		if ($this->parser['custom']) {
			$this->_scan_custom();
		}
		else if (preg_match('/\*\s+@S'.'WGCall="(.+?)"/', $line, $match)) {
			$this->_parser_set_name($match[1]);
		}
		else if (!$this->parser['custom'] && preg_match('/@S'.'WGCustom:(.+?)\=(.*)$/', $line, $match)) {
			$this->parser['custom'] = $match[1];
			$this->parser['json'] = $match[2];
		}
		else if (preg_match('/\*\s+@SWG\\\Swagger\((.+?)\)/', $line, $match)) {
			$this->_scan_swagger($match[1]);
		}
		else if ($this->parser['name']) {
			$this->_scan_swg($line);
		}
	}

	// save pretty printed configuration
	File::save($this->options['config_file'], JSON::encode($this->config));
}


/**
 * Check this.config and this.config[$name].
 *
 * @throws
 * @param string $name
 */
private function _check_config($name) {

	if (empty($name)) {
		throw new Exception('empty configuration name');
	}

	if (!isset($this->config[$name])) {
		throw new Exception('invalid configuration', "missing $name");
	}

	if (empty($this->config['@api']['url'])) {
		throw new Exception('empty url');
	}

	$cx = $this->config[$name];

	if (!isset($cx['call'])) {
		throw new Exception('invalid configuration', "missing $name.call");
	}

	if (empty($cx['call']['method'])) {
		throw new Exception('invalid configuration', "empty $name.call.method");
	}

	if (empty($cx['call']['path'])) {
		throw new Exception('invalid configuration', "missing $name.call.path");
	}

	if (!isset($cx['example'])) {
		throw new Exception('invalid configuration', "missing $name.example");
	}
}


/**
 * Execute api call with:
 *
 * - url: config['@api']['url']
 * - method: config[$name][call][method]
 * - path: config[$name][call][path]
 * - data: config[$name][example]
 *
 * If example number is set add config[$name]['example'.$example] to data.
 *  
 * @throws
 * @param string $name
 * @param int $example = 0
 * @return map|string
 */
public function call($name, $example = 0) {

	$this->_check_config($name);
	$cx = $this->config[$name];

	$api = new \rkphplib\APICall([ 'url' => $this->config['@api']['url'] ]);

	$use_header = [ 'Content-Type', 'Accept' ];
	foreach ($use_header as $hkey) {
		if (!empty($cx['example']['header'][$hkey])) {
			$api->set('header', [ $hkey => $cx['example']['header'][$hkey] ]);
		}
		else if ($config['@api']['header'][$hkey]) {
			$api->set('header', [ $hkey => $config['@api']['header'][$hkey] ]);
		}
	}

	$path = $cx['call']['path'];
	$data = $cx['example'];

	if ($example > 0) {
		throw new Exception('ToDo: merge examples');
	}

	if (isset($data['path'])) {
		while (preg_match('/\{(.+?)\}/', $path, $match)) {
			$key = $match[1];

			if (empty($data[$key])) {
				throw new Exception('invalid configuration', "could not get name=$name key=$key dump: ".print_r($data, true));
			}

			$path = str_replace('{'.$key.'}', $data[$key], $path);
		}
	}

	$api->set('method', $cx['call']['method']);
	$api->set('uri', $path);

	print $api->get('method').": ".$path." ... ";

	if (!$api->exec($data)) {
		throw new Exception('api call failed', "path=$path status=".$api->status." dump:\n".$api->dump);
	}

	$res = $api->result;

	if (isset($this->config[$name]['check_output'])) {
		if (($err = $this->_check_output($res, $this->config[$name]['check_output']))) {
			print "check failed - $err\n";
		}
		else {
			print "ok\n";
		}
	}
	else {
		print "done\n";
	}

	return $res;
}


/**
 * Check output $out according to $check. Return error message.
 *
 * @param map|string $out
 * @param map $check
 * @return string 
 */
private function _check_output($out, $check) {

	if (isset($check['required']) && count($check['required']) > 0) {
		foreach ($check['required'] as $n => $key) {
			if (empty($out[$key])) {
				return "missing $key";
			}
		}
	}

	if (isset($check['compare']) && count($check['compare']) > 0) {
		foreach ($check['compare'] as $key => $value) {
			if (!isset($res[$key])) {
				return "missing $key";
			}
			else if ($res[$key] != $value) {
				return "value of $key = ".$res[$key]." != $value";	
			}
		}
	}

	return '';
}


/**
 * Update swagger_file.
 */
public function updateSwaggerFile() {
	print "\nrecreate ".$this->options['swagger_file']."\n\n";

	if (File::exists($this->options['swagger_bin'])) {
		\rkphplib\lib\execute($this->options['swagger_bin']." '".$this->options['php_file']."' --output '".$this->options['swagger_file']."'");
	}
	else {
		throw new Exception('swagger not found', 'missing '.$this->options['swagger_bin']);
	}
}


}

