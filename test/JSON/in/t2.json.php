<?php

require_once '../../src/JSON.class.php';

$test = [
	'rkphplib\JSON::decode',
	[ '" x "', ' x ' ],
  [ '[ 1, 2, 3 ]', '[1,2,3]' ]
];

