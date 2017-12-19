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
 *
 * @param string $html
 * @return string
 */
public static function removeHtmlWhiteSpace($html) {
	return preg_replace('/\s+(<)|(>)\s+/', '\2\1', $html);
}


/**
 * Remove all attributes from all html tags. Example:
 * 
 * <a href="..." class="..."><i class="..."></i> Text</a> = <a><i></i> Text</a>
 *
 * @param string $html
 * @return string
 */
public static function removeHtmlAttributes($html) {
	return preg_replace('/<([a-zA-Z]+).*?>/', '<\1>', $html);
}


/**
 * Remove all tags from $html. If allow = '<p><a>' then <p> and <a> tags are kept.
 *
 * @param string $html
 * @param string $allow = ''
 * @return string
 */
public static function removeHtmlTags($html, $allow = '') {
	return strip_tags($html);
}


}

