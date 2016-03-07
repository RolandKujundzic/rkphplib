<?php

namespace rkphplib\lib;


/**
 * Replace {:=key} in $text with $map.key value.
 * Replace {:=key.subkey} with $map.key.subkey value.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @param string $text
 * @param map $map
 * @param array $conf (default = [ '{:=', '}', '' ])
 * @return string
 */
function replace_tags($text, $map, $conf = array('{:=', '}', '')) {
	foreach ($map as $key => $value) {
    if (is_array($value)) {
			$sub_conf = $conf;
			$sub_conf[2] = empty($conf[2]) ? $key : $conf[2].'.'.$key;
			$text = replace_tags($text, $value, $sub_conf);
		}
		else {
			$prefix = empty($conf[2]) ? '' : $conf[2].'.';
			$text = str_replace($conf[0].$prefix.$key.$conf[1], $value, $text);
		}
	}

	return $text;
}
