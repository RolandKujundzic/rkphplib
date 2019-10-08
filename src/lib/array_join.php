<?php
  
namespace rkphplib\lib;

require_once dirname(__DIR__).'/Exception.class.php';

use rkphplib\Exception;


/**
 * Join array $parts with $delimiter. Espace $delimiter in $parts with backslash.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
function array_join(string $delimiter, array $parts) : string {
	for ($i = 0; $i < count($parts); $i++) {
		if (strpos($parts[$i], $delimiter) !== false) {
			$parts[$i] = str_replace($delimiter, '\\'.$delimiter, $parts[$i]); 
		}
	}

	return join($delimiter, $parts);
}

