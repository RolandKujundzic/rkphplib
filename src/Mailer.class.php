<?php

namespace rkphplib;

require_once(__DIR__.'/other/PHPMailer/PHPMailerAutoload.php');
require_once(__DIR__.'/lib.php');

use rkphplib\lib\Exception;


/**
 * Mailer - still under construction.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Mailer {

private $_mail = null;

// force always same recpient
public static $always_to = '';

// force always same sender
public static $always_from = '';



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
 * Check email and name. Split "Username" <user@example.com> into email and name.
 * 
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
 * Set who the message is to be sent from (required). Use Mailer::$always_from for global
 * from change.
 *
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

	$set_sender_address = empty($sender);

	$this->_mail->setFrom($email, $name, $set_sender_address);

	if ($set_sender_adress) {
		self::isValidEmail($sender, true);
		$this->_mail->Sender = $sender;
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


/*

//Set an alternative reply-to address
$mail->addReplyTo('replyto@example.com', 'First Last');


//Set who the message is to be sent to
$mail->addAddress('whoto@example.com', 'John Doe');


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
