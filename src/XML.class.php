<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;


/**
 * XML wrapper.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class XML {


/**
 * Convert xml to json.
 *
 * @param string $xml
 * @return map
 */
public static function toJSON($xml) {
	$xml_obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	return json_decode(json_encode((array)$xml_obj), true);
}


/**
 * Convert map $json to xml document.
 *
 * Use special keys "@attributes" = map, "@value|@cdata" = string
 * If root is default or empty and json is hash with single key and value is array use key as root. 
 *
 * Example:
 *
 *  XML::fromJSON(['A', 'B', 'C']) =  <json2xml><vector>A</vector><vector>B</vector><vector>C</vector></json2xml> 
 *  XML::fromJSON(['names' => ['A', 'B', 'C']]) = <json2xml><names>A</names>...</json2xml>
 *
 * @throws rkphplib\Exception
 * @param map|string $json
 * @param map $root (default = 'json2xml')
 * @param map $xml (default = null = create DomDocument) 
 * @return string|xmlElement
 */
public static function fromJSON($json, $root = 'json2xml', $xml = null) {

	$initial = false;

	if (is_null($xml)) {
		$xml = new \DomDocument('1.0', 'UTF-8');
		$xml->preserveWhiteSpace = false;
		$xml->formatOutput = true;
		$initial = true;
	
		if ((empty($root) || $root == 'json2xml') && is_array($json)) {
			$jkeys = array_keys($json);
			if (count($jkeys) == 1 && count($json[$jkeys[0]]) > 1) {
				$json = $json[$jkeys[0]];
				$root = $jkeys[0];
			}
		}
	}

	if (!preg_match('/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i', $root)) {
		throw new Exception('Invalid xml tag name '.$root);
	}

	if (($node = $xml->createElement($root)) === false) {
		throw new Exception('Failed to create xml tag '.$root);
	}

	if (!is_array($json)) {
		$json = (string) $json;

		if (substr($json, 0, 6) == '@file:') {
			$file = substr($json, 6);
			$node->setAttribute('file', $file);
			$node->setAttribute('encoding', 'base64');
			$json = base64_encode(file_get_contents($file));
		}

		if (mb_strlen($json) > 0) {
			$node->appendChild($xml->createTextNode($json));
		}
	}
	else {
		if (isset($json['@attributes'])) {
			foreach ($json['@attributes'] as $key => $value) {
				if (!preg_match('/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i', $key)) {
					throw new Exception("Invalid $root attribute name ".$key);
				}

				$node->setAttribute($key, (string) $value);
			}

			unset($json['@attributes']);
		}

		if (isset($json['@value'])) {
			$node->appendChild($xml->createTextNode((string) $json['@value']));
			unset($json['@value']);
		}
		else if (isset($json['@cdata'])) {
			$node->appendChild($xml->createCDATASection((string) $json['@cdata']));
			unset($json['@cdata']);
		}

		if ($initial && count(array_filter(array_keys($json), 'is_string')) == 0) {
			throw new Exception("Root level vector");
		}

		foreach ($json as $key => $value) {
			if (is_array($value) && key($value) === 0) {
				// first key of $value is 0 ... assume value is vector use $key as tag
				foreach ($value as $k => $v) {
					$node->appendChild(self::fromJSON($v, $key, $xml));
				}
			}
			else {
				$node->appendChild(self::fromJSON($value, $key, $xml));
			}

			unset($json[$key]);
		}

		if (count($json) > 0) {
			print_r($json);
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
 * 
 * @param string $xml_str
 * @return string
 */
public static function prettyPrint($xml_str) {
  $dom = new \DomDocument();
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->loadXML($xml_str);
  return $dom->saveXML();
}


}
