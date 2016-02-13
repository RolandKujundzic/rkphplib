<?php

require_once(dirname(dirname(__DIR__)).'/src/CryptoHelper.class.php');


// $method = [ 'xor', 'ord', 'aes256cbc', 'mcrypt_r256' ];
$method = [ 'xor', 'ord', 'mcrypt_r256' ];
$wrapper = [ 'no', 'urlenc_base64', 'safe64' ]; 

$txt = 'This is a secret.';
$secret = 'no one must know';

print "\n\nEn/Decode [$txt] with secret [$secret]:\n\n";

foreach ($method as $m) {
	foreach ($wrapper as $w) {
		$ch = new rkphplib\CryptoHelper(array('secret' => $secret, 'enc' => $m, 'wrap' => $w));
		$enc = $ch->encode($txt);
		print "encode: $w($m(...)) = [$enc]\n";
		$dec = $ch->decode($enc);
		print "decode: $m($w(...)) = [$dec]\n\n";

		if ($dec !== $txt) {
			print "ERROR: en/decode failed!\n\n";
			exit(1);
		}
	}
}

