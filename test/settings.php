<?php

define('PATH_RKPHPLIB', dirname(__DIR__).'/src/');
define('SETTINGS_LOG_ERROR', __DIR__.'/php.fatal');

define('SETTINGS_XCRYPT_SECRET', 'abc123'); 
define('SETTINGS_XCRYPT_RKEY', 'xcr');

if (file_exists($_SERVER['HOME'].'/.config/rkphplib.settings.php')) {
	require_once $_SERVER['HOME'].'/.config/rkphplib.settings.php';
}

require_once PATH_RKPHPLIB.'TestHelper.class.php';

$th = new rkphplib\TestHelper();

