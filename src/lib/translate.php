<?php

namespace rkphplib\lib;

require_once __DIR__.'/../File.class.php';

use rkphplib\File;

/**
 * Return translation of $msg. If translation target (@to:xx)
 * is not set or $msg is not translatet return $msg.
 * Use php (return [ 'txt' => 'en', ... ];) or json ({ 'txt': 'en', ... })
 * input file LANGUAGE.php|json.
 * If replace list is set replace $pNx with $plist[N-1].
 * Use @p1x:1 to return '' if $plist[0] == ''.
 *
 * @example translate('@php:path/to/translation');
 * @example translate('@json:load/translation');
 * @example translate('@to:fr')
 *
 * @code translation/de.php …
 * return [ 'min_age' => 'Sie müssen mindestens $p1x Jahre alt sein!', ... ];
 * @EOL
 *
 * @code test.php …
 * define('SETTINGS_TRANSLATIONS', 'php/src/translation');
 * translate('@de');
 * translate('min_age', [ 18 ]) // 'Sie müssen mindestens 18 Jahre alt sein!'
 * @EOL
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2017-2020 Roland Kujundzic
 */
function translate(string $msg, array $plist = []) : string {
	static $translation = [];

	if (!isset($translation['@'])) {
		$translation['@'] = [];
	}

	if ($msg[0] == '@') {
		list ($cmd, $value) = explode(':', $msg);
		$translation[$cmd] = $value;
		return '';
	}

	if (!isset($translation['@to'])) {
		throw new \Exception("call translate('@to:en|fr|...') first");
	}

	$lang = $translation['@to'];

	if (!empty($lang) && !isset($translation[$lang])) {
		if (!empty($translation['@json'])) {
			$translation[$lang] = File::loadJSON($translation['@json']."/$lang.json");
		}
		else if (!empty($translation['@php'])) {
			$translation[$lang] = include $translation['@php']."/$lang.php";
		}
		else {
			throw new \Exception('call translate("@json|php:path/to/translation") first');
		}

		print "load [$lang]: ".print_r($translation, true);
	}
	
	if (isset($translation[$lang][$msg])) {
		$msg = $translation[$lang][$msg];
	}

	for ($i = 0; $i < count($plist); $i++) {
		if ($i == 0 && empty($plist[0]) && !empty($translation['@p1x'])) {
			$msg = '';
		}
		else {
			$msg = str_replace('$p'.($i + 1).'x', $plist[$i], $msg);
		}
	}

	return $msg;
}

