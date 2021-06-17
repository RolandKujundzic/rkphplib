<?php

namespace rkphplib;

require_once __DIR__.'/Exception.php';

use rkphplib\Exception;


/**
 * HtmlTag String Object. Only start tag and attribute support (no innerHTML).
 * 
 * @author Roland Kujundzic
 */
class HtmlTag {

// @var array
private $env = [ 'name' => null, 'html' => null, 'start' => null, 'end' => null, 'closed' => null ];

// @var array
private $attribute = [];



/**
 * 
 */
public function __construct(string $name) {
	$name = mb_strtolower(trim($name)); 
	if (!preg_match('/^[a-z]+$/', $name)) {
		throw new Exception('invalid html tag name '.$name);
	}

	$this->env['name'] = $name;
}


/**
 * Return html tag as string.
 */
public function toString() : string {
	$html = '<'.$this->env['name'];

	foreach ($this->attribute as $name => $value) {
		$html .= ' '.$name.'="'.$value.'"';
	}

	$html .= $this->env['closed'] ? '/>' : '>';
	return $html;
}


/**
 * Return true if name|html|start|end|closed is set.
 */
public function has(string $property) : bool {
	return isset($this->env[$property]);
}


/**
 * Return name|html|start|end|closed.
 *
 * @return string|int|bool
 */
public function get(string $property) {
	if (!isset($this->env[$property])) {
		throw new Exception('no such property', $property);
	}

	return $this->env[$property];
}


/**
 * Set html = ''. Return false if invalid.
 */
public function setHtml(string $html, $start = null, $end = null) : bool {
	$tag_name = $this->env['name'];

	if (mb_stripos($html, '<'.$tag_name) !== 0 || mb_substr($html, -1) != '>') {
		throw new Exception('invalid html', "'$html' != '<".$tag_name." ... >'");
	} 

	$this->env = [ 'name' => $tag_name, 'html' => $html, 'start' => $start, 'end' => $end, 'closed' => false ];

	$is_closed = [ 'img', 'br', 'hr', 'link', 'meta' ];
	if (mb_substr($html, -2) == '/>' || in_array($tag_name, $is_closed)) {
		$this->env['closed'] = true;
	}

	$html = mb_substr($html, mb_strlen($tag_name) + 1);
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
	if (!preg_match('/^\s*\/?'.'>$/', $html)) {
		$this->env = [ 'name' => $tag_name, 'html' => null, 'start' => null, 'end' => null, 'closed' => null ];
		$res = false;
	}

	return $res;
}


/**
 * Return attribute $name value.
 */
public function getAttribute(string $name) : ?string {
	return isset($this->attribute[$name]) ? $this->attribute[$name] : null;
}


/**
 * Set attribute $name = $value.
 */
public function setAttribute(string $name, string $value) : void {
	$this->attribute[$name] = $value;
}


}

