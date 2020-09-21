<?php

global $th;

if (!isset($th)) {
	require_once dirname(dirname(__DIR__)).'/src/TestHelper.class.php';
	$th = new rkphplib\TestHelper();
}

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


// $th->tokCheck(PATH_RKPHPLIB.'tok/TBase.class.php'); exit(0);

$th->runTokenizer(15, array('TBase'));

_de_en_codeHash([ 'dir' => 'company/contact', 'id' => 3872 ]);
_de_en_codeHash([ "txt" => "Is it working properly?", "y" => rand(0,100000) ]);
_de_en_codeHash([ "txt" => "Der schnelle braune Fuchs sprang über den trägen Hund", "y" => rand(0,100000) ]);

