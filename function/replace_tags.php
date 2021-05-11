<?php

/**
 * @function replace_tags
 * Replace {:=key} in $text with $hash.key value. Replace $key.subkey with $hash[key][sub] value.
 * Use "(array)$obj" to convert object into $hash. Default $conf = [ '{:=', '}', '' ] ($conf[2] is prefix). 
 * If $conf[1] == '' reverse sort $hash keys to prevent replace errors.
 */
function replace_tags(string $text, array $hash, array $conf = [ '$', '', '' ]) : string {
	if ($conf[1] === '') {
		krsort($hash);
	}

	foreach ($hash as $key => $value) {
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

