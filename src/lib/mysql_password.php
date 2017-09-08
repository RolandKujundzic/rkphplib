<?php

namespace rkphplib\lib;


/**
 * Return mysql PASSWORD($cleartext) result.
 *
 * @param string $cleartext
 * @return string
 */
function mysql_password($cleartext) {
	return '*'.strtoupper(sha1(sha1($cleartext, true)));
	// return '*'.strtoupper(hash('sha1', pack('H*', hash('sha1', $cleartext))));
}

