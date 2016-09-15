<?php

namespace rkphplib\lib;

require_once(dirname(__DIR__).'/Exception.class.php');

use rkphplib\Exception;


/**
 * Send redirect header and exit.
 * 
 * @param string $url
 */
function redirect($url) {
	// avoid [index.php?dir=xxx] redirect loop
	if (empty($_REQUEST['_lrl'])) {
		if (strpos($url, '?') !== false) {
			$url .= '&_lrl='.$md5;
		}
	}
	else if ($_REQUEST['_lrl'] == $md5) {
		throw new Exception('redirect loop', $url);
  }

  // header('P3P: CP="CAO PSA OUR"'); // IE will not accept Frameset SESSIONS without this header 
	session_write_close(); // avoid redirect delay 
  header('Location: '.$url);
  exit();
}

