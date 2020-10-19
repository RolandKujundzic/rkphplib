<?php

if (!defined('SETTINGS_SMTP_PASS') || empty(SETTINGS_SMTP_PASS)) {
	print 'SKIP_TEST';
	return;
}

\rkphplib\Mailer::testSMTP();

