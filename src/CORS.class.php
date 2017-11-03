<?php

namespace rkphplib;


/**
 * Send CORS header. Example: 
 *
 * \rkphplib\CORS::allowAll();
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class CORS {


/**
 * Return yaml file converted to php multi-map.
 *
 * @param string $file
 * @return multi-map
 */
public static function allowAll($methods = 'GET,POST,PUT,DELETE') {
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Headers: *');
	header('Access-Control-Allow-Methods: '.$methods);
	header('Access-Control-Max-Age: 600');  // max. 60 * 10 sec = 10 min cachable
}


}
