<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/APICall.class.php');
require_once(__DIR__.'/JSON.class.php');
require_once(__DIR__.'/lib/execute.php');


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
 * Constructor. Option $opt parameter:
 * 
 * - php_file: default = $_SERVER[0]
 * - config_file: default = File::basename($_SERVER[0]).'.config.json'
 * - swagger_file: default = File::basename($_SERVER[0]).'.swagger.json'
 * - swagger_bin: default = ./vendor/bin/swagger
 */
public function __construct(array $opt = []) {
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
 * Return value of key if found in text. If required and not found or empty throw exception.
 * If not required and not found return empty string. Example:
 *
 * key="value" or key={"v1", "v2", ...} 
 */
private function get_value(string $key, string $text, bool $required = true) {

	if (preg_match('/^[ \*]*'.$key.'\=(.+),?$/', $text, $match)) {
		$res = $this->get_vector($match[1]);

		if ((is_array($res) && count($res) == 0 && $required) || (is_string($res) && mb_strlen($res) == 0)) {
			throw new Exception('empty value', "key=$key text=$text");
		}

		return $res;
	}
	else if ($required) {
		throw new Exception('missing value', "key=$key text=$text");
	}

	return '';
}


/**
 * If $txt != {.*} return $txt. Otherwise parse vector. Return string|vector.
 */
private function get_vector(string $txt) {

	$txt = trim($txt);
	if (mb_substr($txt, -1) == ',') {
		$txt = mb_substr($txt, 0, -1);
	}

	$first_char = mb_substr($txt, 0, 1);
	$last_char = mb_substr($txt, -1);

	if ($first_char === '{' && $last_char !== '}') {
		throw new Exception("invalid multiline entry", $txt);
	}

	if ($first_char === '"' && $last_char !== '"') {
		throw new Exception("invalid multiline entry", $txt);
	}

	if ($first_char !== '{' && $last_char !== '}') {
		if ($first_char === '"' && $last_char === '"') {
			$txt = mb_substr($txt, 1, -1);
		}

		return $txt;
	}

	$txt = trim(mb_substr($txt, 1, -1));
	$res = [];
	$n = 0;

	while ($n < 20 && preg_match('/^"(.*?)"[, ]*(.*)$/', $txt, $match)) {
		array_push($res, $match[1]);
		$txt = $match[2];
		$n++;
	}

	if ($n == 20 || mb_strlen($txt) > 0) {
		throw new Exception("vector scan failed", "n=$n txt=[$txt] res=".print_r($res, true));
	}

	return $res;
}


/**
 * Scan $line for "$name(... KEY="VALUE" ...). Return false or map of key value pairs.
 */
private function get_map(string $line, string $name) {
  $map = false;

	if (($pos = mb_strpos($line, '@SWG\\'.$name.'(')) === false) {
		return $map;
	}

	$line = trim(mb_substr($line, $pos + 5));
	$rxs = '/^'.$name.'\(([a-zA-Z0-9]+?)\=';
	$rxe = '[, ]*(.*)\),?$/';
	$n = 0;

	$schema_def = '@SWG\Schema(ref="#/definitions/';
	$sdl = mb_strlen($schema_def);

	if (($pos = mb_strpos($line, $schema_def)) !== false && ($pos2 = mb_strpos($line, '")', $pos + $sdl)) !== false) {
		$line = mb_substr($line, 0, $pos).'schema="'.mb_substr($line, $pos + $sdl, $pos2 - $pos - $sdl).'"'.mb_substr($line, $pos2 + 2);
	}

  while ((preg_match($rxs.'"(.*?)"'.$rxe, $line, $match) || preg_match($rxs.'(\{.+?\})'.$rxe, $line, $match) ||
				preg_match($rxs.'([a-zA-Z0-9\.\+\-]+)'.$rxe, $line, $match)) && $n < 20) {
    if ($map === false) {
      $map = [];
    }

		$map[$match[1]] = $this->get_vector($match[2]);

		$line = $name.'('.$match[3].')';
		$n++;
  }

	if ($n == 20 || $line !== $name.'()') {
		throw new Exception("map scan failed", "name=$name line=[$line] map=".print_r($map, true));
	}

	return $map;
}


/**
 * If "=@SWGCustom:".parser[custom] is found, set config[parser.name][parser.custom] = parser.json. 
 */
private function _scan_custom() : void {
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
 */
private function _parser_set_name(string $name) : void {
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
 * Set global swagger paramter.
 */
private function _set_swagger(array $p) : void {
	$required = [ 'host', 'schemes', 'consumes', 'produces' ];
	foreach ($required as $key) {
		if (empty($p[$key])) {
			throw new Exception('missing or empty parameter', "key=$key");
		}
	}

	$host = $p['host'];
	$schemes = $p['schemes'];
	$prefix = isset($p['basePath']) ? $p['basePath'] : '';

	$this->config['@api'] = [ 'header' => [], 'url' => $schemes[0].'://'.$host.$prefix ];
	$this->config['@api']['header']['Accept'] = $p['consumes'];
	$this->config['@api']['header']['Content-Type'] = $p['produces'];
}


/**
 * Scan for body, header and path parameter.
 */
private function _scan_swg(string $line) : void {

	$name = $this->parser['name'];

	if (($map = $this->get_map($line, 'Property')) !== false) {
		if (!empty($map['property']) && isset($map['default'])) {	
			$this->config[$name]['example']['body'][$map['property']] = $map['default'];
		}
	}
	else if (($map = $this->get_map($line, 'Parameter')) !== false) {
		if (!empty($map['in']) && !empty($map['name']) && isset($map['default'])) {
			$this->config[$name]['example'][$map['in']][$map['name']] = $map['default'];
		}
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
public function updateConfigFile() : void {

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
		else if (($map = $this->get_map($line, 'Swagger')) !== false) {
			$this->_set_swagger($map);
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
 */
private function _check_config(string $name) : void {

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
 * Return header map {$hname: value}. If $name[example] header is not set use $name[@api] header.
 */
private function _get_header(string $name, string $hname) : array {
	$value = '';

	if (!empty($this->config[$name]['example']['header'][$hname])) {
		$value = $this->config[$name]['example']['header'][$hname];
	}
	else if (!empty($config['@api']['header'][$hname])) {
		$value = $this->config['@api']['header'][$hname];
	}
	else if (in_array($hname, $this->config[$name]['input_check']['required'])) {
		throw new Exception('no such header', "name=$name header=$hname");
	}

	if (is_array($value) && count($value) == 1) {
		$value = $value[0];
	}

	return [ $name => $value ];
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
 * Return string|hash.
 */
public function call(string $name, int $example = 0) {

	$this->_check_config($name);
	$cx = $this->config[$name];

	$api = new \rkphplib\APICall([ 'url' => $this->config['@api']['url'] ]);
	$path = $cx['call']['path'];
	$data = $cx['example'];

	if ($example > 0) {
		$ekey = 'example'.$example;

		foreach ($cx[$ekey]['body'] as $key => $value) {
			$data['body'][$key] = $value;
		}
	}

	$use_header = array_merge([ 'Content-Type', 'Accept' ], array_keys($data['header']));
	foreach ($use_header as $hkey) {
		$api->set('header', $this->_get_header($name, $hkey));
	}

	if (isset($data['path'])) {
		while (preg_match('/\{(.+?)\}/', $path, $match)) {
			$key = $match[1];

			if (empty($data['path'][$key])) {
				throw new Exception('invalid configuration', "empty value path=$path key=$key dump: ".print_r($data, true));
			}

			$path = str_replace('{'.$key.'}', $data['path'][$key], $path);
		}
	}

	$api->set('method', $cx['call']['method']);
	$api->set('uri', $path);

	try {
		if (!$api->exec($data['body'])) {
			throw new Exception('api call failed', "path=$path status=".$api->status." dump:\n".$api->dump."\nresult: ".print_r($api->result, true));
		}
	}
	catch (\Exception $e) {
		throw new Exception('api call failed - '.$e->getMessage(), "path=$path status=".$api->status." dump:\n".$api->dump."\nresult: ".print_r($api->result, true));
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
 * Check output $out (string|hash) according to $check. Return error message.
 */
private function _check_output($out, array $check) : string {

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
public function updateSwaggerFile() : void {
	print "\nrecreate ".$this->options['swagger_file']."\n\n";

	if (File::exists($this->options['swagger_bin'])) {
		\rkphplib\lib\execute($this->options['swagger_bin']." '".$this->options['php_file']."' --output '".$this->options['swagger_file']."'");
	}
	else {
		throw new Exception('swagger not found', 'missing '.$this->options['swagger_bin']);
	}
}


}


