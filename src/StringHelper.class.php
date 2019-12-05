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

// @var ?string $html
private $html = null;



/**
 * Set html text.
 */
public function setHtml(string $html) : void {
	$this->html = $html;
}


/**
 * Update $tag.start and $tag.end.
 */
public function nextTag(HtmlTag &$tag) : bool {
	if ($this->html === null) {
		throw new Exception('call setHtml() first');
	}

	$len = mb_strlen($tag->name) + 1;
	$tag->html = '';

	$offset = ($tag->end === false) ? 0 : $tag->end + 1;
	$tag->start = mb_stripos($this->html, '<'.$tag->name, $offset);

	if ($tag->start !== false && ($next_char = mb_substr($this->html, $tag->start + $len, 1)) && 
			in_array($next_char, [ "\r", "\n", "\t", " " ])) {
		$tag->end = mb_strpos($this->html, '>', $tag->start + $len + 1);

		if ($tag->end !== false) {
			$tag->setHtml(mb_substr($this->html, $tag->start, $tag->end - $tag->start + 1));
		}
	}

	return $tag->html !== '';
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
	return preg_replace('/<([a-zA-Z]+).*?>/', '<\1>', $html);
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
	
	$res = str_replace([ " ", "\t", "\r", "\n" ], '-', $res);
	$res = str_replace([ 'ö', 'ä', 'ü', 'ß', 'Ä', 'Ö', 'Ü', '/' ], [ 'oe', 'ae', 'ue', 'ss', 'Ae', 'Oe', 'Ue', '-' ], $res);
	$res = preg_replace('/[^a-zA-Z0-9_,\.\-]/', '', $res);
	$res = preg_replace('/\-+/', '-', $res);
	return strtolower($res);
}


}

