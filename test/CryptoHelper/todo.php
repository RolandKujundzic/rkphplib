<?php

require_once(dirname(dirname(__DIR__)).'/src/CryptoHelper.class.php');


/**
 *
 */
function _enc_dec($opt) {
	$ox = empty($opt['xor_secret']) ? '' : ' obfuscated';

	foreach ($opt['method'] as $m) {
		foreach ($opt['wrapper'] as $w) {
			$opt['wrap'] = $w;
			$opt['enc'] = $m;

			$ch = new rkphplib\CryptoHelper($opt);
			$enc = $ch->encode($opt['txt']);
			print "encode$ox: $w($m(...)) = [$enc]\n";
			$dec = $ch->decode($enc);
			print "decode$ox: $m($w(...)) = [$dec]\n\n";

			if ($dec !== $opt['txt']) {
				print "ERROR: en/decode failed!\n\n";
				exit(1);
			}
		}
	}
}


$opt = array();
$opt['method'] = [ 'xor', 'ord' ]; 
$opt['wrapper'] = [ 'no', 'urlenc_base64', 'safe64' ]; 
$opt['txt'] = 'This is a secret.';
$opt['secret'] = 'no one must know';
_enc_dec($opt);

$opt['xor_secret'] = 'aJE13Lm';
_enc_dec($opt);

$opt['method'] = [ 'aes256cbc' ];
$opt['xor_secret'] = '';
_enc_dec($opt);

$opt['xor_secret'] = 'aJE13Lm';
_enc_dec($opt);

$opt['method'] = [ 'mcrypt_r256' ];
$opt['xor_secret'] = '';
_enc_dec($opt);

$opt['xor_secret'] = 'aJE13Lm';
_enc_dec($opt);
