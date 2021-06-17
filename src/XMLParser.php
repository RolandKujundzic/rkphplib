<?php

namespace rkphplib;

require_once __DIR__.'/Exception.php';
require_once __DIR__.'/File.php';


/**
 * XML Parser based on Expat.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
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

// @var array $_callback (<string, callable>)
private $_callback = [];

// @var array $_path_list
private $_path_list = [];


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
 * function xml_tag(string $tag, string $text, array $attrib, string $path) { ... }
 *
 * $import = new ShopImport();
 * $xml_reader = new XMLParser(); 
 * $xml_reader->setCallback($import, [ 'shop/item' => 'addItem' ]); 
 * $xml_reader->setCallback(null, [ 'shop' => 'xml_tag' ]);
 * $xml_reader->parse('<shop><category>...</category>...<item>...</item></shop>');
 * @end_example
 */
public function setCallback(?object $obj, array $map) {
	$this->_callback = [];
	foreach ($map as $path => $func) {
		$path = strtolower($path);
		$this->_callback[$path] = is_null($obj) ? $func : [ $obj, $func ];
	}
	// \rkphplib\lib\log_debug("XMLParser.setCallback:70> _callback: ".join("\n", array_keys($this->_callback)));
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

	// \rkphplib\lib\log_debug("XMLParser.load:122> $xml_file [$start_line,$end_line]");
	if (false === ($fh = fopen($xml_file, 'rb'))) {
		throw new Exception('open xml file', "file=[$xml_file]");
	}

	$n = 0;
	for ( ; $n < $start_line; $n++) {
		fgets($fh); // skip
	}

	while (!($eof = feof($fh)) && ($end_line == 0 || $n < $end_line)) {
		$line = fgets($fh);
		$n++;

		// \rkphplib\lib\log_debug("XMLParser.load:136> $n: ".trim($line));
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
 * Scan xml file. Return path list. Parameter is either file path or xml string.
 */
public function scan(string $xml) : array {
	$this->setCallback($this, [ '*' => 'scanPath' ]);

	if (File::exists($xml)) {
		$this->load($xml);
	}
	else {
		$this->parse($xml);
	}

	return $this->_path_list;
}


/**
 * tag callback (*)
 */
private function scanPath(string $tag, ?string $text, array $attrib, string $path) {
	if (!in_array($path, $this->_path_list)) {
		array_push($this->_path_list, $path);
	}
}


/**
 * tag callback
 */
private function printTags(string $tag, ?string $text, array $attrib, string $path) {
	$attrib_str = '';
	foreach ($attrib as $key => $value) {
		$attrib_str .= ' '.$key.'="'.$value.'"';
	}

	$text = is_null($text) ? '/>' : '>'.trim($text)."</$tag>";

	print "<$tag$attrib_str$text\n";
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
		
		// \rkphplib\lib\log_debug("XMLParser.xmlTagClose:338> $dp: ".print_r($this->data[$dp], true));
		$cpath_list = array_keys($this->_callback);
		foreach ($cpath_list as $cpath) {
			// \rkphplib\lib\log_debug("XMLParser.xmlTagClose:341> [$path] ? [$cpath]");
			if (strpos($path, $cpath) === 0 || $cpath == '*' ||
					(substr($cpath, 0, 1) === '*' && strpos($path, strpos($cpath, 1)) !== false)) {
				$this->call_back($cpath, $this->data[$dp]);
			}
		}

		if (count($cpath_list) > 0) {
			// free memory in callback mode
			unset($this->_data_pos[$path]);
			$this->data[$dp] = null;

			for ($i = 0; $i < count($text_pos); $i++) {
				$this->data[$text_pos[$i]] = null;
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

	$method = is_array($this->_callback[$cpath]) ? $this->_callback[$cpath][1] : $this->_callback[$cpath];
	// \rkphplib\lib\log_debug([ "XMLParser.call_back:378> <1>(<2>, '<3>', <4>, <5>)", $method, $tag, $text, $attrib, $data['>path'] ]);
	call_user_func($this->_callback[$cpath], $tag, $text, $attrib, $data['>path']);
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

