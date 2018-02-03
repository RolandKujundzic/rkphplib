<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/TokHelper.trait.php');
require_once($parent_dir.'/Mailer.class.php');

use \rkphplib\Exception;
use \rkphplib\Mailer;



/**
 * Mailer plugin. Wrapper for Mailer.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TMailer implements TokPlugin {
use TokHelper;


/** @var Tokenizer $tok = null */
protected $tok = null;

/** @var Mailer $mail = null */
protected $mail = null; 

/** @var map $conf = null */
protected $conf = null;



/**
 *
 */
public function getPlugins($tok) {
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
 * Initialize Mailer. Example:
 *
 * @tok 
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
 * @:
 *
 * @see Mailer.set[From|Subject|To|Cc|Bcc|ReplyTo], Mailer.useSMTP and Mailer.setHeader
 * @param map $conf
 * @return ''
 */
public function tok_mail_init($conf) {
	$this->conf = $conf;

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

	$this->mail->useSMTP($conf['smtp.host']);

	/*
	$smtp = $this->getMapKeys('smtp', $conf);
	print "<pre>SMTP: ".print_r($smtp, true)."<br>\nconf: ".print_r($conf, true)."</pre>"; exit(1);
	if (is_array($smtp) && count($smtp) > 0) {
		$this->mail->useSMTP($smtp);
	}
	else {
		throw new Exception('you must use SMTP [mail:init]smtp.host=...');
	}
	*/

	$header = $this->getMapKeys('header', $conf);
	if (is_array($header)) {
		foreach ($header as $key => $value) {
			$this->mail->setHeader($key, $value);
		}
	}
}


/**
 * Set html mail body.
 *
 * @tok {mail:html}HTML BODY{:mail} 
 *
 * @see Mailer.setHtmlBody
 * @param string $body
 * @return ''
 */
public function tok_mail_html($body) {
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
public function tok_mail_txt($body) {
	$this->mail->setTxtBody($body);
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
	$mime = isset($p['mime']) ? $p['mime'] : 'application/octet-stream';
	$this->mail->attach($p['file'], $mime);
}


/**
 * Send mail. Return message id. Set conf.send|save boolean (=1|0|'')
 * and conf.save_dir (='') if necessary.
 * 
 * @see Mailer.send
 * @return string 
 */
public function tok_mail_send() {
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

