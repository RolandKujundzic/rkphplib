<?php

namespace rkphplib;

require_once dirname(__DIR__).'/other/PHPMailer/Exception.php';
require_once dirname(__DIR__).'/other/PHPMailer/PHPMailer.php';

require_once __DIR__.'/Dir.class.php';
require_once __DIR__.'/lib/resolvPath.php';


use PHPMailer\PHPMailer\PHPMailer;



/**
 * Mailer.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Mailer {

/** @var string $always_to force always same recpient */
public static $always_to = '';

/** @var string $always_from force always same sender */
public static $always_from = '';

/** @var array|string $smtp smtp-host (string) or smtp-configuration (array) */
public static $smtp = null;

/** @var PHPMailer $_mailer */
private $_mailer = null;

/** @var array $type_email count doubles. Keys: to|cc|bcc|ReplyTo|from|recipient */
private $type_email = [ 'recipient' => [] ];



/**
 * Use PHPMailer with exceptions in utf-8 mode.
 */
public function __construct() {
  $this->_mailer = new PHPMailer(true);
	$this->_mailer->CharSet = 'utf-8';
}


/**
 * Change PHPMailer parameter. Parameter List:
 *
 *	- CharSet: utf-8 (=default) | iso-8859-1
 *  - Priority: null (=default) | 1 (= High) | 3 (= Normal) | 5 (= low)
 *  - Encoding: 8bit (= default) | 7bit | binary | base64 | quoted-printable
 *  - Hostname: empty = default = auto-detect (= try: $_SERVER['SERVER_NAME'], gethostname(), php_uname('n') or 'localhost.localdomain')
 * @param string $value
 */
public function setMailer($key, $value) {
	$allow = [ 'CharSet', 'Priority', 'Encoding', 'Hostname' ];

	if (!in_array($key, $allow)) {
		throw new Exception("Invalid Mailer parameter [$key]");
	}

	$this->_mailer->$key = $value;
}


/**
 * Return PHPMailer parameter. 
 */
public function getMailer($key) {
	$allow = [ 'CharSet', 'Priority', 'Encoding', 'Hostname' ];
	if (!in_array($key, $allow)) {
		throw new Exception("Invalid Mailer parameter [$key]");
	}

	return $this->_mailer->$key;
}


/**
 * Return array of attachments.
 */
public function getAttachments() {
	return $this->_mailer->getAttachments();
}


/**
 * Return last message id.
 */
public function getLastMessageID() {
	return $this->_mailer->getLastMessageID();
}


/**
 * Return encoded string. Encoding is base64, 7bit, 8bit, binary or 'quoted-printable.
 */
public function encodeString($str, $encoding = 'base64') {
	return $this->_mailer->encodeString($str, $encoding);
}


/**
 * Return true if email is valid (or throw error).
 */
public static function isValidEmail($email, $throw_error = false) {
	$res = true;

	if (empty($email) || mb_strpos($email, '@') == false) {
		$res = false;
	}
	else {
		$res = preg_match('/^(?:[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+\.)*[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+@(?:(?:(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!\.)){0,61}[a-zA-Z0-9_-]?\.)+[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!$)){0,61}[a-zA-Z0-9_]?)|(?:\[(?:(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\]))$/', $email);
	}

	if (!$res && $throw_error) {
		throw new Exception("Invalid email", $email);
	}

	return $res;
}


/**
 * Embed Image.
 */
public function embedImage($file, $mime = 'application/octet-stream', $encoding = 'base64') {
	if (!$this->_mailer->addEmbeddedImage($file, md5($file), basename($file), $encoding, $mime, 'inline')) {
		throw new Exception('failed to add embedded image '.$file);
	}
}


/**
 * Add attachment to mail (base64 encoding). Default mime type is application/octet-stream, empty means auto_detect.
 */
public function attach($file, $mime = 'application/octet-stream') {
	if (empty($mime)) {
		$mime = File::mime($file);
	}

	if (empty($mime)) {
		$mime = 'application/octet-stream';
	}

	if (!$this->_mailer->AddAttachment($file, basename($file), 'base64', $mime)) {
		throw new Exception('failed to add attachment '.$file);
	}
}


/**
 * Add Custom Header (key:value).
 */
public function setHeader($key, $value) {
  $this->_mailer->AddCustomHeader($key.':'.$value);
}


/**
 * Send mail via SMTP. Convert string to [ 'host' => parameter ]. 
 * Parameter is string (=host) or hash. SMTP Parameter are:
 * 
 * host= required
 * port= 25 (optional) 
 * secure= '' (=default), ssl, tls
 * auto_tls= false (always enable tls)
 * auth= CRAM-MD5, PLAIN, LOGIN, XOAUTH2 (optional)
 * user= (optional)
 * pass= (optional)
 * persist= false (optional) 
 * hostname=
 */
public function useSMTP($smtp) {
	require_once dirname(__DIR__).'/other/PHPMailer/SMTP.php';

	if (is_string($smtp) && !empty($smtp)) {
		$smtp = [ 'host' => $smtp ];
	}

  if (count($smtp) == 0 || empty($smtp['host'])) {
    return;
  }

	if (getHostByName(getHostName())== getHostByName($smtp['host'])) {
		// smtp.host = localhost: use mail method
		$this->_mail->IsMail();
		return;
	}

	$this->_mailer->isSMTP();
  $this->_mailer->Host = $smtp['host'];

  if (isset($smtp['hostname'])) {
    $this->_mailer->Hostname = $smtp['hostname'];
  }
	else if (!empty($this->type_email['from']) && empty($this->_mail->Hostname) && empty($_SERVER['SERVER_NAME'])) {
		// don't use localhost.localdomain
		list ($email_from_name, $email_from_host) = explode('@', $this->type_email['from'], 2);
		$this->_mail->Hostname = mb_strtolower($email_from_host);
	}

	if (isset($smtp['secure']) && in_array($smtp['secure'], [ '', 'ssl', 'tls' ])) {
		$this->_mailer->SMTPSecure = $smtp['secure'];
	}

	if (isset($smtp['auto_tls'])) {
		$this->_mailer->SMTPAutoTLS = $smtp['auto_tls'];
	}
	else {
		// assume we have a mailer with non valid tls certificate
		$this->_mailer->SMTPAutoTLS = false;
	}

	if (isset($smtp['persist'])) {
	  $this->_mailer->SMTPKeepAlive = $smtp['persist'];
	}

	if (!empty($smpt['port'])) {
		$this->_mailer->Port = $smtp['port'];
	}

  if (!empty($smtp['user']) && isset($smtp['pass'])) {
    $this->_mailer->SMTPAuth = true;
    $this->_mailer->Username = $smtp['user'];
    $this->_mailer->Password = $smtp['pass'];

		if (!empty($smtp['auth'])) {
			$this->_mailer->AuthType = $smtp['auth'];
		}
  }
  else {
    $this->_mailer->SMTPAuth = false;
  }
}


/**
 * Set sender (required). Use Mailer::$always_from for global from change.
 */
public function setFrom($email, $name = '', $sender = '') {
  list ($email, $name) = $this->_email_name($email, $name);

  if (!empty(self::$always_from)) {
		self::isValidEmail($always_from, true);
    $email = self::$always_from;
  }

	$this->type_email['from'] = mb_strtolower($email);
	$this->_mailer->setFrom($email, $name, empty($sender));

	if (!empty($sender)) {
		self::isValidEmail($sender, true);
		$this->_mailer->Sender = $sender;
	}
}


/**
 * Set Recipient Adress (required). Use Mailer::$always_to = ... for global recipient redirection.
 */
public function setTo($email, $name = '') {

  if (!empty(self::$always_to)) {
		self::isValidEmail(self::$always_to, true);
    $email = self::$always_to;
  }

  $this->_add_address('to', $email, $name);
}


/**
 * Set Cc Adress. If called multiple times address list will be used.
 */
public function setCc($email, $name = '') {

	if (!empty(self::$always_to)) {
		return;
	}

	$this->_add_address('cc', $email, $name);
}


/**
 * Set Bcc Adress. If called multiple times address list will be used.
 */
public function setBcc($email, $name = '') {

	if (!empty(self::$always_to)) {
		return;
	}

	$this->_add_address('bcc', $email, $name);
}


/**
 * Set ReplyTo Adress. If called multiple times address list will be used.
 * From is added automatically as ReplyTo - an error will occure if you add it manually.
 * Default is Sender Adress.
 */
public function setReplyTo($email, $name = '') {
	$this->_add_address('ReplyTo', $email, $name);
}


/**
 * Set Subject. UTF-8 characters are allowed.
 */
public function setSubject($txt) {
	if ($txt != quoted_printable_encode($txt)) {
		$this->_mailer->Subject = '=?utf-8?B?'.base64_encode($txt).'?=';
	}
	else {
		$this->_mailer->Subject = $txt;
	}
}


/**
 * Set Text Body if $txt is not empty.
 */
public function setTxtBody($txt) {
  if (!empty($txt)) {
    $this->_mailer->IsHTML(false);
    $this->_mailer->Body = $txt;
  }
}


/**
 * Set HTML Mail Body (if $html is not empty). 
 * Relative image URLs and backgrounds will be converted into inline images.
 * If basedir is set path for images will be relative to basedir. 
 * Alt Text Body is automatically created. 
 */
public function setHtmlBody($html, $basedir = '') {
  if (!empty($html)) {
    $this->_mailer->MsgHTML($html, $basedir);
  }
}


/**
 * Check email and name. Split "Username" <user@example.com> into email and name. Return (email, name).
 */
private function _email_name($email, $name) {

  if (!empty($name)) {
    if (mb_strpos($name, ',') !== false || mb_strpos($name, '"') !== false) {
			throw new Exception("Invalid name", "[$name] contains comma or quote");
    }
  }
  else {
    if (preg_match('/^"(.+?)" \<(.+?)\>$/', $email, $match)) {
      // return split values
      $name = $match[1];
      $email = $match[2];
    }
  }

	self::isValidEmail($email, true);

	return array($email, $name);
}


/**
 * Add address. Use $type in to|cc|bcc|ReplyTo.
 */
private function _add_address($type, $email, $name) {
	$email = trim($email);
	$name = trim($name);

	if (empty($name) && strpos($email, ',')) {
		$email_name_list = array();

		$email_list = explode(',', $email);
		foreach ($email_list as $email) {
			$email = trim($email);
			$email_name_list[$email] = '';
		}
	}
	else if (!empty($email)) {
		$email_name_list = array($email => $name);
	}
	else {
		throw new Exception("Empty email", "name=[$name] type=[$type]");
  }

	if (!isset($this->type_email[$type])) {
		$this->type_email[$type] = [];
	}

	foreach ($email_name_list as $ex => $nx) {
		list ($email, $name) = $this->_email_name($ex, $nx);

		$lc_email = mb_strtolower($email);

		if (isset($this->type_email[$type]) && in_array($lc_email, $this->type_email[$type])) {
			// don't add same email to same type twice
			continue;
		}

		if ($type != 'ReplyTo') {
			if (in_array($lc_email, $this->type_email['recipient'])) {
				// don't add same email twice
				continue;
			}
			else {
				array_push($this->type_email['recipient'], $lc_email);
			}
		}

		if ($type == 'to') {
			$ok = $this->_mailer->AddAddress($email, $name);
		}
		else if ($type == 'cc') {
			$ok = $this->_mailer->AddCC($email, $name);
		}
		else if ($type == 'bcc') {
			$ok = $this->_mailer->AddBCC($email, $name);
		}
		else if ($type == 'ReplyTo') {
			$ok = $this->_mailer->AddReplyTo($email, $name);
		}
		else {
			throw new Exception("invalid type [$type]");
		}

		if (!$ok) {
			throw new Exception("invalid email [$email]", "type=[$type] name=[$name]");
		}

		array_push($this->type_email[$type], $lc_email);
	}
}


/**
 * Save mail in dir.
 */
private function saveMail($dir) {
	Dir::create($dir, 0, true);
	throw new Exception('ToDo ...');
}


/**
 * Send mail. Options: 
 *
 * - send: true
 * - save: false
 * - save_dir: data/mail/$date(Ym)/$date(dH)/$map(id)
 */
public function send($options = []) {

	if (!isset($this->type_email['to']) || count($this->type_email['to']) == 0) {
		throw new Exception('call setTo() first');
	}

	if (!is_null(self::$smtp)) {
		$this->useSMTP(self::$smtp);
	}

	$default = [ 'send' => true, 'save' => false, 'save_dir' => 'data/mail/$date(Ym)/$date(dH)/$map(id)' ];
	$options = array_merge($default, $options);

	if (!$this->_mailer->preSend()) {
		throw new Exception('Mailer preSend failed');
	}

	if ($options['save']) {
		$options['save_dir'] = \rkphplib\lib\resolvPath($options['save_dir'], [ 'id' => $this->_mailer->getLastMessageId() ]);
		$this->saveMail($options['save_dir']);
	}

	if (!$options['send']) {
		return;
	}

	if (!$this->_mailer->postSend()) {
		throw new Exception('Mailer postSend failed');
	}
}


}
