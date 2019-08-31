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
 *
 * @param string $path
 * @return boolean
 */
public static function fileExists($path) {
	return @fopen($path, 'r') == true;
}


}
