<?php

namespace rkphplib\lib;

require_once dirname(__DIR__).'/Exception.class.php';

use rkphplib\Exception;



/**
 * Replace {:=key} in $text with $hash.key value. Replace {:=key.subkey} with $hash.key.subkey value.
 * Use "(array)$obj" to convert object into $hash. Use 'prefix' as shortcut for ['{:=', '}', 'prefix' ] ({:=prefix.tag} replace).
 * Default $conf = [ '{:=', '}', '' ].

 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function replace_tags(string $text, array $hash, array $conf = [ TAG_PREFIX, TAG_SUFFIX, '' ]) : string {

	if (is_string($conf)) {
		$conf = [ TAG_PREFIX, TAG_SUFFIX, $conf ];
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
