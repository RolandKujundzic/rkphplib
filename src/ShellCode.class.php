<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';



/**
 * Shell Code parser.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class ShellCode {

// @var string $file
private $file = null;

// @var array $lines
private $lines = [];

// @var hash $variable
private $variable = [];

// @var hash $function
private $function = [];

// @var int $lpos
private $lpos = -1;

// @var hash $parse_env
private $parse_env = null;



/**
 * Return status hash (file, namespace, class, lpos and line_num).
 */
public function getStatus() : array {
  $status = [];

  $status['file'] = $this->file;
  $status['function'] = join(' ', array_keys($this->function));
  $status['variable'] = join(' ', array_keys($this->variable));
  $status['lpos'] = $this->lpos;
  $status['line_num'] = count($this->lines);

  return $status;
}


/**
 * Return true if variable exists.
 */
public function hasVar(string $name) : bool {
	return isset($this->variable[$name]);
}


/**
 *  Return $variable[$name]. If Variable does not exist return ''.
 */
public function getVar(string $name, bool $required = false) : string {
	$res = '';

	if (isset($this->variable[$name])) {
		$res = $this->variable[$name];
	}
	else if ($required) {
		throw new Exception('no such variable '.$name);
	}

	return $res;
}


/**
 * Split $variable[$name] into array. Delimiter is '[ \t\r]*\n[ \t\r]*'.
 * If variable does not exist return [].
 */
public function getArray(string $name, bool $required = false) : array {
	$res = [];

	if (isset($this->variable[$name])) {
		$res = preg_split("/[ \t\r]*\n[ \t\r]*/", trim($this->variable[$name]));
	}
	else if ($required) {
		throw new Exception('no such array '.$name);
	}

	return $res;
}


/**
 * Return number lines in current file.
 */
public function getLineNumber() : int {
	return count($this->lines);
}


/**
 * Return current line number (-1, 0, 1, ...).
 */
public function getCurrentLineNumber() : int {
	return $this->lpos;
}


/**
 * Return next line if exists otherwise return null.
 */
public function nextLine() : ?string {
	$res = null;

	if ($this->lpos < count($this->lines) - 1) {
		$this->lpos++;
		$res = $this->lines[$this->lpos];
	}

	// \rkphplib\lib\log_debug("ShellCode.nextLine:123> nextLine: lpos=".$this->lpos." found: ".$res);
	return $res;
}


/**
 * Reset status. Flags:
 * 2^0 = 1: reset file
 * 2^1 = 2: reset variable
 * 2^2 = 4: reset function
 */
protected function reset($flag = 0) {
  if ($flag & 1) {
    $this->file = null;
    $this->lines = [];
    $this->lpos = -1;
  }

  if ($flag & 2) {
    $this->variable = [];
  }

  if ($flag & 4) {
    $this->function = [];
  }
}


/**
 * Load shell script from $file.
 */
public function load(string $file) : void {
	$this->reset(7);
	$this->file = $file;
	$this->lines = File::loadLines($file);

	if (strtolower(substr($file, -3)) != -3 && substr($this->lines[0], 0, 12) != "#!/bin/bash\n" && 
			substr($this->lines[0], 0, 10) != "#!/bin/sh\n") {
		throw new Exception('neither *.sh suffix nor [#!/bin/(bash|sh)\n] in first line');
	}
}


/**
 * Set $variable[$parse_env.ml_var] or $function[$parse_env.fname][variable][parse_env.ml_var]. 
 * Update $parse_env[ml_var].
 */
private function parseMultiLineVariable(string $line) : void {
	$local_var = $this->parse_env['local_var'];
	$ml_var = $this->parse_env['ml_var'];
	$fname = $this->parse_env['fname'];
	$tline = trim($line);

	if (substr($tline, -1) == '"') {
		if (isset($local_var[$ml_var])) {
			$this->function[$fname]['variable'][$ml_var] .= substr($tline, 0, -1);
		}
		else {
			$this->variable[$ml_var] .= substr($tline, 0, -1);
		}

		$this->parse_env['ml_var'] = '';
	}
	else if (isset($local_var[$ml_var])) {
		$this->function[$fname]['variable'][$ml_var] .= $line;
	}
	else {
		$this->variable[$ml_var] .= $line;
	}
}


/**
 * Set $variable[$vname] or $function[$parse_env.fname][variable][$vname]. Update $parse_env[ml_var].
 */
private function parseVariable(string $vname, string $value) : void {
	$fname = $this->parse_env['fname'];
	$local_var = $this->parse_env['local_var'];

	if (!isset($local_var[$vname]) && !empty($this->variable[$vname])) {
		// ignore
	}
	else if (isset($local_var[$vname]) && !empty($this->function[$fname]['variable'][$vname])) {
		// ignore
	}
	else if ($value && $value[0] == '"') {
		$tval = trim($value);

		if (substr($tval, -1) == '"') {
			$value = substr($tval, 1, -1);
		}
		else {
			$value = substr($value, 1);
			$this->parse_env['ml_var'] = $vname;
		}
	}
	else {
		$value = trim($value);
	}

	if (!isset($local_var[$vname])) {
		$this->variable[$vname] = $value;
	}
	else {
		$this->function[$fname]['variable'][$vname] = $value;
	}
}


/**
 * @todo
 */
private function parseComment() {
	$comment = $this->parse_env['comment'];
	$this->parse_env['comment'] = [];
	return $comment;
}


/**
 * Set $function[$name] and $parse_env[fname|bracket|local_var].
 */
private function parseFunction($name) {
	$func = [];
	$func['name'] = $name;
	$func['comment'] = $this->parseComment();
	$func['variable'] = [];
	$func['start'] = $this->lpos;
	$func['end'] = -1;

	$this->function[$name] = $func;

	$this->parse_env['bracket'] = 1;
	$this->parse_env['local_var'] = [];
	$this->parse_env['fname'] = $name;
}


/**
 * Set parse_env[bracket|fname|local_var].
 */
private function parseFunctionBody(string $line) : void {
	$bc = preg_replace('/[^\{\}]/', '', $line);
	for ($i = 0; $i < strlen($bc); $i++) {
		if ($bc[$i] == '{') {
			$this->parse_env['bracket']++;
		}
		else {
			$this->parse_env['bracket']--;
		}
	}
	
	if ($this->parse_env['bracket'] == 0) {
		$this->function[$this->parse_env['fname']]['end'] = $this->lpos;
		$this->parse_env['comment'] = [];
		$this->parse_env['fname'] = '';
	}
}


/**
 * Parse shell script $file.
 */
public function parse(string $file) : void {
	$this->parse_env = [ 'local_var' => [], 'comment' => [], 'bracket' => 0, 'ml_var' => '', 'fname' => '' ];
	$this->load($file);

	while (($line = $this->nextLine(true))) {
		$tline = trim($line);

		if ($tline && $tline[0] == '#') {
			array_push($this->parse_env['comment'], trim(substr($tline, 1)));
		}
		else if ($this->parse_env['ml_var']) {
			$this->parseMultiLineVariable($line);
		}
		else if (preg_match('/^\s*(local )?([a-zA-Z0-9_]+)=(.*)$/', $line, $match)) {
			if ($match[1] == 'local ') {
				if (empty($this->parse_env['fname'])) {
					throw new Exception('invalid local variable '.$match[2].' in line '.$this->lpos);
				}

				$this->parse_env['local_var'][$match[2]] = 1;
			}

			$this->parseVariable($match[2], $match[3]."\n");
		}
		else if (preg_match('/^\s*function ([a-zA-Z0-9_]+) \{\s+$/', $line, $match)) {
			$this->parseFunction($match[1]);
		}
		else if (!empty($this->parse_env['fname']) && $this->parse_env['bracket'] > 0) {
			$this->parseFunctionBody($line);
		}
	}
}


}


