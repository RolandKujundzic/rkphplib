<?php

define('DOCROOT', dirname(__DIR__).'/tmp');
define('PATH_SRC', dirname(__DIR__).'/src/');
define('SETTINGS_LOG_ERROR', __DIR__.'/php.fatal');
define('SETTINGS_XCRYPT_SECRET', 'abc123'); 
define('SETTINGS_XCRYPT_RKEY', 'xcr');

if (file_exists($_SERVER['HOME'].'/.config/rkphplib.settings.php')) {
	require_once $_SERVER['HOME'].'/.config/rkphplib.settings.php';
}

require_once PATH_SRC.'TestHelper.php';

$th = new rkphplib\TestHelper();

