<?php

namespace rkphplib;

if (!defined('PATH_PHPMAILER')) {
	define('PATH_PHPMAILER', dirname(__DIR__).'/other/PHPMailer/');
}

// @define SETTINGS_NO_SMTP 0 (force local mailer if 1)
defined('SETTINGS_NO_SMTP') || define('SETTINGS_NO_SMTP', 0);

require_once __DIR__.'/Dir.class.php';
require_once __DIR__.'/lib/resolvPath.php';

require_once PATH_PHPMAILER.'Exception.php';
require_once PATH_PHPMAILER.'PHPMailer.php';
require_once PATH_PHPMAILER.'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

use function rkphplib\lib\resolvPath;


/**
 * Mailer.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Mailer {

// @var string $always_to force always same recpient
public static $always_to = '';

// @var string $always_from force always same sender 
public static $always_from = '';

// @var array|string $smtp smtp-host (string) or smtp-configuration (array) 
public static $smtp = null;

// @var PHPMailer $_mailer 
private $_mailer = null;

// @var array $type_email count doubles. Keys: to|cc|bcc|ReplyTo|from|recipient 
private $type_email = [ 'recipient' => [] ];

// @var string $last_msg_id
private $last_msg_id = null;



/**
 * Use PHPMailer with exceptions in utf-8 mode.
 */
public function __construct() {
  $this->_mailer = new PHPMailer(true);
	$this->_mailer->CharSet = 'utf-8';
	$this->_mailer->Timeout = 10;
	$this->setDefault();
}


/**
 * Use global SETTINGS_* constants:
 * SETTINGS_SMTP_[HOST|POST|USER|PASS] (if user+pass always use tls)
 * SETTINGS_MAIL_[FROM|REPLY_TO]
 */
private function setDefault() {
	if (defined('SETTINGS_SMTP_HOST')) {
		$smtp = SETTINGS_SMTP_HOST;
		if (defined('SETTINGS_SMTP_USER') && defined('SETTINGS_SMTP_PASS')) {
			$smtp = [
				'host' => SETTINGS_SMTP_HOST,
				'user' => SETTINGS_SMTP_USER,
				'pass' => SETTINGS_SMTP_PASS,
				'auto_tls' => true
			];

			if (defined('SETTINGS_SMTP_PORT')) {
				$smtp['port'] = SETTINGS_SMTP_PORT;
			}
		}

		$this->useSMTP($smtp);
	}

	if (defined('SETTINGS_MAIL_FROM')) {
		$this->setFrom(SETTINGS_MAIL_FROM);
	}

	if (defined('SETTINGS_MAIL_REPLY_TO')) {
		$this->setReplyTo(SETTINGS_MAIL_REPLY_TO);
	}
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
public function setMailer(string $key, string $value) : void {
	$allow = [ 'CharSet', 'Priority', 'Encoding', 'Hostname' ];

	if (!in_array($key, $allow)) {
		throw new Exception("Invalid Mailer parameter [$key]");
	}

	$this->_mailer->$key = $value;
}


/**
 * Return PHPMailer parameter. 
 */
public function getMailer(string $key) : string {
	$allow = [ 'CharSet', 'Priority', 'Encoding', 'Hostname' ];
	if (!in_array($key, $allow)) {
		throw new Exception("Invalid Mailer parameter [$key]");
	}

	return $this->_mailer->$key;
}


/**
 * Return array of attachments.
 */
public function getAttachments() : array {
	return $this->_mailer->getAttachments();
}


/**
 * Return last message id.
 */
public function getLastMessageID() : ?string {
	return $this->last_msg_id;
}


/**
 * Return encoded string. Encoding is base64, 7bit, 8bit, binary or 'quoted-printable.
 */
public function encodeString(string $str, string $encoding = 'base64') : string {
	return $this->_mailer->encodeString($str, $encoding);
}


/**
 * Return true if email is valid (or throw error).
 */
public static function isValidEmail(string $email, bool $throw_error = false) : bool {
	if (preg_match('/^[a-z0-9_\.\-]+@([a-z0-9\-]+\.)+[a-z]{2,}$/i', $email)) {
		return true;
	}

	$ip4 = '\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\]';
	$rx = '/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@(('.$ip4.
		')|(([A-Z\-0-9]+\.)+[A-Z]{2,}))$/i';

	if (preg_match($rx, $email)) {
		return true;
	}

	if (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
		return false;
	}

	$tmp = explode('@', $email);
	$ascii_email = idn_to_ascii($tmp[0]).'@'.idn_to_ascii($tmp[1]);
	$res = preg_match($rx, $ascii_email);

	if (!$res && $throw_error) {
		throw new Exception("Invalid email", $email);
	}

	return $res;
}


/**
 * Embed Image.
 */
public function embedImage(string $file, string $mime = 'application/octet-stream', string $encoding = 'base64') : void {
	if (!$this->_mailer->addEmbeddedImage($file, md5($file), basename($file), $encoding, $mime, 'inline')) {
		throw new Exception('failed to add embedded image '.$file);
	}
}


/**
 * Add attachment to mail (base64 encoding). Default mime type is application/octet-stream, empty means auto_detect.
 */
public function attach(string $file, string $mime = 'application/octet-stream') : void {
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
public function setHeader(string $key, string $value) : void {
  $this->_mailer->AddCustomHeader($key.':'.$value);
}


/**
 * Send Test mail
 */
public function testMail(string $to, bool $verbose = false) : void {
	print "Send Testmail\n";

	if ($verbose) {
		$this->_mailer->SMTPDebug = SMTP::DEBUG_SERVER;
	}

	$now = date('d.m.Y H:i:s', time());
	$this->setTo($to);
	$this->setSubject('Mailer Test '.$now);
	$this->setHtmlBody('<h3>Mailer Test</h3><p>'.$now.'</p>');
	$this->setTxtBody('Mailer Test '.$now);

	if (defined('SETTINGS_SMTP_USER') && defined('SETTINGS_SMTP_PASS') &&
			!empty(SETTINGS_SMTP_USER) && !empty(SETTINGS_SMTP_PASS)) {
		$this->send();
		print "Message has been send\n";
	}
	else {
		print "Skip send message - define SETTINGS_SMTP_USER|PASS\n";
	}
}


/**
 * Test SMTP Server defined via SETTINGS_SMTP_*.
 */
public static function testSMTP(bool $verbose = false) : void {
	$smtp = new \PHPMailer\PHPMailer\SMTP();
	$smtp->Timeout = 5;

	if ($verbose) {
		$smtp->do_debug = \PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION;
	}

	print "Connect\n";
	if (!$smtp->connect(SETTINGS_SMTP_HOST, SETTINGS_SMTP_PORT)) {
		throw new Exception('Connect failed');
	}

	print "hello\n";
	if (!$smtp->hello(gethostname())) {
		throw new Exception('EHLO failed: '.$smtp->getError()['error']);
	}

	$e = $smtp->getServerExtList();
	if (is_array($e) && array_key_exists('STARTTLS', $e)) {
		print "TLS Login\n";
		$tlsok = $smtp->startTLS();
		if (!$tlsok) {
			throw new Exception('Failed to start encryption: ' . $smtp->getError()['error']);
		}

		print "hello\n";
		if (!$smtp->hello(gethostname())) {
			throw new Exception('EHLO (2) failed: ' . $smtp->getError()['error']);
		}

		$e = $smtp->getServerExtList();
	}

	if (defined('SETTINGS_SMTP_USER') && defined('SETTINGS_SMTP_PASS') &&
			!empty(SETTINGS_SMTP_USER) && !empty(SETTINGS_SMTP_PASS)) {
		print "Authenticate\n";
		if ($smtp->authenticate(SETTINGS_SMTP_USER, SETTINGS_SMTP_PASS)) {
			print "Connected ok!\n";
		}
		else {
			throw new Exception('Authentication failed: ' . $smtp->getError()['error']);
		}
	}
	else {
		print "Skip Authentication - define SETTINGS_SMTP_USER|PASS\n";
	}

	print "quit\n";
	$smtp->quit();
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
public function useSMTP($smtp) : void {
	if (is_string($smtp) && !empty($smtp)) {
		$smtp = [ 'host' => $smtp ];
	}

  if (count($smtp) == 0 || empty($smtp['host'])) {
    return;
  }

	if (SETTINGS_NO_SMTP) {
		if (defined('SETTINGS_SMTP_USER') && SETTINGS_SMTP_USER) {
			\rkphplib\lib\warn('SETTINGS_NO_SMTP and SETTINGS_SMTP_USER are both set');
		}

		return;
	}

	if (getHostByName(getHostName())== getHostByName($smtp['host'])) {
		// smtp.host = localhost: use mail method
		$this->_mailer->IsMail();
		return;
	}

	$this->_mailer->isSMTP();
  $this->_mailer->Host = $smtp['host'];

  if (isset($smtp['hostname'])) {
    $this->_mailer->Hostname = $smtp['hostname'];
  }
	else if (!empty($this->type_email['from']) && empty($this->_mailer->Hostname) && empty($_SERVER['SERVER_NAME'])) {
		// don't use localhost.localdomain
		list ($email_from_name, $email_from_host) = explode('@', $this->type_email['from'], 2);
		$this->_mailer->Hostname = mb_strtolower($email_from_host);
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

	if (!empty($smtp['port'])) {
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
public function setFrom(string $email, string $name = '', string $sender = '') : void {
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
public function setTo(string $email, string $name = '') : void {

  if (!empty(self::$always_to)) {
		self::isValidEmail(self::$always_to, true);
    $email = self::$always_to;
  }

  $this->_add_address('to', $email, $name);
}


/**
 * Set Cc Adress. If called multiple times address list will be used.
 */
public function setCc(string $email, string $name = '') : void {

	if (!empty(self::$always_to)) {
		return;
	}

	$this->_add_address('cc', $email, $name);
}


/**
 * Set Bcc Adress. If called multiple times address list will be used.
 */
public function setBcc(string $email, string $name = '') : void {

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
public function setReplyTo(string $email, string $name = '') : void {
	$this->_add_address('ReplyTo', $email, $name);
}


/**
 * Set Subject. UTF-8 characters are allowed.
 */
public function setSubject(string $txt) : void {
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
public function setTxtBody(string $txt) : void {
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
public function setHtmlBody(string $html, string $basedir = '') : void {
  if (!empty($html)) {
    $this->_mailer->MsgHTML($html, $basedir);
  }
}


/**
 * Check email and name. Split "Username" <user@example.com> into email and name. Return (email, name).
 */
private function _email_name(string $email, string $name) : array {

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
private function _add_address(string $type, string $email, string $name) : void {
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
 * Save mail in directory $save_in.
 */
private function saveMail(string $save_in) : void {
	if (!is_null($this->last_msg_id)) {
		$save_in .= '/'.substr($this->last_msg_id, 1, -1);
	}

	Dir::create($save_in, 0, true);
	throw new Exception('ToDo: save mail in '.$save_in);
}


/**
 * Send mail. Force global overwrite with SETTINGS_MAIL_[SEND|SAVE].
 *
 * @hash $opt
 * send: 1
 * save: 0
 * save_dir: data/.log/mail/$date(Ym)/$date(dH)/$map(id)
 * @eol
 */
public function send(array $options = []) : bool {

	if (!isset($this->type_email['to']) || count($this->type_email['to']) == 0) {
		throw new Exception('call setTo() first');
	}

	if (!is_null(self::$smtp)) {
		$this->useSMTP(self::$smtp);
	}

	if (!isset($options['send']) && defined('SETTINGS_MAIL_SEND')) {
		$options['send'] = SETTINGS_MAIL_SEND;
	}

	if (!isset($options['save']) && defined('SETTINGS_MAIL_SAVE')) {
		$options['save'] = SETTINGS_MAIL_SAVE; 
	}

	$default = [ 'send' => 1, 'save' => 0, 'save_dir' => 'data/.log/mail/$date(Ym)/$date(dH)' ];
	$options = array_merge($default, $options);

	// \rkphplib\lib\log_debug([ "Mailer.send:624> <1>", $options ]);
	if (!$this->_mailer->preSend()) {
		throw new Exception('Mailer preSend failed');
	}

	$this->last_msg_id = null;

	if (!empty($options['send'])) {
		// \rkphplib\lib\log_debug([ "Mailer.send:632> <1>", $this->_mailer ]);
		if (!$this->_mailer->postSend()) {
			throw new Exception('Mailer postSend failed');
		}

		$this->last_msg_id = $this->_mailer->getLastMessageID();
		// \rkphplib\lib\log_debug("Mailer.send:638> ".$this->last_msg_id);
	}

	if (!empty($options['save'])) {
		$this->saveMail(resolvPath($options['save_dir']));
	}

	return !is_null($this->last_msg_id);
}


}
