<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/YAML.class.php');
require_once(__DIR__.'/Dir.class.php');

use \rkphplib\Exception;


/**
 * Convert swagger yaml file to zircote swagger annotations.
 *
 * Install required symphony/yaml and zircote/swagger-php (via composer):
 *
 * shell> php composer.phar require symfony/yaml 
 * shell> php composer.phar require zircote/swagger-php
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class SwaggerHelper {

/** @var multi-map $sobj swagger object e.g. parsed yaml */
private $sobj = [];

/** @var map $swg - swg info, header, ... */
private $swg = [];

/** @var string $last_schema */
public $last_schema = '';



/**
 * Load yaml file.
 * 
 * @param string $yaml_file
 * @return multi-map
 */
public function loadYaml($yaml_file) {
	$this->sobj = YAML::load($yaml_file);
	return $this->sobj;
}


/**
 * Define 'info' or 'swagger'.
 *
 * @param string $name
 * @param string $value
 */
public function setSWG($name, $value) {
	$allow = [ 'info', 'swagger' ];

	if (!in_array($name, $allow)) {
		throw new Exception('unknown swg name', "name=$name");
	}

	$this->swg[$name] = $value;
}


/**
 * Return swagger annotations for all paths. Options:
 *
 * - save_in: directory_path (don't return annotations)
 * - save_as: file path (don't return annotations)
 * - code_header: prepend to php files created in save_as if set
 * 
 * @param map $options = [] 
 * @return map - keys are method_path
 */
public function getAnnotations($options = []) {

	if (count($this->sobj) == 0) {
		throw new Exception('no swagger object - call loadYaml first');
	}

	if (!empty($options['save_in'])) {
		Dir::create($options['save_in'], 0, true);	
	}

	$methods = [ 'post', 'get', 'put', 'delete' ];
	$path_doc = [];

	foreach ($this->sobj['paths'] as $path => $p) {
		foreach ($methods as $m) {
			if (isset($p[$m])) {
				if (!empty($options['save_in'])) {
					$code_header = empty($options['code_header']) ? '' : "include_once('".$options['code_header']."');\n\n";
					$file = $options['save_in'].'/'.$m.str_replace(['/', '{', '}'], ['@', '', ''], $path).'.php';
					$code = '<'."?php\n\n".$code_header.$this->getSWG()."/**\n".$this->parsePath($m, $path, $p[$m]).
						"\n */\n\$apiDT->call('".$this->last_schema."');\n";
					File::save($file, $code);
					$path_doc[$m.'_'.$path] = $file;
				}
				else {
					$path_doc[$m.'_'.$path] = $this->parsePath($m, $path, $p[$m]);
				}
			}
		}
	}

	return $path_doc;
}


/**
 * Return swg blocks. If name is empty merge all blocks.
 * Prepend [ * ] before every line. Add header and footer comment.
 *
 * @param string $name = ''
 * @return string
 */
private function getSWG($name = '') {
	$res = '';
		
	if ($name) {
		if (!isset($this->swg[$name])) {
			throw new Exception('no such swg block', "name=$name");
		}

		$res = $this->swg[$name];
	}
	else if (count($this->swg) > 0) {
		$res = join("\n\n", $this->swg);
	}
	else {
		return $res;
	}

	$lines = preg_split("/\r?\n/", $res);
	return "\n/**\n * ".join("\n * ", $lines)."\n */\n\n";
}


/**
 * Return [$name={"value1", ... , "valueN"}] (if $p[$name] is array) or [$name="$value"].
 *
 * @param map $p
 * @param string $name
 * @return string
 */
private function getKeyValue($p, $name) {
	$res = '';

	if (!isset($p[$name])) {
		return $res;
	}

	if ($name == 'schema') {
		print "ignore schema ...\n";
		return '';
	}

	if (is_array($p[$name])) {
		$arr = [];

		foreach ($p[$name] as $value) {
			if (!is_string($value)) {
				throw new Exception('unexpected array', "name=$name p: ".print_r($p, true));
			}

			$value = str_replace('"', '\"', trim($value));
			array_push($arr, $value);
		}

		$res = $name.'={"'.join('", "', $arr).'"}';
	}
	else {
		$res = $name.'="'.str_replace('"', '\"', trim($p[$name])).'"';
	}

	return $res;
}


/**
 * Return [schema_name, schema_swg].
 *
 * @param string $ref
 * @return vector<string,string>
 */
private function getSchema($ref) {
	$name = '';
	$swg = '';

	if (mb_substr($ref, 0, 14) == '#/definitions/') {
		$name = mb_substr($ref, 14);
		$swg = '@SWG\Schema(ref="#/definitions/'.$name.'")';
	}
	else {
		throw new Exception('unknown schema');
	}

	return [ $name, $swg ];
}


/**
 * Return parameter annotation vector. Last element of vector is 
 * [ required, schema ].
 * 
 * @param map $p
 * @return vector
 */
private function parseParameters($param_list) {
	$this->log(" parameters");

	$required = [];
	$schema = '';
	$out = [];

	for ($i = 0; $i < count($param_list); $i++) {
		$p = $param_list[$i];
		$kv = [];

		if ($p['in'] == 'body' && $p['name'] == 'body' && !empty($p['schema']) && !empty($p['schema']['$ref'])) {
			list ($schema, $swg_schema) = $this->getSchema($p['schema']['$ref']);
			array_push($kv, $swg_schema);
			unset($p['schema']);
		}
		else if (!isset($p['default'])) {
			$p['default'] = '';
		}

		if (!empty($p['required'])) {
			array_push($required, $p['name']);
		}

		foreach ($p as $key => $value) {
			array_push($kv, $this->getKeyValue($p, $key));	
		}

		array_push($out, '@SWG\Parameter('.join(', ', $kv).')');
	}

	array_push($out, [ $required, $schema ]);
	return $out;
} 


/**
 * Return vector with SWG\Response annotations.
 * Last vector element is schema list.
 * 
 * @param vector $p
 * @return vector
 */
private function parseResponses($p) {
	$this->log(" responses");

	$schema_list = [];
	$out = [];

	foreach ($p as $code => $info) {
		$kv = [];

		foreach ($info as $key => $ignore) {
			if ($key == 'schema') {
				list ($schema, $swg_schema) = $this->getSchema($info['schema']['$ref']);
				array_push($schema_list, $schema);		
				array_push($kv, $swg_schema);
				continue;
			}

			array_push($kv, $this->getKeyValue($info, $key));
		}

 		array_push($out, '@SWG\Response(response="'.$code.'", '.join(', ', $kv).')');
	}

	array_push($out, $schema_list);
	return $out;
}


/**
 * Parse path data. Return annotation for method+path.
 *
 * @param string $method
 * @param string $path
 * @param multi-map $p
 * @return string
 */
private function parsePath($method, $path, $p) {

	$this->log("$method:$path ...");
	$this->last_schema = '';

	$swg = [];

	array_push($swg, "@SWG\\$method(path=\"$path\"");
	array_push($swg, $this->getKeyValue($p, 'tags'));
	array_push($swg, $this->getKeyValue($p, 'summary'));
	array_push($swg, $this->getKeyValue($p, 'description'));
	array_push($swg, $this->getKeyValue($p, 'produces'));
	array_push($swg, $this->getKeyValue($p, 'consumes'));

	if (isset($p['parameters'])) {
		$out = $this->parseParameters($p['parameters']);
		list ($required, $schema) = array_pop($out);
		$swg = array_merge($swg, $out);
	}

	$input_check = '';
	if (count($required) > 0) {
		$input_check = " *\n".' * @SWGCustom:input_check= {'."\n * ".	'"required": ["'.join('", "', $required).'"]'."\n".
			' * }=@SWGCustom:input_check'."\n *\n";
	}

	$definition = '';
	$swg_call = '';

	if ($schema) {
		$definition = $this->parseDefinitions($schema);
		$swg_call = ' * @SWGCall="'.$schema.'"'."\n *\n";
		$this->last_schema = $schema;
	}

	if (isset($p['responses'])) {
		$resp = $this->parseResponses($p['responses']);
		$schema_list = array_pop($resp);
		$swg = array_merge($swg, $resp);
	}

	$resp_schema = '';
	foreach ($schema_list as $name) {
		$resp_schema .= $this->parseDefinitions($name);
	}

	$lines = preg_split("/\r?\n/", join(",\n", $swg));
	$res = $swg_call.$input_check." * ".join("\n * ", $lines)."\n * )".$definition.$resp_schema;

	$this->log(" ... done\n");
	return $res;
}


/**
 * Print log message.
 *
 * @param string $msg
 */
private function log($msg) {
	print "$msg";
}


/**
 * Return SWG\Definition annotations.
 *
 * @param string $name
 * @return string
 */
private function parseDefinitions($name) {
	$this->log(" definition:$name");

	if (!isset($this->sobj['definitions'][$name])) {
		throw new Exception('no such schema definition', "schema=$schema");
	}

	$p = $this->sobj['definitions'][$name];

	$swg = [ '@SWG\Definition(definition="'.$name.'"' ];
	array_push($swg, $this->getKeyValue($p, 'type'));
	array_push($swg, $this->getKeyValue($p, 'required'));

	$properties = [];
	if ($p['type'] == 'object') {
		$properties = $p['properties'];
	}
	else if ($p['type'] == 'array') {
		if (isset($p['items']) && isset($p['items']['properties'])) {
			$properties = $p['items']['properties'];
		}
		else {
			throw new Exception('ToDo: type=array');
		}
	}
	else {
		throw new Exception('ToDo: type='.$p['type']);
	}

	foreach ($properties as $pname => $prop) {
		$kv = [];

		if (!isset($prop['default'])) {
			$prop['default'] = '';
		}

		foreach ($prop as $prop_key => $igore) {
			array_push($kv, $this->getKeyValue($prop, $prop_key)); 
		}

		array_push($swg, '@SWG\Property(property="'.$pname.'", '.join(", ", $kv).')');
	}

	$lines = preg_split("/\r?\n/", join(",\n", $swg));
	$res = "\n *\n * ".join("\n * ", $lines)."\n * )";

	return $res;
}


}

