<?php

namespace rkphplib;

require_once __DIR__.'/Exception.php';


/**
 * Net access wrapper.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Net {


/**
 * Check if remote file exists.
 */
public static function fileExists(string $path) : bool {
	return @fopen($path, 'r') == true;
}


}
