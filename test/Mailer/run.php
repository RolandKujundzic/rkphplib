<?php

require_once '../settings.php';

global $th;

if (defined('SETTINGS_SMTP_HOST')) {
	$th->run(1, 1);
}

