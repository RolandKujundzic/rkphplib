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

// @var array $_xml_tag
private $_xml_tag = [];

// @var array $_path (tag call stack)
private $_path = [];

// @var array $_data_pos (last tag occurance)
private $_data_pos = [];

// @var callable $_callback
private $_callback = [];


/**
 * 
 */
private function reset() {
	$this->data = [];
	$this->_xml_tag = [];
	$this->_path = [];
	$this->_data_pos = [];
}


/**
 * Set multiple tag path (a/b/...) callbacks.
 * All tags path prefix and either attributes or text are callback.
 *
 * @start_example
 * class ShopImport {
 *   public function addItem(string $tag, string $text, array $attrib, string $path) { ... }
 * }
 * 
 * $import = new ShopImport();
 * // call $import->addItem(...) foreach tag (without subtags) in <shop><item>...</item></shop>
 * setCallback($import, [ 'shop/item' => 'addItem' ]); 
 * @end_example
 */
public function setCallback(object $obj, array $map) {
	foreach ($map as $path => $func) {
		$path = strtolower($path);
		$this->_callback[$path] = [ $obj, $func ];
	}
}


/**
 * Parse xml text
 */
public function parse(string $xml_text) : void {
	$this->reset();

	if (preg_match("/^\<\?xml(.+?)\?\>/", $xml_text, $match)) {
		// Match attribute-name attribute-value pairs.
		if (preg_match_all('#[ \t]+(.+?)=\"(.+?)\"#', $match[1], $matches, PREG_SET_ORDER) != 0) {
			foreach ($matches as $attribute) {
				$this->_xml_tag[$attribute[1]] = $attribute[2];
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
 * Parse xml file. Use $func(string $tag, string $text, array $attrib, array $path) as callback.
 * Use $start_line = n (>0) and $end_line (>$start_line) to read only parts.
 */
public function load(string $xml_file, int $start_line = 0, int $end_line = 0) : void {
	$this->reset();

	$parser = xml_parser_create();

	xml_set_object($parser, $this);
	xml_set_element_handler($parser, 'xmlTagOpen', 'xmlTagClose');
	xml_set_character_data_handler($parser, 'xmlTagData');

	if (false === ($fh = fopen($xml_file, 'rb'))) {
		throw new Exception('open xml file', "file=[$xml_file]");
	}

	$n = -1;
	for ($n = 0; $n < $start_line; $n++) {
		fgets($fh); // skip
	}

	while (!($eof = feof($fh)) && $n < $end_line) {
		$line = fgets($fh);
		$n++;

		\rkphplib\lib\log_debug("XMLParser.load:129> $n: ".trim($line));
		if (!xml_parse($parser, $line, $eof)) {
			$error_msg = sprintf('XML error %d: "%s" at line %d column %d byte %d',
				xml_get_error_code($parser),
				xml_error_string(xml_get_error_code($parser)),
				xml_get_current_line_number($parser),
				xml_get_current_column_number($parser),
				xml_get_current_byte_index($parser));
			throw new Exception($error_msg);
		}
	}

	fclose($fh);
	xml_parser_free($parser);
}


/**
 * Return xml
 */
public function toString() : string {
	$close = array();
	$res = '';

	if (count($this->_callback) > 0) {
		// print "data: ".print_r($this->data, true)." _data_pos: ".print_r($this->_data_pos, true);
		return '';
	}

	if (count($this->_xml_tag) > 0) {
		$res = '<'.'?xml';

		foreach ($this->_xml_tag as $key => $value) {
			$res .= ' '.$key.'="'.$value.'"';
		}

		$res .= ' ?'.">\n";
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
 * Expat callback function (on leaf tag close).
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

		$text = '';
		for ($i = 0; $i < count($text_pos); $i++) {
			$text .= $this->data[$text_pos[$i]]['>text'];
		}

		$this->data[$dp]['>text'] = $text;
		$this->data[$dp]['>text_pos'] = join(',', $text_pos);
		$this->data[$dp]['>end_pos'] = count($this->data) - 1;
		
		\rkphplib\lib\log_debug("XMLParser.xmlTagClose:283> $dp: ".print_r($this->data[$dp], true));
		$cpath_list = array_keys($this->_callback);
		foreach ($cpath_list as $cpath) {
			if (strpos($path, $cpath) === 0) {
				$this->call_back($cpath, $this->data[$dp]);
			}
		}

		if (count($cpath_list) > 0) {
			// free memory in callback mode
			foreach ($this->data[$dp] as $key => $value) {
				if ($key != '>text_pos') {
					unset($this->data[$dp][$key]);
				}
			}
		}

		array_pop($this->_path);
	}
}


/**
 * Call $func 
 */
private function call_back(string $cpath, array $data) : void {
	$attrib = [];
	foreach ($data as $key => $value) {
		if (substr($key, 0, 1) != '>') {
			$attrib[$key] = $value;
		}
	}

	$tag = $data['>name'];
	$text = isset($data['>text']) ? $data['>text'] : null;

	if (!is_null($text) || count($attrib) > 0) {
		\rkphplib\lib\log_debug([ "XMLParser.call_back:309> <1>(<2>, <3>, <4>, <5>)", $this->_callback[$cpath][1], $tag, $text, $attrib, $data['>path'] ]);
		call_user_func($this->_callback[$cpath], $tag, $text, $attrib, $data['>path']);
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

