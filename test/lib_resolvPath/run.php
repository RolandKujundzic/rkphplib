<?php

global $th;

if (!isset($th)) {
	require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
	$th = new rkphplib\TestHelper();
}

$th->load('src/lib/resolvPath.php');

$path = 'data/log/ajax/$date(Ym)/$date(dH)/$map(rechnung,email)_$date(is)';
$map = [ 'rechnung' => [ 'email' => 'test@domain.tld' ] ];
$ok = 'data/log/ajax/'.date('Ym').'/'.date('dH').'/'.$map['rechnung']['email'].'_'.date('is');

$out = rkphplib\lib\resolvPath($path, $map);

$th->compare('resovePath('.$path.')', [ $out ], [ $ok ]);
