<?php declare(strict_types=1);

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';
require_once __DIR__.'/File.class.php';


/**
 * XML wrapper.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
class XML {

// @var SimpleXMLElement $xml = null
protected $xml = null;

// @var array $ns = null
protected $ns = null;


/**
 * Call load($source) if $source is set.
 */
public function __construct(string $source = '') {
	if ($source) {
		$this->load($source);
	}
}


/**
 * Load xml data. Either xml string, url or file.
 */
public function load(string $source) : void {
	$xml = '';

	if (strpos($source, '<') !== false)  {
		$xml = $source;
		$source = '';
	}
	else if (substr(strtolower($source), 0, 5) == 'http') {
		$xml = File::fromURL($source);
	}
	else if (File::exists($source)) {
		$xml = File::load($source);
	}
	else {
		throw new Exception('invalid xml source', substr($source, 0, 800));
	}

	libxml_use_internal_errors(true);
	$this->xml = new \SimpleXMLElement($xml, LIBXML_NOCDATA);
	if ($this->xml == false) {
		$errors = libxml_get_errors();
		$lines = explode("\n", $xml);
		$info = count($errors) ? self::getError($errors[0], $lines, $source) : 'unknown error'; 
		throw new Exception('invalid xml', $info);
	}

	$this->ns = $this->xml->getDocNamespaces(true);
}


/**
 * Return value of xpath. Instead of /root/tag root.tag is allowed.
 * @return string|array
 */
public function get(string $xpath, $required = false) {
	if (strpos($xpath, '/') === false) {
		$xpath = '/'.str_replace([ '.', '@' ], [ '/', '/@' ], $xpath);
	}

	foreach ($this->xml->getNamespaces() as $prefix => $namespace) {
		$this->xml->registerXPathNamespace($prefix, $namespace);
	}

	$xp = $this->xml->xpath($xpath);
	$res = '';

	if (false === $xp) {
		if ($required) {
			throw new Exception('invalid xpath query', $xpath);
		}
		
		return $res;
	}
	else if (0 === count($xp)) {
		if ($required) {
			throw new Exception('xpath query failed', $xpath);
		}
		
		return $res;
	}
	else if (1 === count($xp)) {
		$res = $this->toArray($xp[0]);

		if (is_array($res) && isset($res['='])) {
			$res = $res['='];
		} 
	}
	else {
		throw new Exception('todo');
	}

	return $res; 
}


/**
 * Return xml error information.
 */
public static function getError(object $error, array $xml, string $source = '') : string {
	$res = trim($xml[$error->line - 1])."\n";

	switch ($error->level) {
		case LIBXML_ERR_WARNING:
			$res .= 'Warning '; break;
		case LIBXML_ERR_ERROR:
			$res .= 'Error '; break;
		case LIBXML_ERR_FATAL:
			$res .= 'Fatal Error '; break;
	}

	$res .= $error->code.' Line '.$error->line;

	if ($source) {
		$res .= ' in '.$source;
	}
	else if ($error->file) {
		$res .= ' in '.$error->file;
	}

	$res .= "\n".trim($error->message);
	return $res;
}


/**
 * Check if xml is valid
 */
public static function isValid(string $xml, bool $abort = false) : bool {
	libxml_use_internal_errors(true);
	$doc = new DOMDocument('1.0', 'utf-8');
	$doc->loadXML($xml);
	$errors = libxml_get_errors();

	if (empty($errors)) {
		return true;
	}

	$error = $errors[0];
	if ($error->level < 3) {
		return true;
	}

	if ($abort) {
		$lines = explode("\n", $xml);
		$info = count($errors) ? self::getError($errors[0], $lines) : 'unknown error'; 
		throw new Exception('invalid xml', $info);
	}

	return false;
}


/**
 * Return loaded xml as array. If tag has attriburtes or subtags return 
 * array with "@" als Attribute array key and "=" as value key. 
 * Multiple tags are named tag.0, tag.1, ...
 * Parameter is used for recursion. Use toMap to keep root node.
 *
 * @return array|string
 */
public function toArray(?\SimpleXMLElement $xml = null) {
	if (is_null($xml)) {
		$xml = $this->xml;
	}

	$name = $xml->getName();
	$value = trim(strval($xml));
	$has_value = strlen($value);
	$attributes = $xml->attributes();
	$res = [];

	$nodes = [];
	foreach ($this->ns as $prefix => $uri) {
		foreach ($xml->children($prefix, true) as $cname => $cnode) {
			$nodes[$cname] = $cnode; 
		}
	}

	if (!is_null($attributes)) {
		foreach ($attributes as $attrName => $attrValue) {
			$res['@'][$attrName] = strval($attrValue);
		}
	}

	if (count($nodes) == 0) {
		if (!isset($res['@'])) {
			return $value;
		}

		if ($has_value) {
			$res['='] = $value;
		}

		return $res;
	}

	$akey = [];

	foreach ($nodes as $nodeName => $nodeValue) {
		if (isset($akey[$nodeName])) {
			if ($akey[$nodeName] == 0) {
				$res[$nodeName.'.0'] = $res[$nodeName];
				unset($res[$nodeName]);
			}

			$akey[$nodeName]++;
			$nodeName .= '.'.$akey[$nodeName];
		}
		else {
			$akey[$nodeName] = 0;
		}

		$res[$nodeName] = $this->toArray($nodeValue);
	}

	if ($has_value) {
		$res['='] = $value;
	}

	return $res;
}


/**
 * Convert xml string to array.
 */
public static function toMap(string $xml, bool $keep_root = false) : array {
	$tmp = new XML($xml);
	$res = $tmp->toArray();

	if ($keep_root && preg_match('/<([a-z0-9_\:\-]+)\s*/i', $xml, $match)) {
		$res = [ $match[1] => $res ];
	}

	return $res;
}


/**
 * Convert hashmap $data to xml document.
 *
 * Use special keys "@" for attribute hash and "=" for tag value (use "@cdata" for cdata value).
 * If root is default or empty and data is hash with single key and value is array use key as root. 
 * Use "@file:path/to/file" to load base64 encoded file content. Example:
 *
 *  XML::fromMap(['A', 'B', 'C']) =  <root><vector>A</vector><vector>B</vector><vector>C</vector></root> 
 *  XML::fromMap(['names' => ['A', 'B', 'C']]) = <root><names>A</names>...</root>
 */
public static function fromMap(array $data, string $root = 'root') : string {
	/**
	 *  Convert array to DOMElement (recursive).
	 * @param mixed $data array|string
	 */
	$array2xml = function ($data, string $root, \DomDocument $xml) use (&$array2xml) : \DOMElement {
		if (!preg_match('/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i', $root)) {
			throw new Exception('Invalid xml tag name '.$root);
		}

		if (($node = $xml->createElement($root)) === false) {
			throw new Exception('Failed to create xml tag '.$root);
		}

		if (!is_array($data)) {
			$data = (string) $data;

			if (substr($data, 0, 6) == '@file:') {
				$file = substr($data, 6);
				$node->setAttribute('file', $file);
				$node->setAttribute('encoding', 'base64');
				$data = base64_encode(file_get_contents($file));
			}

			if (mb_strlen($data) > 0) {
				$node->appendChild($xml->createTextNode($data));
			}
		}
		else {
			if (isset($data['@'])) {
				foreach ($data['@'] as $key => $value) {
					if (!preg_match('/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i', $key)) {
						throw new Exception("Invalid $root attribute name ".$key);
					}

					$node->setAttribute($key, (string) $value);
				}

				unset($data['@']);
			}

			if (isset($data['='])) {
				$node->appendChild($xml->createTextNode((string) $data['=']));
				unset($data['=']);
			}
			else if (isset($data['@cdata'])) {
				$node->appendChild($xml->createCDATASection((string) $data['@cdata']));
				unset($data['@cdata']);
			}

			foreach ($data as $key => $value) {
				if (is_array($value) && key($value) === 0) {
					// first key of $value is 0 ... assume value is vector use $key as tag
					foreach ($value as $k => $v) {
						$node->appendChild($array2xml($v, $key, $xml));
					}
				}
				else {
					$node->appendChild($array2xml($value, $key, $xml));
				}

				unset($data[$key]);
			}
		}

		return $node;
	};


	$xml = new \DomDocument('1.0', 'UTF-8');
	$xml->preserveWhiteSpace = false;
	$xml->formatOutput = true;
	$initial = true;
	
	if ((empty($root) || $root == 'root') && is_array($data)) {
		$keys = array_keys($data);
		if (count($keys) == 1 && count($data[$keys[0]]) > 1) {
			$data = $data[$keys[0]];
			$root = $keys[0];
		}
	}

	$xml->appendChild($array2xml($data, $root, $xml));
	return $xml->saveXML();
}


/**
 * Return xml string.
 */
public function __toString() : string {
	return $this->xml->asXML();
}


/**
 * Pretty print xml string.
 */
public static function prettyPrint(string $xml_str) : string {

	if (strpos($xml_str, '<?xml') === false) {
		throw new Exception('Invalid xml', '<?xml missing');
	}

	$dom = new \DomDocument();
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($xml_str);
	return $dom->saveXML();
}


}

