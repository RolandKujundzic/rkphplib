<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';
require_once __DIR__.'/lib/split_str.php';

use function rkphplib\lib\split_str;



/**
 * PHP Code parser.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class PhpCode {

/** @var string $file */
private $file = null; 

/** @var array $lines */
private $lines = [];

/** @var string $namespace */
private $namespace = null;

/** @var hash $namespaces */
private $namespaces = [];

/** @var hash $class */
private $class = null;

/** @var hash $classes */
private $classes = [];

/** @var hash $method */
private $method = null;

/** @var hash $methods */
private $methods = [];

/** @var int $lpos current line number (0, 1, ... ). Default is -1 (no line number scanned yet). */
private $lpos = -1;



/**
 * Return status hash (file, namespace, class, lpos and line_num).
 */
public function getStatus() : array {
	$status = [];

	$status['file'] = $this->file;
	$status['namespace'] = $this->namespace;
	$status['class'] = $this->class;
	$status['lpos'] = $this->lpos;
	$status['line_num'] = count($this->lines);

	return $status;
}


/**
 * Reset status. Flags:
 * 2^0 = 1: reset file
 * 2^1 = 2: reset namespace
 * 2^2 = 4: reset class
 * 2^3 = 8: reset method
 */
protected function reset($flag = 0) {
	if ($flag & 1) {
		$this->file = null; 
		$this->lines = [];
		$this->lpos = -1;
	}

	if ($flag & 2) {
		$this->namespace = null;
		$this->namespaces = [];
	}

	if ($flag & 4) {
		$this->class = null;
		$this->classes = [];
	}

	if ($flag & 8) {
		$this->method = null;
		$this->methods = [];
	}

	if ($this->lpos == -1 && ($flag & 1)) {
		$this->lpos = 0;
	}
}


/**
 * Load php source code from $file.
 */
public function load(string $file) : void {
	$this->reset(15);
	$this->file = $file;
	$this->lines = File::loadLines($file);

	if (substr($this->lines[0], 0, 6) != "<?php\n" && (substr($this->lines[0], 0, 15) != "#!/usr/bin/php\n" || 
			substr($this->lines[1], 0, 6) != "<?php\n")) {
		throw new Exception('no [<?php\n] or [#!/usr/bin/php\n<?php] in first line');
	}
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
 * Change current line number. Use $n = null to set line number to -1. Example:
 * setCurrentLineNumber(-1) == goto last line == setCurrentLineNumber(getLineNumber() - 1)
 * setCurrentLineNumber(0) == goto first line
 * setCurrentLineNumber(getCurrentLineNumber() + 1) == goto next line
 */
public function setCurrentLineNumber(?int $n) : void {
	if (is_null($n)) {
		$this->lpos = -1;
		return;
	}

	if ($n >= 0) {
		if ($n > count($this->lines) - 1) {
			throw new Exception("invalid line number $n use 0 ... ".(count($this->lines) - 1));
		}

		$this->lpos = $n;
	}
	else {
		if ($n + count($this->lines) < 0) {
			throw new Exception("invalid line number $n use -1 ... ".(-1 * count($this->lines)));
		}

		$this->lpos = count($this->lines) + $n;
	}
}


/**
 * If next line exists goto next line and return true.
 * Otherwise return false.
 */
public function nextLine() : bool {
	$res = false;

	if ($this->lpos < count($this->lines) - 1) {
		$this->lpos++;
		$res = true;
	}

	return $res;
}


/**
 * Return namespace. Throw Exception if not found and required.
 * Flags: 0 = get namespace, 1 = get required namespace, 2 = scan for next namespace.
 */
public function getNamespace(int $flag = 0) : ?string {
	if ($flag & 2) {
		if (!$this->nextLine()) {
			return null;
		}
	}
	elseif ($this->namespace) {
		return $this->namespace;
	}

	$this->reset(14);

	for ($i = $this->lpos; $this->namespace == null && $i < 5 && $i < count($this->lines); $i++) {
		if (strlen($this->lines[$i]) > 0 && preg_match('/^\s*namespace ([a-z_\\\]+)(;| \{)\s$/', $this->lines[$i], $match)) {
			$this->namespace = $match[1];
			$this->lpos = $i;
		}
	}

	if ($this->namespace == null) {
		if ($this->lpos > 0 && ($flag & 2) != 2) {
			// try again from start
			$this->lpos = 0;
			return $this->getNamespace($flag);
		}

		if ($flag & 1) {
			throw new Exception('Namespace not found in '.$this->file);
		}
	}

	if (($flag & 2) && $this->namespace != null) {
		$this->namespaces[$this->namespace] = $this->lpos; 
	}

	return $this->namespace;
}


/**
 * Return class definition. Throw Exception if not found and required. Result keys (if not null):
 * abstract: true|false, name: string, extends: ?string, implements: ?array, path: string.
 * Flags: 0 = get (first) class, 1 = get required (first) class, 2 = scan for next class
 */
public function getClass(int $flag = 0) : ?array {
	if ($flag & 2) {
		if (!$this->nextLine()) {
			return null;
		}
	}
	elseif ($this->class) {
		return $this->class;
	}

	$this->reset(8);

	if (($flag & 2) != 2 && !$this->namespace) {
		$this->getNamespace();
	}

	for ($i = $this->lpos; $this->class == null && $i < count($this->lines); $i++) {
  	if (preg_match('/^(abstract )?class ([a-zA-Z_]+)( extends [a-zA-Z_]+)?( implements [a-zA-Z_, ]+)? \{\s$/', $this->lines[$i], $match)) {
			$this->class = [];

			$this->class['abstract'] = ($match[1] == 'abstract') ? true : false;
			$this->class['name'] = $match[2];
			$this->class['path'] = $this->namespace ? $this->namespace.'\\'.$this->class['name'] : $this->class['name'];
			$this->lpos = $i;

			if (substr($match[3], 0, 9) == ' extends ') {
				$this->class['extends'] = trim(substr($match[3], 9));
			}

			if (substr($match[4], 0, 12) == ' implements ') {
				$this->class['implements'] = split_str(',', trim(substr($match[4], 12)));
			}
		}
	}

	if ($this->class == null) {
		if ($this->lpos > 0 && ($flag & 2) != 2) {
			// try again from start
			$this->lpos = 0;
			return $this->getClass($flag);
		}

		if ($flag & 1) {
			throw new Exception('Class not found in '.$this->file);
		}
	}

	if (($flag & 2) && $this->class != null) {
		$this->classes[$this->class['name']] = $this->lpos;
	}

	return $this->class;
}


/**
 * Return next @tok example.
 */
public function getNextTok() : array {
	$ml_comment = false;

	/*
	for (; $i < count($code_lines); $i++) {
		$line = trim($code_lines[$i]);

		if (mb_substr($line, 0, 3) == '/**') {
			$ml_comment = true;
		}

		if (!$ml_comment || strlen($line) == 0 || $line[0] != '*' || mb_substr($line, 2, 5) != '@tok ') {
			continue;
		}

		$line = trim(mb_substr($line, 7));

		if (substr($line, 0, 1) == '"' && substr($line, -1) == '"') {
			$plugin = substr($line, 1, -1);
			$linebreak = true;
		}
		elseif (($pos = mb_strrpos($line, '=')) !== false) {
			$plugin = trim(mb_substr($line, 7, $pos - 7));
			$result = trim(mb_substr($line, $pos + 1));
		}
		else {
			$plugin = $line;
			$linebreak = true;
		}
	
		if ($linebreak) {
			$result = trim(mb_substr($code_lines[$i + 1], 3));
			$i++;

			if (mb_substr($result, 0, 12) == '@tok:result ') {
				$result_file = trim(mb_substr($result, 12));
				$result = File::load($result_file.'.ok');
			}
		}
	}
	*/
	
	exit(1);
}


}

