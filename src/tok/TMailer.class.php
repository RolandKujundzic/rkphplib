<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/TokHelper.trait.php';
require_once __DIR__.'/../Mailer.class.php';

use rkphplib\Exception;
use rkphplib\Mailer;


/**
 * Mailer plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2018-2020 Roland Kujundzic 
 */
class TMailer implements TokPlugin {
use TokHelper;

// @var Mailer $mail = null
protected $mail = null; 

// @var map $conf = null
protected $conf = null;



/**
 * Return {mail:init|html|txt|send|attach}
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [];
	$plugin['mail:init'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['mail:html'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['mail:txt'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['mail:send'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['mail:attach'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['mail'] = 0;

	return $plugin;
}


/**
 * Initialize Mailer. If to is empty do nothing.
 * Use constants SETTINGS_SMTP_* and SETTINGS_MAIL_*.
 *
 * @tok … 
 * {mail:init}
 * subject= Subject|#| 
 * from= sender@domain.tld|#|
 * from.name= Peter Parker|#|
 * from.sender= |#|
 * to= recipient@domain.tld|#|
 * to.name= Jonah Jamson|#|
 * cc= carbon.copy@domain.tld|#|
 * cc.name= |#|
 * bcc= blind.carbon.copy@domain.tld|#|
 * bcc.name= |#|
 * reply_to= |#|
 * reply_to.name= |#|
 * basedir= |#|
 * smtp.host= mail.smtp-server.tld|#|
 * smtp.port= |#|
 * smtp.secure= |#|
 * smtp.auto_tls= |#|
 * smtp.auth= |#|
 * smtp.user= |#|
 * smtp.pass= |#|
 * smtp.persist= |#|
 * smtp.hostname= |#|
 * header.something= |#| (you may add multiple headers)|#|
 * send= 1|#|
 * save= 0|#|
 * save_dir= |#|
 * {:mail}
 * @EOL
 *
 * @see Mailer
 */
public function tok_mail_init(array $conf) : void {
	$this->conf = $conf;

	if (empty($conf['to'])) {
		return;
	}

	$this->mail = new Mailer();
	
	if (!empty($conf['subject'])) {
		$this->mail->setSubject($conf['subject']); 
	}

	$map = [ 
		'from' => [ 'setFrom', 'from.name', 'from.sender' ],
		'to' => [ 'setTo', 'to.name' ], 
		'cc' => [ 'setCc', 'cc.name' ],
		'bcc' => [ 'setBcc', 'bcc.name' ],
		'reply_to' => [ 'setReplyTo', 'reply_to.name' ]
	];

	foreach ($map as $key => $info) {
		if (empty($conf[$key])) {
			continue;
		}

		$func = $info[0];
		$param_1 = $conf[$key];

		if (count($info) == 1) {
			$this->mail->$func($param_1);
		}
		else if (count($info) == 2) {
			$param_2 = isset($conf[$info[1]]) ? $conf[$info[1]] : '';
			$this->mail->$func($param_1, $param_2);
		}
		else if (count($info) == 3) {
			$param_2 = isset($conf[$info[1]]) ? $conf[$info[1]] : '';
			$param_3 = isset($conf[$info[2]]) ? $conf[$info[2]] : '';
			$this->mail->$func($param_1, $param_2, $param_3);
		}
	}

	$this->setSMTP();

	$header = $this->getMapKeys('header', $conf);
	if (is_array($header)) {
		foreach ($header as $key => $value) {
			$this->mail->setHeader($key, $value);
		}
	}
}


/**
 * Configure smtp if this.conf[smtp.*] is set.
 */
private function setSMTP() : void {
	if (empty($this->conf['smtp.host'])) {
		return;
	}

	$smtp = [];
	$keys = [ 'host', 'port', 'user', 'pass', 'auto_tls',
		'auth', 'secure', 'persist', 'hostname' ];

	foreach ($keys as $key) {
		if (isset($this->conf['smtp.'.$key])) {
			$smtp[$key] = $this->conf['smtp.'.$key];
		}
	}

	if (count($smtp) == 1) {
		$this->mail->useSMTP($this->conf['smtp.host']);
	}
	else {
		$this->mail->useSMTP($smtp);
	}
}


/**
 * Set html mail body. Prepend [<!DOCTYPE html><html> ... </head><body>] and append [</body></html>] 
 * if body has no [<html ] and [</html].
 *
 * @tok {mail:html}HTML BODY{:mail} 
 *
 * @see Mailer.setHtmlBody
 * @param string $body
 * @return ''
 */
public function tok_mail_html($body) : void {
	if (is_null($this->mail)) {
		return;
	}

	if (mb_stripos($body, '<html ') === false && mb_stripos($body, '</html>') === false) {
		$body = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\" />\n</head>\n<body>\n".
			$body."\n</body>\n</html>\n";
	}

	$basedir = isset($this->conf['basedir']) ? $this->conf['basedir'] : '';
	$this->mail->setHtmlBody($body, $basedir);
}


/**
 * Set text mail body.
 * 
 * @tok {mail:txt}TEXT BODY{:mail}
 *
 * @see Mailer.setTxtBody
 * @param string $body
 * @return ''
 */
public function tok_mail_txt($body) : void {
	if (!is_null($this->mail)) {
		$this->mail->setTxtBody($body);
	}
}


/**
 * Attach file to mail.
 *
 * @tok {mail:attach}HTML BODY{:mail} 
 *
 * @see Mailer.attach
 * @param map $p { file: '...', mime: 'application/octet-stream' }
 * @return ''
 */
public function tok_mail_attach($p) {
	if (!is_null($this->mail)) {
		$mime = isset($p['mime']) ? $p['mime'] : 'application/octet-stream';
		$this->mail->attach($p['file'], $mime);
	}
}


/**
 * Send mail. Return message id. Set conf.send|save boolean (=1|0|'')
 * and conf.save_dir (='') if necessary.
 * 
 * @see Mailer.send
 * @return string 
 */
public function tok_mail_send() {
	if (is_null($this->mail)) {
		return;
	}

	$opt = [];

	if (isset($this->conf['send'])) {
		$opt['save'] = !empty($this->conf['save']);
	}

	if (isset($this->conf['save'])) {
		$opt['save'] = !empty($this->conf['save']);
	}

	if (!empty($this->conf['save_dir'])) {
		$opt['save_dir'] = $this->conf['save_dir'];			
	}

	$this->mail->send($opt);
	return $this->mail->getLastMessageID();
}


}

