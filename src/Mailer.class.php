<?php

namespace rkphplib;

require_once(dirname(__DIR__).'/other/PHPMailer/Exception.php');
require_once(dirname(__DIR__).'/other/PHPMailer/PHPMailer.php');
require_once(dirname(__DIR__).'/other/PHPMailer/SMTP.php');

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

/** @var PHPMailer $_mailer */
private $_mailer = null;

/** @var array $typ_email count doubles */
private $typ_email = [ 'recipient' => '' ];



/**
 *
 */
public function __construct() {
	// use PHPMailer with exceptions
  $this->_mailer = new PHPMailer(true);
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


//Attach an image file
$mail->addAttachment('images/phpmailer_mini.png');

*/

}
