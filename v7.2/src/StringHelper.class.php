<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';
require_once __DIR__.'/HtmlTag.class.php';

use rkphplib\Exception;
use rkphplib\HtmlTag;


/**
 * String manipulation.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class StringHelper {

// @var ?string $text
private $text = null;



/**
 * Set text.
 */
public function __construct(string $text = null) {
	$this->text = $text;
}


/**
 * Return HtmlTag if found.
 */
public function nextTag(HtmlTag $tag) : ?HtmlTag {
	$offset = $tag->has('end') ? $tag->get('end') + 1 : 0;
	$tname = '<'.$tag->get('name');
	$start = mb_stripos($this->text, $tname, $offset);
	$len = mb_strlen($tname);

	$tag = new HtmlTag($tag->get('name'));
	if ($start !== false && ($next_char = mb_substr($this->text, $start + $len, 1)) && 
			in_array($next_char, [ "\r", "\n", "\t", " " ]) &&
			($end = mb_strpos($this->text, '>', $start + $len + 1)) !== false) {
		$tag->setHtml(mb_substr($this->text, $start, $end - $start + 1), $start, $end);
	}

	return $tag->has('end') ? $tag : null;
}


/**
 * Remove all whitespace from html. Example:
 *
 * [ <a href="...">Home</a>  |  <a href="..."> Somewhere </a> ] = [<a href="...">Home</a>|<a href="...">Somewhere</a>]
 */
public static function removeHtmlWhiteSpace(string $html) : string {
	return str_replace([ "^M" ], [ "" ], preg_replace('/\s+(<)|(>)\s+/', '\2\1', $html));
}


/**
 * Remove all attributes from all html tags. Example:
 * 
 * <a href="..." class="..."><i class="..."></i> Text</a> = <a><i></i> Text</a>
 */
public static function removeHtmlAttributes(string $html) : string {
	return preg_replace('/'.chr(60).'([a-zA-Z]+).*?'.chr(62).'/', '<\1>', $html);
}


/**
 * Remove all tags from $html. If allow = '<p><a>' then <p> and <a> tags are kept.
 */
public static function removeHtmlTags(string $html, string $allow = '') : string {
	$html = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
	$html = strip_tags($html, $allow);
	return $html;
}


/**
 * Convert string or array to url string. Join $url array with '-'. Apply strtolower.
 * Replace umlaute (Ä = Ae) and slash (/ = -). Remove special character ([^a-zA-Z0-9_,\.\-]).
 * Keep / and abort if % is found.
 *
 * @param string|array $url
 */
public static function url($url) : string {
	if (is_array($url)) {
		for ($i = 0; $i < count($url); $i++) {
			$url[$i] = trim($url[$i]);
		}

		$res = join('-', $url);
	}
	else {
		$res = trim($url);
	} 
	
	if (strpos($res, '%') !== false) {
		throw new Exception('string is urlencoded', $res);
	}

	$res = str_replace([ " ", "\t", "\r", "\n" ], '-', $res);
	$res = str_replace([ 'ö', 'ä', 'ü', 'ß', 'Ä', 'Ö', 'Ü' ], [ 'oe', 'ae', 'ue', 'ss', 'Ae', 'Oe', 'Ue' ], $res);
	$res = preg_replace('/[^a-zA-Z0-9_,\.\-\/]/', '', $res);
	$res = preg_replace('/\-+/', '-', $res);
	return strtolower($res);
}


}

