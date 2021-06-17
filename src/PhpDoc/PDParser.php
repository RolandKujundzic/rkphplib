<?php

namespace rkphplib\PhpDoc;

require_once __DIR__.'/../File.php';

use rkphplib\Exception;
use rkphplib\File;


/**
 * PHP Documentator Parser.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class PDParser {

// @var array[string]string $class
private $class = []; 

// @var array[string]string $main
private $main = []; 

// @var array[string]array $function
private $function = [];

// @var array[string]array $var
private $var = [];

// @var array[string]array $const
private $const = [];

// @var array[string]array $define
private $define = [];

// @var string $namespace
private $namespace = '';

// @var array $include
private $include = [];

// @var array $include_once
private $include_once = [];

// @var array $require
private $require = [];

// @var array $require_once
private $require_once = [];

// @var array $comment
private $comment = []; 

// @var array $use
private $use = [];


/** 
 * @var array[string]array $tag List of tag definitions (tag = [is_multiline, is_unique, split_rules ])
 */
private static $tag = [
	'author'    => [ 0, 0, [ '/^(.+) <(.+)>$/', 'name', 'email' ] ],
	'copyright' => [ 0, 0, [ '/^([0-9\-]+) (.+)$/', 'year', 'organisation' ] ],
	'throws'    => [ 0, 1, null ],
	'desc'      => [ 1, 0, null ],
	'example'   => [ 1, 0, null ],
	'see'       => [ 0, 0, [ '/^([a-zA-Z0-9\-\.\:\/>\(\)]+) ?(.*)$/', 'uri', 'desc' ] ],
	'return'    => [ 0, 1, [ '/^([a-zA-Z0-9_\|\[\]\:\\\]+) ?(.*?)$/', 'type', 'desc' ] ],
	'param'     => [ 0, 0, [ '/^([a-zA-Z0-9_\|\[\]\:\\\]+) \&?\$([a-zA-Z0-9_]+)(.*)$/', 'type', 'name', 'desc' ] ],
	'var'       => [ 0, 1, [ '/^([a-zA-Z0-9_\|\[\]\:\\\]+) \$([a-zA-Z0-9_]+)(.*)$/', 'type', 'name', 'desc' ] ],
	'const'     => [ 0, 1, [ '/^([a-zA-Z0-9_\|\[\]\:\\\]+) ([A-Z0-9_]+)(.*)$/', 'type', 'name', 'desc' ] ]
];

// @var array $code
private $code = [];

// @var int $line_num current line number in code (1, 2, ...)
private $line_num = 0;

// @var int $docblock line number after start marker (1, 2, ...)
private $doc_start = 0;

// @var int $doc_end line number before end marker (1, 2, ...)
private $doc_end = 0;

// @var string $last_type
private $last_type = '';



/**
 * Constructor. Parse php_file.
 *
 * @throws
 * @param string $php_file
 * @return array[string]array
 */
public function parse($php_file) {
	$this->code = File::loadLines($php_file);
	$this->parseCode();

	if (count($this->class) > 0) {
		$this->class['code_end'] = $this->getCodeEnd(count($this->code));

		$func_num = count($this->function);
		if ($func_num > 0 && !$this->function[$func_num - 1]['code_end']) {
			$this->function[$func_num - 1]['code_end'] = $this->getCodeEnd($this->class['code_end'] - 1); 
		}
	}

	$this->checkFunctions();

	$res = [];
	$res['class'] = $this->class; 
	$res['main'] = $this->main; 
	$res['function'] = $this->function;
	$res['var'] = $this->var;
	$res['const'] = $this->const;
	$res['define'] = $this->define;
	$res['namespace'] = $this->namespace;
	$res['include'] = $this->include;
	$res['include_once'] = $this->include_once;
	$res['require'] = $this->require;
	$res['require_once'] = $this->require_once;
	$res['comment'] = $this->comment; 
	$res['use'] = $this->use;

	return $res;
}


/**
 * Check if indent, and if "@throw" or "@return" are missing.
 *
 * @throws 
 */
private function checkFunctions() {
	$has_throw = false;
	$has_return = false;

	for ($i = 0; $i < count($this->function); $i++) {
		$info  = $this->function[$i];

		if ($info['code_end'] < $info['code_start']) {
			throw new Exception('invalid code end in function '.$info['name'], $info['code_start']."-".$info['code_end']);
		}

		for ($k = $info['code_start'] + 1; $k < $info['code_end'] - 1; $k++) {
			$line = $this->code[$k];

			if (!preg_match('/^[\t]+[\'"a-zA-Z0-9\$\}\)\/\\\]/', $line) && strlen(trim($line)) > 0) {
				throw new Exception('no tab indent in function '.$info['name'].' (line '.($k + 1).')', 
					'ord(line[0])='.ord(substr($line, 0, 1))." line: [$line]");
			}

			if (preg_match('/^[\t]+throw new /', $line) && !isset($info['throws'])) {
				throw new Exception('missing @throws in documentation of function '.$info['name']." (line ".$info['code_start'].')', $line);
			}

			if (preg_match('/^[\t]+return /', $line) && !isset($info['return'])) {
				throw new Exception('missing @return in documentation of function '.$info['name']." (line ".$info['code_start'].')', $line);
			}
		}
	}
}


/**
 * Parse code.
 */
private function parseCode() {
	$multiline_comment = 0;

	foreach ($this->code as $line) {
		$this->line_num++;
		$line = trim($line);

		if (($maxlen = mb_strlen($line)) > 120) {
			throw new Exception('line '.$this->line_num." is too long ($maxlen > 120)", $line);
		}
		else if ($line == '/*') {
			$multiline_comment = $this->line_num;
		}
		else if ($line == '*/') {
			if ($multiline_comment) {
				array_push($this->comment, $multiline_comment.'-'.$this->line_num);
				$multiline_comment = 0;
				continue;
			}

			$this->doc_end = $this->line_num - 1;

			if (!$this->doc_start) {
				throw new Exception('unexpected end of multiline comment', 'line: '.$this->line_num);
			}

			$this->parseDocBlock();
		}
		else if ($multiline_comment) {
			// ignore line inside of multiline comment
		}
		else if (substr($line, 0, 4) == '/** ' && substr($line, -2) == '*/') {
			// e.g. /** @var ... */
			if ($this->doc_start) {
				throw new Exception('documentation block aready started', 'doc_start= '.$this->doc_start.' line='.$this->line_num);
			}

			$this->doc_start = $this->line_num;
			$this->doc_end = $this->line_num;
			$this->parseDocBlock();
		}
		else if (substr($line, 0, 3) == '/**') {
			if ($this->doc_start) {
				throw new Exception('documentation block aready started', 'doc_start= '.$this->doc_start.' line='.$this->line_num);
			}

			$this->doc_start = $this->line_num + 1;
		}
		else if (substr($line, 0, 2) == '//' || (substr($line, 0, 2) == '/*' && substr($line, -2) == '*/')) {
			// ignore single comment line
			array_push($this->comment, $this->line_num.'-'.$this->line_num);
		}
		else if (preg_match('/^namespace ([a-zA-Z0-9_]+);$/', $line, $match)) {
			$this->namespace = $match[1];
		}
		else if (preg_match('/^use ([a-zA-Z0-9_\\\]+);$/', $line, $match)) {
			array_push($this->use, $match[1]);
		}
		else if (substr($line, 0, 7) == 'require' || substr($line, 0, 7) == 'include') {
			if (preg_match('/^(require_once|require|include_once|include)\((.+?)\);$/', $line, $match)) {
				array_push($this->{$match[1]}, $match[2]);
			}
			else {
				throw new Exception('invalid include[_once] or require[_once]', $this->line_num.'> '.$line);
			}
		}
	}
}


/**
 * Parse line after docblock end. Return docblock type:
 * class|abstract_method|method|class_var|class_const|function|const|var
 *
 * @throws
 * @return array [type, line, part1, ... ] 
 */
private function getDocType() {
	$lnum = ($this->doc_start == $this->doc_end) ? $this->doc_end + 1 : $this->doc_end + 2;
	$next_line = trim($this->code[$lnum - 1]);

	if (substr($next_line, -3) == ' */') {
		$next_line = trim(substr($next_line, 0, -3));
	}

	$k = 0;

	while ($k < 10 && $lnum + $k <= count($this->code) && substr($next_line, -1) != '{' && substr($next_line, -1) != ';') {
		$k++;
		$next_line .= ' '.trim($this->code[$lnum + $k - 1]);
	}

	if (substr($next_line, -1) != '{' && substr($next_line, -1) != ';') {
		throw new Exception('unrecognized line after documentation block', "line=$lnum k=$k code: $next_line");
	}

	$type = '';
	$match = [];

	$func_rx = 'function ([a-zA-Z0-9_]+)\((.*?)\)';
	$const_rx = '([A-Z0-9_]+) = (.+);$/';
	$var_rx = '\&?\$([a-zA-Z0-9_]+) ?=? ?(.*);$/';

	$in_class = !empty($this->class['name']);

	if (preg_match('/^(abstract|static)? ?class ([a-zA-Z0-9_]+) ?(extends)? ?(.*?) ?(implements)? ?(.*?) \{$/', $next_line, $match)) {
		if (count($this->function) > 0 || count($this->var) > 0 || $in_class) {
			throw new Exception('only one class per file', "line=$lnum code: $next_line");
		}

		$type = 'class';
	}
	else if ($in_class) {
		if (preg_match('/^abstract (public|private|protected) ?(static)? '.$func_rx.';$/', $next_line, $match)) {
			$type = 'abstract_method';
		}
		else if (preg_match('/^(public|private|protected) ?(static)? '.$func_rx.' \{$/', $next_line, $match)) {
			$type = 'method';
		}
		else if (preg_match('/^(public|private|protected) ?(static)? '.$var_rx, $next_line, $match)) {
			$type = 'class_var';
		}
		else if (preg_match('/^(const) '.$const_rx, $next_line, $match)) {
			$type = 'class_const';
		}
	}
	else {
		if (preg_match('/^(static)? ?'.$func_rx.' \{$/', $next_line, $match)) {
			$type = 'function';
		}
		else if (preg_match('/(public|private|protected)? (const) '.$const_rx, $next_line, $match)) {
			$type = 'const';
		}
		else if (preg_match('/(static)? ?'.$var_rx, $next_line, $match)) {
			$type = 'var';
		}
	}

	if (empty($type)) {
		throw new Exception('unrecognized line after documentation block', "line=$lnum code: $next_line");
	}

	array_unshift($match, $type);

	return $match;
}


/**
 * Return last line of previous function.
 *
 * @throws
 * @param int $lnum
 * @return int
 */
private function getCodeEnd($lnum) {

	if ($lnum < 1 || $lnum > count($this->code)) {
		throw new Exception('invalid line number '.$lnum);
	}

	$found = false;
	$n = $lnum - 1;

	while (!$found && $n > 0) {
		$line = trim($this->code[$n]);

		if (substr($line, -1) == '}') {
			$found = $n + 1;
		}

		$n--;
	}

	if (!$found) {
		throw new Exception('failed to get code end', $lnum);
	}

	return $found;
}


/**
 * Scan documentation lines for "@tag". First lines without "@tag" are description.
 */
private function parseDocBlock() {
	$function_type = [ 'method', 'abstract_method', 'function' ];

	$info = $this->getDocType();
	$type = array_shift($info);
	$line = array_shift($info);
	$tags = $this->splitTags();

	$desc = [];

	if ($type == 'class' || in_array($type, $function_type)) {
		$desc['doc_start'] = $this->doc_start - 1;
		$desc['doc_end'] = $this->doc_end + 1;
		$desc['code_start'] = $this->doc_end + 2;
		$desc['code_end'] = 0;
	}

	$func_num = count($this->function);
	if ($func_num > 0 && in_array($this->last_type, $function_type)) {
		$this->function[$func_num - 1]['code_end'] = $this->getCodeEnd($this->doc_start - 2); 
	}

	if ($type == 'class') {
		$desc['name'] = $info[1];
		$desc['namespace'] = $this->namespace;
		$desc['path'] = $this->namespace ? '\\'.$this->namespace.'\\'.$desc['name'] : $desc['name']; 
		$desc['abstract'] = intval($info[0] == 'abstract');
		$desc['static'] = intval($info[0] == 'static');
		$desc['extends'] = ($info[2] == 'extends') ? $info[5] : '';
		$desc['implements'] = ($info[3] == 'implements') ? $info[4] : '';
		$desc = array_merge($tags, $desc);
		$this->class = $desc;

		$required_tag = [ 'author', 'desc', 'copyright' ];
		$this->checkRequired([ 'author', 'desc', 'copyright' ], $desc);
	}
	else if ($type == 'method' || $type == 'abstract_method') {
		$desc['name'] = $info[2];
		$desc['namespace'] = $this->namespace;
		$desc['abstract'] = intval($type == 'abstract_method');
		$desc['scope'] = $info[0]; 
		$desc['static'] = intval($info[1] == 'static');
		$desc['path'] = $desc['static'] ? $this->class['path'].'::'.$desc['name'] : $this->class['path'].'->'.$desc['name'];
		$desc = array_merge($tags, $desc);
		$this->checkParam($desc, $info[3]);
		array_push($this->function, $desc);	
	}
	else if ($type == 'function') {
		$desc['name'] = $info[1];
		$desc['namespace'] = $this->namespace;
		$desc['static'] = intval($info[0] == 'static');
		$desc['path'] = $desc['namespace'] ? '\\'.$desc['namespace'].'\\'.$desc['name'] : $desc['name'];
		$desc = array_merge($tags, $desc);
		array_push($this->function, $desc);
	}
	else if ($type == 'class_var') {
		$desc['scope'] = $info[0];
		$desc['static'] = intval($info[1] == 'static');
		$desc['name'] = $info[2];
		$desc['type'] = $tags['var']['type'];
		$desc['default'] = empty($info[3]) ? '' : $info[3];
		$desc['path'] = $desc['static'] ? $this->class['path'].'::$'.$desc['name'] : $this->class['path'].'->$'.$desc['name'];

		$this->fixNameDesc($desc, $tags['var']);
		array_push($this->var, $desc);
	}
	else if ($type == 'class_const') {
		$desc['name'] = $info[1];
		$desc['type'] = $tags['const']['type'];
		$desc['value'] = $info[2]; 

		$this->fixNameDesc($desc, $tags['const']);
		array_push($this->const, $desc);
	}
	else {
		die("\nToDo:\n$type:\n---\n".print_r($tags, true)."\n---\n".print_r($info, true)."\n");
	}

	$this->last_type = $type;
	$this->doc_start = 0;
	$this->doc_end = 0;
}


/**
 * Check if param name matches "@param". Fix description.
 *
 * @throws
 * @param
 * @param 
 */
private static function fixNameDesc(&$desc, $tags) {

	if ($tags['name'] != $desc['name']) {
		throw new Exception('name mismatch '.$desc['name'].' != '.$tags['name']);
	}

	if (empty($tags['desc'])) {
		return;
	}

	if (empty($desc['desc'][0])) {
		$desc['desc'][0] = $tags['desc'];
	}
	else {
		array_push($desc['desc'], $tags['desc']);
	}
}


/**
 * Compare function parameter with @param. Add default value and byRef to info[param].
 *
 * @param array[string]string &$info
 * @param string $param_str
 */
private function checkParam(&$info, $param_str) {
	$plist = empty($param_str) ? [] : explode(', ', $param_str);
	$pinfo = isset($info['param']) ? $info['param'] : [];
	$k = 0;

	if (count($plist) != count($pinfo)) {
		throw new Exception('parameter count', "@param: ".
			print_r($pinfo, true)."\nplist: ".print_r($plist, true));
	}

	foreach ($plist as $param) {
		if (!preg_match('/^\&?\$([a-zA-Z0-9_]+) ?=? ?(.*)$/', $param, $match)) {
			throw new Exception('invalid parameter '.$param, 'line='.$info['code_start'].' function='.$info['name']);
		}

		if ($pinfo[$k]['name'] != $match[1]) {
			throw new Exception('parameter mismatch '.$match[1], "param $k of ($param_str) - @param: ".print_r($pinfo[$k], true));
		}

		if (substr($param, 0, 1) == '&') {
			$info['param'][$k]['byRef'] = 1;
		}

		if (!empty($match[2])) {
			$info['param'][$k]['default'] = trim($match[2]);	
			if (!empty($pinfo[$k]['desc']) && strpos($pinfo[$k]['desc'], 'default = ') !== false) {
				throw new Exception('remove default from @param '.$pinfo[$k]['name'].' in function '.$info['name'], 
					'line='.$info['code_start']);
			}
		}

		$k++;
	}
}


/**
 * Throw exception if required tags are missing.
 *
 * @throws
 * @param array $required_tags
 * @param array[string]mixed $info
 */
private function checkRequired($required_tags, $info) {
	foreach ($required_tags as $tag) {
		if (!isset($info[$tag]) || empty($info[$tag])) {
			throw new Exception('missing required tag @'.$tag.' in line '.$info['doc_start'].'-'.$info['doc_end']);
		}
	}
}


/**
 * Throw exception if type if invalid.
 * 
 * @throws
 * @param string $type
 * @param string $line
 */
private function checkType($type, $line) {

	static $native_object = [ '\\DOMNode' ];

	if (preg_match('/^array\[string\](.+)$/', $type, $match)) {
		$this->checkType($match[1], $line);
		return;
	}

	if (strpos($type, '|') !== false) {
		if (substr($type, 0, 1) == '(' && substr($type, -1) == ')') {
			$type = substr($type, 1, -1);
		}

		$list = explode('|', $type);

		foreach ($list as $ltype) {
			$this->checkType($ltype, $line);
		}

		return;
	}

	$allow = [ 'string', 'bool', 'boolean', 'int', 'integer', 'float', 'double', 'array', 
		'object', 'mixed', 'resource', 'void', 'null', 'callback', 'false', 'true', 'self' ];

	$ctype = (substr($type, -2) == '[]') ? substr($type, 0, -2) : $type;

	if (!in_array($ctype, $allow) && !in_array($ctype, $native_object)) {
		// ToDo: allow class name
		throw new Exception('invalid type '.$type, $line);
	}
}


/**
 * Return list of parsed tags in code[doc_start, doc_end].
 * 
 * @return array[string]mixed
 */
private function splitTags() { 

	$multiline_tag = 'desc';
	$multiline = '';
	$res = [];

	for ($lnum = $this->doc_start; $lnum <= $this->doc_end; $lnum++) {
		$line = trim($this->code[$lnum - 1]);

		if (substr($line, -3) == ' */') {
			$line = trim(substr($line, 0, -3));
		}
	
		if (substr($line, 0, 1) == '*') {
			$line = trim(substr($line, 1));
		}	
		else if (substr($line, 0, 4) == '/** ') {
			$line = trim(substr($line, 4));
		}	
		else {
			throw new Exception('invalid docblock', 'line: '.$lnum);
		}

		if (preg_match('/^@([a-z]+) ?(.*)$/', $line, $match)) {
			if ($multiline_tag) {
				$this->setTagValue($res, $multiline_tag, $multiline);
				$multiline_tag = '';
				$multiline = '';
			}

			$tname = $match[1];
			if (!isset(self::$tag[$tname])) {
				throw new Exception('no such documentation tag '.$tname, 'line_num='.$lnum.' line='.$line);
			}

			if (!self::$tag[$tname][0]) {
				$this->setTagValue($res, $tname, $match[2]);

				if (!empty($res[$tname]['type'])) {
					$this->checkType($res[$tname]['type'], "$lnum> $line");
				}
				else if (is_array($res[$tname])) {
                    $last = count($res[$tname]) - 1;
                    if (!empty($res[$tname][$last]['type'])) {
                        $this->checkType($res[$tname][$last]['type'], "$lnum> $line");
                    }
                }
			}
			else {
				if ($line != '@'.$tname) {
					throw new Exception('invalid multiline tag '.$tname, 'line_num='.$lnum.' line='.$line);
				}

				$multiline_tag = $tname;
			}
		}
		else if ($multiline_tag) {
			$multiline .= $line."\n";
		}
		else if (!empty($line)) {
			throw new Exception('invalid documentation block entry', 'line_num='.$lnum.' line='.$line);
		}
	}

	if ($multiline_tag) {
		$this->setTagValue($res, $multiline_tag, $multiline);
	}

	return $res;
}


/**
 * Set tag value in map.
 *
 * @throws 
 * @param map &$map
 * @param string $tname
 * @param string $value 
 */
private function setTagValue(&$map, $tname, $value) {
	$value = trim($value);

	if (!isset(self::$tag[$tname])) {
		throw new Exception('no such tag '.$tname, $value);
	}

	$is_unique = self::$tag[$tname][1];
	$split_value = !is_null(self::$tag[$tname][2]);

	if (!$is_unique && !isset($map[$tname])) {
		$map[$tname] = [];
	}

	if ($split_value) {
		if (preg_match(self::$tag[$tname][2][0], $value, $tag_parts)) {
			if (count($tag_parts) != count(self::$tag[$tname][2])) {
				throw new Exception('failed to parse tag '.$tname, "value: $value parts: ".print_r($tag_parts, true));
			}

			$value = [];
			for ($i = 1; $i < count(self::$tag[$tname][2]); $i++) {
				$key = self::$tag[$tname][2][$i];
				$tval = trim($tag_parts[$i]);

				if ($key == 'desc' && empty($tval)) {
					// ignore ...
				}
				else {
					$value[$key] = $tval;
				}
			}
		}
		else {
			throw new Exception('failed to parse tag '.$tname, $value);
		}
	}

	if ($is_unique) {
		$map[$tname] = $value;
	}
	else {
		array_push($map[$tname], $value);
	}
}


}

