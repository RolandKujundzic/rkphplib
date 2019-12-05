<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';

use rkphplib\Exception;


/**
 * HtmlTag String Object. Only start tag and attribute support (no innerHTML).
 * 
 * @author Roland Kujundzic
 */
class HtmlTag {

// @var string
public $name = '';

// @var string
public $html = '';

// @var int|bool
public $start = false;

// @var int|bool
public $end = false;

// @var array
private $attribute = [];



/**
 * 
 */
public function __construct(string $name) {
	$this->name = mb_strtolower(trim($name));

	if (!preg_match('/^[a-z]+$/', $this->name)) {
		throw new Exception('invalid html tag name '.$this->name);
	}
}


/**
 * Set html = ''. Return false if invalid.
 */
public function setHtml(string $html) : bool {
	$this->html = $html;
	$this->attribute = [];

	if (mb_stripos($html, '<'.$this->name) !== 0 || mb_substr($html, -1) != '>') {
		throw new Exception('invalid html', "'$html' != '<".$this->name." ... >'");
	} 

	$html = mb_substr($html, mb_strlen($this->name) + 1);
	while (preg_match('/^\s+([a-zA-Z0-9_\-]+)=?(".*?"|\'.*?\')?/s', $html, $match)) {
		if (count($match) == 3) {
			$this->attribute[$match[1]] = mb_substr($match[2], 1, -1);
		}
		else if (count($match) == 2) {
			$this->attribute[$match[1]] = $match[1];
		}

		$html = mb_substr($html, mb_strlen($match[0]));
	}

	$res = true;
	if (!preg_match('/^\s*\/?>$/', $html)) {
		$this->attribute = [];
		$this->html = '';
		$res = false;
	}

	return $res;
}


/**
 *
 */
public function getAttribute(string $name) : ?string {
	return isset($this->attribute[$name]) ? $this->attribute[$name] : null;
}


/**
 * 
 */
public function setAttribute(string $name, string $value) : void {
	$this->attribute[$name] = $value;
}


}

