<?php
  
namespace rkphplib\lib;

require_once dirname(__DIR__).'/Exception.php';

use rkphplib\Exception;


/**
 * Join array $parts with $delimiter sorted by keys.
 * Espace $delimiter in $parts with backslash.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function array_join(string $delimiter, array $p) : string {
	$res = '';

	$keys = array_keys($p);
	sort($keys);

	for ($i = 0; $i < count($keys); $i++) {
		$key = $keys[$i];

		if ($i > 0) {
			$res .= $delimiter;
		}

		$res .= str_replace($delimiter, '\\'.$delimiter, $p[$key]);
	}

	return $res;
}

