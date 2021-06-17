<?php

namespace rkphplib;


/**
 * JSON Memory session handler. Use 16 digit base 62 number as session id. 
 *  
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @phpVersionLt 7.0 class DatabaseSessionHandler implements \SessionHandlerInterface {
 */
abstract class JSONSessionHandler implements \SessionHandlerInterface, \SessionIdInterface, \SessionUpdateTimestampHandlerInterface {

	// @see https://github.com/lboynton/memcached-json-session-save-handler
	// @see https://code.google.com/archive/p/php-msgpack/

}

