<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';


/**
 * Net access wrapper.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2018 Roland Kujundzic
 *
 */
class Net {


/**
 * Check if remote file exists.
 */
public static function fileExists(string $path) : bool {
	return @fopen($path, 'r') == true;
}


}
