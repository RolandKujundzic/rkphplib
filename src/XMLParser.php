<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';
require_once __DIR__.'/File.class.php';


/**
 * XML Parser based on Expat.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2008-2020 Roland Kujundzic
 *
 */
class XMLParser {

// @var array $data
public $data = [];

// @var array $env
public $env = [];


// @var array $_path
private $_path = [];

// @var array $_data_pos
private $_data_pos = [];


/**
 * 
 */
private function init() {
	$this->data = [];
	$this->env = [];
	$this->_path = [];
	$this->_data_pos = [];
}


/**
 *
 */
public function parse(string $xml_text) : void {
	$this->init();

	if (preg_match("/^\<\?xml(.+?)\?\>/", $xml_text, $match)) {
		// Match attribute-name attribute-value pairs.
		if (preg_match_all('#[ \t]+(.+?)=\"(.+?)\"#', $match[1], $matches, PREG_SET_ORDER) != 0) {
			foreach ($matches as $attribute) {
				$this->env[$attribute[1]] = $attribute[2];
			}
		}
	}

	$parser = xml_parser_create();

	xml_set_object($parser, $this);
	xml_set_element_handler($parser, 'xmlTagOpen', 'xmlTagClose');
	xml_set_character_data_handler($parser, 'xmlTagData');

	if (!xml_parse($parser, $xml_text, true)) {
		$error_msg = sprintf('XML error %d: "%s" at line %d column %d byte %d',
			xml_get_error_code($parser),
			xml_error_string(xml_get_error_code($parser)),
			xml_get_current_line_number($parser),
			xml_get_current_column_number($parser),
			xml_get_current_byte_index($parser));
		throw new Exception($error_msg);
	}

	xml_parser_free($parser);
}


/**
 * Return xml
 */
public function toString() : string {
	$close = array();
	$res = '';

	if (count($this->env) > 0) {
		$res = '<?xml';

		foreach ($this->env as $key => $value) {
			$res .= ' '.$key.'="'.$value.'"';
		}

		$res .= " ?>\n";
	}

	for ($i = 0; $i < count($this->data); $i++) {
		$tag = $this->data[$i];

		if (!isset($tag['>name'])) {
			$res .= $tag['>text'];
		}
		else {
			$res .= '<'.$tag['>name'];
			foreach ($tag as $key => $value) {
				if (substr($key, 0, 1) != '>') {
					$res .= ' '.$key.'="'.$value.'"';
				}
			}

			if ($tag['>end_pos'] == $i) {
				$res .= ' />';
			}
			else {
				$close[$tag['>end_pos']] = $tag['>name'];
				$res .= '>';
			}
		}

		if (!empty($close[$i])) {
			$res .= '</'.$close[$i].'>';
		}
	}

	return $res;
}


/**
 * Return data array as string.
 */
public function debug() : string {
	$res = '';

	foreach ($this->data as $tag) {

		if (!isset($tag['>name'])) {
			continue;
		}

		$res .= $tag['>name'].':';

		foreach ($tag as $key => $value) {
			if (substr($key, 0, 1) != '>') {
				$res .= ' ['.$key.']=['.$value.']';
			}
		}

		if (isset($tag['>text'])) {
			$res .= "\n	[".$tag['>text'].']';
		}
		else if (!empty($tag['>text_pos'])) {
			$text_pos = explode(',', $tag['>text_pos']);
			$res .= "\n	[";

			foreach ($text_pos as $pos) {
				$res .= $this->data[$pos]['>text'];
			}

			$res .= ']';
		}

		$res .= "\n";
	}

	return $res;
}


/**
 * Expat callback function (on tag open). Overwrite for custom parsing action.
 * 
 * @param resource $parser
 * @param string $name
 * @param array $attributes
 */
protected function xmlTagOpen($parser, $name, $attributes) {

	$name = strtolower($name);
	array_push($this->_path, $name);
	$path = join('/', $this->_path);

	$tag = array('>name' => $name, '>path' => $path, '>text_pos' => array());
	$tag['>line'] = xml_get_current_line_number($parser);

	foreach ($attributes as $key => $value) {
		$key = strtolower($key);
		$tag[$key] = $value;
	}

	$this->_data_pos[$path] = count($this->data);
	array_push($this->data, $tag);
}


/**
 * Expat callback function (on tag close)
 *
 * @param resource $parser
 * @param string $name
 */
protected function xmlTagClose($parser, $name) {

	$name = strtolower($name);
	$path = join('/', $this->_path);
	$last_name = end($this->_path);

	if ($last_name == $name) {
		$dp = $this->_data_pos[$path];
		$text_pos = $this->data[$dp]['>text_pos'];

		if (count($text_pos) == 1) {
			$this->data[$dp]['>text'] = $this->data[$text_pos[0]]['>text'];
		}

		$this->data[$dp]['>text_pos'] = join(',', $text_pos);
		$this->data[$dp]['>end_pos'] = count($this->data) - 1;
		array_pop($this->_path);
	}
}


/**
 * Expat callback function (on tag content)
 *
 * @param resource $parser
 * @param string $data
 */
protected function xmlTagData($parser, $data) {

	$path = join('/', $this->_path);

	$tag_data = array('>path' => $path, '>text' => $data);
	$tp = count($this->data);
	array_push($this->data, $tag_data);

	$dp = $this->_data_pos[$path];
	array_push($this->data[$dp]['>text_pos'], $tp);
}


}

