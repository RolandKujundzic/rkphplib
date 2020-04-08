<?php

require_once '../../src/PhpCode.class.php';

use rkphplib\PhpCode;


$php = new PhpCode();
$php->load('../../src/tok/TBase.class.php');

print_r($php->getStatus());
