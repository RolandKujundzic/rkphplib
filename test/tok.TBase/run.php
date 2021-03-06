<?php

require_once '../settings.php';

function _de_en_codeHash($map) {
	global $th;

	$enc = TBase::encodeHash($map);
	$dec = TBase::decodeHash($enc);
	$th->compare('(de|en)codeHash', [ \rkphplib\lib\kv2conf($dec) ], [ \rkphplib\lib\kv2conf($map) ]);
}

// $th->tokCheck(PATH_SRC.'tok/TBase.php'); exit(0);

global $th;

$th->useTokPlugin([ 'TBase' ]);
$th->run(1, 18);

/*
_de_en_codeHash([ 'dir' => 'company/contact', 'id' => 3872 ]);
_de_en_codeHash([ "txt" => "Is it working properly?", "y" => rand(0,100000) ]);
_de_en_codeHash([ "txt" => "Der schnelle braune Fuchs sprang über den trägen Hund", "y" => rand(0,100000) ]);
*/
