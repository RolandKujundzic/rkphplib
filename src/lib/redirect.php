<?php

namespace rkphplib\lib;

require_once(dirname(__DIR__).'/Exception.class.php');

use rkphplib\Exception;


/**
 * Send redirect header and exit. If $p['@link'] use {link:}@=$url{:link}. 
 * If $p['@back']=1 append parameter back=parent/dir. If url matches
 * /^[a-z0-9_\-\/]+$/i assume {link:}_=$url{:link}. Append extra parameter $p to 
 * redirect url.
 */
function redirect(string $url, array $p = []) : void {
	// \rkphplib\lib\log_debug('redirect("'.$url.'", ...): _ld?'.!empty($_REQUEST['_ld']).' p: '.print_r($p, true));

	if (preg_match('/^[a-z0-9_\-\/]+$/i', $url)) {
		// assume {link:}_=$url{:link}
		$url = \rkphplib\tok\Tokenizer::$site->callPlugin('link', 'tok_link', [ [], [ '_' => $url ] ]);
	}
	
	// avoid [index.php?dir=xxx] redirect loop
	$md5 = md5($url);
	if (!empty($_REQUEST['_ld']) && $_REQUEST['_ld'] === $md5) {
		throw new Exception('redirect loop', $url);
	}

	if (!empty($p['@back']) && !empty($_REQUEST[SETTINGS_REQ_DIR])) {
		$p['back'] = dirname($_REQUEST[SETTINGS_REQ_DIR]);
		unset($p['@back']);
	}

	if (!empty($p['@link'])) {
		require_once(dirname(__DIR__).'/tok/Tokenizer.class.php');

		unset($p['@link']);
		$p['@'] = $url;
		$p['_ld'] = $md5;

		$url = \rkphplib\tok\Tokenizer::$site->callPlugin('link', 'tok_link', [ [], $p ]);
	}
	else {
		// append parameter _ld for loop detection
		$url .= mb_strpos($url, '?') ? '&_ld='.$md5 : '?_ld='.$md5;

		// append optional parameter
		foreach ($p as $key => $value) {
			$url .= '&'.$key.'='.rawurlencode($value);
		}
	}

	// header('P3P: CP="CAO PSA OUR"'); // IE will not accept Frameset SESSIONS without this header 
	// \rkphplib\lib\log_debug('exit redirect: Location '.$url);
	session_write_close(); // avoid redirect delay 
	header('Location: '.$url);
	exit();
}

