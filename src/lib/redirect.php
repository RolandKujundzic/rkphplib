<?php

namespace rkphplib\lib;

require_once(dirname(__DIR__).'/Exception.class.php');

use rkphplib\Exception;


/**
 * Send redirect header and exit. 
 * 
 * @param string $url
 * @param map<string:string> $p (extra parameter, default = [])
 */
function redirect($url, $p = []) {
	// \rkphplib\lib\log_debug('enter redirect: url='.$url.' _ld?'.!empty($_REQUEST['_ld']));
	
	// avoid [index.php?dir=xxx] redirect loop
	$md5 = md5($url);
	if (!empty($_REQUEST['_ld']) && $_REQUEST['_ld'] === $md5) {
		throw new Exception('redirect loop', $url);
	}

	// append parameter _ld for loop detection
	$url .= mb_strpos($url, '?') ? '&_ld='.$md5 : '?_ld='.$md5;

	// append optional parameter
	foreach ($p as $key => $value) {
		$url .= '&'.$key.'='.rawurlencode($value);
	}

	// header('P3P: CP="CAO PSA OUR"'); // IE will not accept Frameset SESSIONS without this header 
	session_write_close(); // avoid redirect delay 
	header('Location: '.$url);
	exit();
}

