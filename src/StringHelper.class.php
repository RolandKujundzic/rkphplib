<?php

namespace rkphplib;


/**
 * String manipulation.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class StringHelper {

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
	$res = is_array($url) ? join('-', $url) : $url;
  $res = str_replace([ 'ö', 'ä', 'ü', 'ß', 'Ä', 'Ö', 'Ü', '/' ], [ 'oe', 'ae', 'ue', 'ss', 'Ae', 'Oe', 'Ue', '-' ], join('-', $url));
  $res = preg_replace('/[^a-zA-Z0-9_,\.\-]/', '', $res);
  return strtolower($res);
}


}

