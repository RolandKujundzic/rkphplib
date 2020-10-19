<?php

if (!defined('SETTINGS_SMTP_PASS') || empty(SETTINGS_SMTP_PASS) || !defined('TEST_MAIL_TO') || empty(TEST_MAIL_TO)) {
	print 'SKIP_TEST';
	return;
}

$mailer = new \rkphplib\Mailer();
$mailer->testMail(TEST_MAIL_TO);
