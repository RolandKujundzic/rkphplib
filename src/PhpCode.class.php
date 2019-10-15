<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';


/**
 * PHP Code parser.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class PhpCode {

/** @var string $file */
private $file = ''; 

/** @var array $lines */
private $lines = [];

/** @var string $namespace */
private $namespace = null;

/** @var hash $class */
private $class = null;

/** @var int $lpos current line number (0, 1, ... ) */
private $lpos = 0;



/**
 * Load php source code from $file.
 */
public function load(string $file) : void {
	$this->file = $file;
	$this->lines = File::loadLines($file);
	$this->namespace = '';
	$this->class = '';
	$this->lpos = 0;

	if (substr($this->lines[0], 0, 6) != "<?php\n") {
		throw new Exception('no <?php in first line');
	}
}


/**
 * Return namespace. Throw Exception if not found and required.
 */
public function getNamespace(bool $required = false) : ?string {
	if ($this->namespace) {
		return $this->namespace;
	}

	$this->namespace = null;

	for ($i = $this->lpos; $this->namespace == null && $i < count($this->lines); $i++) {
		if (strlen($line) > 0 && preg_match('/^\s*namespace ([a-z_]+)(;| \{)\s$/', $this->lines[$i], $match)) {
			$this->namespace = $match[1];
			$this->lpos = $i;
		}
	}

	if ($this->namespace == null) {
		if ($this->lpos > 0) {
			// try again from start
			$this->lpos = 0;
			return $this->getNamespace($required);
		}

		if ($required) {
			throw new Exception('Namespace not found in '.$this->file);
		}
	}

	return $this->namespace;
}


/**
 * Return class. Throw Exception if not found and required.
 */
public function getClass(bool $required = false) : ?string {
	if ($this->class) {
		return $this->class;
	}

	$this->class = null;

	if (!$this->namespace) {
		$this->getNamespace();
	}

	for ($i = $this->lpos; $this->class == null && $i < count($this->lines); $i++) {
  	if (preg_match('/^(abstract )?class ([a-zA-Z_]+) (extends [a-zA-Z_]+|implements [a-zA-Z_, ]+)?\{\s$/', $this->lines[$i], $match)) {
			$this->class = [];

			if ($match[1] == 'abstract') {
				$this->class['abstract'] = true;
				$this->class['name'] = $match[2];
			}
			else {
				$this->class['name'] = $match[1];
			}

			$this->class['path'] = $this->namespace ? $this->namespace.'\\'.$this->class['name'] : $this->class['name'];
			$this->lpos = $i;

			// ToDo Extends + Implements
		}
	}

	if ($this->class == null) {
		if ($this->lpos > 0) {
			// try again from start
			$this->lpos = 0;
			return $this->getClass($required);
		}

		if ($required) {
			throw new Exception('Class not found in '.$this->file);
		}
	}

	return $this->class;
}


/**
 * Return next @tok example.
 */
public function getNextTok() : array {
	$ml_comment = false;

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

	
	exit(1);
}


}

