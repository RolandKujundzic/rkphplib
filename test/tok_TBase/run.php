<?php

global $th;

defined('DOCROOT') || define('DOCROOT', dirname(dirname(__DIR__)));
defined('PATH_RKPHPLIB') || define('PATH_RKPHPLIB', DOCROOT.'/src/');

if (!isset($th)) {
	require_once PATH_RKPHPLIB.'lib/log_debug.php';
	require_once PATH_RKPHPLIB.'TestHelper.class.php';
	$th = new rkphplib\TestHelper();
}

$th->runTokenizer(15, array('TBase'));

require_once PATH_RKPHPLIB.'/tok/TBase.class.php';
require_once PATH_RKPHPLIB.'/lib/kv2conf.php';

use \rkphplib\tok\TBase;


/** 
 *
 */
function _de_en_codeHash($map) {
	global $th;

	$enc = TBase::encodeHash($map);
	$dec = TBase::decodeHash($enc);
	$th->compare('(de|en)codeHash', [ \rkphplib\lib\kv2conf($dec) ], [ \rkphplib\lib\kv2conf($map) ]);
}


$th->tokCheck(PATH_RKPHPLIB.'tok/TBase.class.php');

_de_en_codeHash([ 'dir' => 'company/contact', 'id' => 3872 ]);
_de_en_codeHash([ "txt" => "Is it working properly?", "y" => rand(0,100000) ]);
_de_en_codeHash([ "txt" => "Der schnelle braune Fuchs sprang über den trägen Hund", "y" => rand(0,100000) ]);

