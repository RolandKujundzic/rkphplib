<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';


/**
 * XML wrapper.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
class XML {


/**
 * Convert xml to hash.
 */
public static function toMap(string $xml) : array {
	$xml_obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	return json_decode(json_encode((array)$xml_obj), true);
}


/**
 * Convert hashmap $data to xml document.
 *
 * Use special keys "@attributes" = map, "@value|@cdata" = string
 * If root is default or empty and data is hash with single key and value is array use key as root. 
 *
 * Example:
 *
 *  XML::fromMap(['A', 'B', 'C']) =  <root><vector>A</vector><vector>B</vector><vector>C</vector></root> 
 *  XML::fromMap(['names' => ['A', 'B', 'C']]) = <root><names>A</names>...</root>
 *
 * @param mixed $data array|string
 * @return mixed string|\DOMNode
 */
public static function fromMap($data, string $root = 'root', ?\DomDocument $xml = null) {
	$initial = false;

	if (is_null($xml)) {
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
	}

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
		if (isset($data['@attributes'])) {
			foreach ($data['@attributes'] as $key => $value) {
				if (!preg_match('/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i', $key)) {
					throw new Exception("Invalid $root attribute name ".$key);
				}

				$node->setAttribute($key, (string) $value);
			}

			unset($data['@attributes']);
		}

		if (isset($data['@value'])) {
			$node->appendChild($xml->createTextNode((string) $data['@value']));
			unset($data['@value']);
		}
		else if (isset($data['@cdata'])) {
			$node->appendChild($xml->createCDATASection((string) $data['@cdata']));
			unset($data['@cdata']);
		}

		if ($initial && count(array_filter(array_keys($data), 'is_string')) == 0) {
			throw new Exception("Root level vector");
		}

		foreach ($data as $key => $value) {
			if (is_array($value) && key($value) === 0) {
				// first key of $value is 0 ... assume value is vector use $key as tag
				foreach ($value as $k => $v) {
					$node->appendChild(self::fromMap($v, $key, $xml));
				}
			}
			else {
				$node->appendChild(self::fromMap($value, $key, $xml));
			}

			unset($data[$key]);
		}

		if (count($data) > 0) {
			print_r($data);
		}
	}

	if ($initial) {
		$xml->appendChild($node);
		return $xml->saveXML();
	}

	return $node;
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
