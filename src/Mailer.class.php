<?php

namespace rkphplib;

require_once(dirname(__DIR__).'/other/PHPMailer/Exception.php');
require_once(dirname(__DIR__).'/other/PHPMailer/PHPMailer.php');

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

/** @var array $typ_email count doubles */
private $typ_email = [ 'recipient' => '' ];



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
 *
 * @param string $key
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
 *
 * @see setMailer
 * @param string
 * @return string
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
 *
 * @return array
 */
public function getAttachments() {
	return $this->_mailer->getAttachments();
}


/**
 * Return last message id.
 *
 * @return string
 */
public function getLastMessageID() {
	return $this->_mailer->getLastMessageID();
}


/**
 * Return encoded string. Encoding is base64, 7bit, 8bit, binary or 'quoted-printable.
 *
 * @param string $str
 * @param string encoding $encoding
 */
public function encodeString($str, $encoding = 'base64') {
	return $this->_mailer->encodeString($str, $encoding);
}


/**
 * Return true if email is valid (or throw error).
 * @param string $email
 * @param boolean $throw_error (default = false)
 * @return boolean
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
 *  
 * @throws
 * @param string $file
 * @param string $mime (application/octet-stream)
 * @param string $encoding (base64)
 */
public function embedImage($file, $mime = 'application/octet-stream', $encoding = 'base64') {
	if (!$this->_mailer->addEmbeddedImage($file, md5($file), basename($file), $encoding, $mime, 'inline')) {
		throw new Exception('failed to add embedded image '.$file);
	}
}


/**
 * Add attachment to mail (base64 encoding).
 * 
 * @param string $file 
 * @param string $mime (application/octet-stream)
 */
public function attach($file, $mime = 'application/octet-stream') {
	if (!$this->_mailer->AddAttachment($file, basename($file), 'base64', $mime)) {
		throw new Exception('failed to add attachment '.$file);
	}
}


/**
 * Add Custom Header (key:value).
 *
 * @param string $key 
 * @param string $value 
 */
public function setHeader($key, $value) {
  $this->_mailer->AddCustomHeader($key.':'.$value);
}


/**
 * Send mail via SMTP. Convert string to [ 'host' => parameter ]. SMTP Parameter are:
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
 *
 * @param array|string smtp (convert string to [ 'host' => smtp ])
 */
public function useSMTP($smtp) {
	require_once(dirname(__DIR__).'/other/PHPMailer/SMTP.php');

	if (is_string($smtp) && !empty($smtp)) {
		$smtp = [ 'host' => $smtp ];
	}

  if (count($smtp) == 0 || empty($smtp['host'])) {
    return;
  }

	$this->_mailer->isSMTP();
  $this->_mailer->Host = $smtp['host'];

  if (isset($smtp['hostname'])) {
    $this->_mailer->Hostname = $smtp['hostname'];
  }

	if (isset($smtp['secure']) && in_array($smtp['secure'], [ '', 'ssl', 'tls' ])) {
		$this->_mailer->SMTPSecure = $smtp['secure'];
	}

	if (isset($smtp['auto_tls'])) {
		$this->_mailer->SMTPAutoTLS = $smtp['auto_tls'];
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
 *
 * @throws
 * @param string $email (From Property)
 * @param string $name (FromName Property default = '')
 * @param string $sender (Sender email / Return-Path - default = '' = From) 
 */
public function setFrom($email, $name = '', $sender = '') {
  list ($email, $name) = $this->_email_name($email, $name);

  if (!empty(self::$always_from)) {
		self::isValidEmail($always_from, true);
    $email = self::$always_from;
  }

	$this->_mailer->setFrom($email, $name, empty($sender));

	if (!empty($sender)) {
		self::isValidEmail($sender, true);
		$this->_mailer->Sender = $sender;
	}
}


/**
 * Set Recipient Adress (required). Use Mailer::$always_to = xxx for global 
 * recipient redirection.
 * 
 * @param string 
 * @param string 
 */
public function setTo($email, $name = '') {

  if (!empty(self::$always_to)) {
		self::isValidEmail($always_to, true);
    $email = self::$always_to;
  }

  $this->_add_address('to', $email, $name);
}


/**
 * Set Cc Adress. If called multiple times address list will be used.
 * 
 * @param string $email
 * @param string $name
 * 
 */
public function setCc($email, $name = '') {

	if (!empty(self::$always_to)) {
		return;
	}

	$this->_add_address('cc', $email, $name);
}


/**
 * Set Bcc Adress. If called multiple times address list will be used.
 * 
 * @param string $email
 * @param string $name
 * 
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
 * 
 * @param string $email
 * @param string $name 
 */
public function setReplyTo($email, $name = '') {
	$this->_add_address('ReplyTo', $email, $name);
}


/**
 * Set Subject.
 * 
 * @param string $txt 
 */
public function setSubject($txt) {
	$this->_mailer->Subject = $txt;
}


/**
 * Set Text Body if $txt is not empty.
 *
 * @param string $txt  
 */
public function setTxtBody($txt) {
  if (!empty($txt)) {
    $this->_mailer->IsHTML(false);
    $this->_mailer->Body = $txt;
    $this->_conf['body_type'] = 'text';
  }
}


/**
 * Check email and name. Split "Username" <user@example.com> into email and name.
 *
 * @throws 
 * @param string $email
 * @param string $name
 * @return array (email, name)
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
 * Add address 
 * 
 * @param string $typ to|cc|bcc|ReplyTo
 * @param string $email
 * @param string $name
 */
private function _add_address($typ, $email, $name) {

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
		throw new Exception("Empty email", "name=[$name] typ=[$typ]");
  }

	foreach ($email_name_list as $ex => $nx) {
		list ($email, $name) = $this->_email_name($ex, $nx);

		$lc_email = mb_strtolower($email);

		if (isset($this->typ_email[$typ]) && in_array($lc_email, $this->typ_email[$typ])) {
			// don't add same email to same type twice
			continue;
		}

		if ($typ != 'ReplyTo') {
			if (in_array($lc_email, $this->typ_email['recipient'])) {
				// don't add same email twice
				continue;
			}
			else {
				array_push($this->typ_email['recipient'], $lc_email);
			}
		}

		if ($typ == 'to') {
			$ok = $this->_mailer->AddAddress($email, $name);
		}
		else if ($typ == 'cc') {
			$ok = $this->_mailer->AddCC($email, $name);
		}
		else if ($typ == 'bcc') {
			$ok = $this->_mailer->AddBCC($email, $name);
		}
		else if ($typ == 'ReplyTo') {
			$ok = $this->_mailer->AddReplyTo($email, $name);
		}
		else {
			throw new Exception("invalid typ [$typ]");
		}

		if (!$ok) {
			throw new Exception("invalid email [$email]", "typ=[$typ] name=[$name]");
		}

		if (!isset($this->typ_email[$typ])) {
			$this->typ_email[$typ] = [];
		}

		array_push($this->typ_email[$typ], $lc_email);
	}
}


/*

//Set the subject line
$mail->Subject = 'PHPMailer mail() test';

//Read an HTML message body from an external file, convert referenced images to embedded,
//convert HTML into a basic plain-text alternative body
$mail->msgHTML(file_get_contents('contents.html'), dirname(__FILE__));

//Replace the plain text body with one created manually
$mail->AltBody = 'This is a plain-text message body';

*/

}
