<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';


/**
 * IMAP/POP3 wrapper.
 * 
 * Install php-imap if necessary (e.g. apt-get install php7-imap)
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class IMAP {

/** @var object $_con */
protected $con = null;

/** @var map $conf */
protected $conf = [];

/** @var int $inbox_count */
protected $inbox_count = null;

/** @var bool $expunge */
protected $expunge = false;

/** @var int $id */
protected $id = null;

/** @var int $is_uid (0 = default, FT_UID otherwise) */
private $id_is_uid = 0;


/**
 * Open IMAP/POP3 connection.
 */
public function __construct(array $conf = []) {

	$this->reset();

	if (count($conf) > 0) {
		$this->open($conf);
	}
}


/**
 * Close connection and reset to default connection parameter.
 */
public function reset() : void {

	if (!is_null($this->con)) {
		$this->close();
	}

	$this->con = null;
	$this->inbox_count = null;
	$this->expunge = false;
	$this->id = null;
	$this->id_is_uid = 0;
	$this->conf = [ 'connect' => '', 'server' => '', 'port' => '143', 'mode' => '', 'ssl' => false, 'login' => '', 
		'password' => '', 'dir' => 'INBOX', 'extra' => '' ];
}


/**
 * Set connection parameter. Parameter List:
 * 
 * - server: required
 * - port: required (imap = 143, pop3 = 110, default = 143)
 * - mode: imap|pop3|nntp (default = '')
 * - ssl: true|false (default = false)
 * - extra: e.g. ssl certificate (default = '')
 * - login:
 * - password:
 * - dir: (default = INBOX)
 *
 * Use special parameter "connect" instead of server+port+mode+ssl+dir.
 * Connect examples: 
 *
 * {localhost:143}INBOX - IMAP server auf Port 143 des lokalen Rechners
 * {localhost:110/pop3}INBOX - POP3 server auf Port 110 des lokalen Rechners
 * {localhost:993/imap/ssl}INBOX
 * {localhost:995/pop3/ssl/novalidate-cert} - Servern mit selbstsignierten Zertifikaten  
 * {localhost:119/nntp}comp.test - use empty login + pass
 * 
 * @see http://de.php.net/manual/de/function.imap-open.php
 */
public function set(string $key, string $value) : void {

	if (!isset($this->conf[$key])) {
		throw new Exception('invalid parameter '.$key);
	}

	if (in_array($key, [ 'server', 'port' ]) && empty($value)) {
		throw new Exception('empty value', "key=$key value=$value");
	}

	if ($key == 'port' && intval($value) < 1) {
		throw new Exception('invalid port', 'port='.$value);
	}

	$this->conf[$key] = $value;
}


/**
 * Return configuration value.
 */
public function get(string $key) : string {

	if (!isset($this->conf[$key])) {
		throw new Exception('no such configuration parameter '.$key);
	}

	return $this->conf[$key];
}


/**
 * Open connection.
 */
public function open(array $conf = []) : void {

	foreach ($conf as $key => $value) {
		$this->set($key, $value);
	}

	if (empty($this->conf['connect'])) {

		if (empty($this->conf['server'])) {
			throw new Exception('server is not set');
		}

		if (empty($this->conf['port'])) {
			throw new Exception('port is not set');
		}

		$this->conf['connect'] = '{'.$this->conf['server'].':'.$this->conf['port'];

		if (!empty($this->conf['mode'])) {
			$this->conf['connect'] .= '/'.$this->conf['mode'];
		}

		if (!empty($this->conf['ssl'])) {
			$this->conf['connect'] .= '/ssl';
		}

		if (!empty($this->conf['extra'])) {
			$this->conf['connect'] .= '/'.$this->conf['extra'];
		}

		$this->conf['connect'] .= '}';

		if (!empty($this->conf['dir'])) {
			$this->conf['connect'] .= $this->conf['dir'];
		}
	}

	$this->con = imap_open($this->conf['connect'], $this->conf['login'], $this->conf['password']);

	if (!is_resource($this->con)) {
		throw new Exception("Could not connect", 'connect='.$this->conf['connect'].' error='.imap_last_error());
	}

	if (substr($this->conf['connect'], -6) == '}INBOX') {
		$this->inbox_count = imap_num_msg($this->con);
	}
}


/**
 * Return message number.
 */
public function count() : int {
	$this->_check_con();
  return $this->inbox_count;
}


/**
 * Return connection resource. For use with e.g. imap_xxx() functions.
 *
 * @return resource
 */
public function getConnection() {
	$this->_check_con(false);
	return $this->con;
}


/**
 * Check if message exists. Return true|false if $abort == false.
 */
public function hasMessage(int $num, bool $abort = false) : bool {
	$this->_check_con();

	if ($num < 1 || $num > $this->inbox_count) {
		if ($abort) {
			throw new Exception('invalid message number', $num.' not in 1..'.$this->inbox_count);
		}

		return false;
  }

	return true;
}

 
/**
 * Delete message.
 */
public function deleteMsg(int $num) : void {
	$this->hasMessage($num, true);
   
	if (!imap_delete($this->con, $num)) {
		throw new Exception('could not delete message', $num);
	}

  $this->expunge = true;
}


/**
 * Select message by number. Necessary for getXXX() operations.
 */
public function selectMsg(int $num) : void {
	$this->hasMessage($num, true);
  $this->id = $num;
  $this->id_as_uid = 0;
}


/**
 * Set message id. Necessary for getXXX() operations.
 */
public function setUid(string $uid) : void {
	$this->_check_con();
  $this->id_is_uid = FT_UID;
  $this->id = $uid;
}


/**
 * Return header hash. Return keys are: 
 *  date, subject, message_id, to, from, size, uid,
 *  udate, references, in_reply_to, msgno, recent, flagged, answered, deleted, seen, draft
 *
 * @see php imap_fetch_overview()
 */
public function getHeader() : array {
  $this->_check_id();
  $h = imap_fetch_overview($this->con, $this->id, $this->id_id_uid);
	return $this->_convert_overview($h[0]);
}


/**
 * Convert overview object to map. Add first part of message_id as mid (= unique identifier).
 */
private function _convert_overview(object $o) : array {
  $res = [];
  $res['subject'] = $o->subject;
  $res['from'] = $o->from;
  $res['to'] = $o->to;
  $res['date'] = $o->date;
  $res['udate'] = property_exists($o, 'udate') ? $o->udate : strtotime($o->date);
  $res['message_id'] = $o->message_id;
  $res['references'] = property_exists($o, 'references') ? $o->references : '';
  $res['in_reply_to'] = property_exists($o, 'in_reply_to') ? $o->in_reply_to : '';
  $res['size'] = $o->size;
  $res['uid'] = $o->uid;
  $res['msgno'] = $o->msgno;
  $res['recent'] = $o->recent;
  $res['flagged'] = $o->flagged;
  $res['answered'] = $o->answered;
  $res['deleted'] = $o->deleted;
  $res['seen'] = $o->seen;
  $res['draft'] = $o->draft;

  $tmp = explode('@', $res['message_id']);
  if (substr($tmp[0], 0, 1) == '<') {
    $tmp[0] = substr($tmp[0], 1);
  }
  $res['mid'] = $tmp[0];

  return $res;
}


/**
 * Return raw mail header.
 */
public function getRawHeaders() : string {
	$this->_check_id();
  return imap_fetchheader($this->con, $this->id, $this->id_is_uid);
}


/**
 * Return current mailbox.
 */
public function getMailbox() : object {
	$this->_check_con();
	return imap_check($this->con);
}


/**
 * Return table with mailbox overview.
 * @see getHeader() for table rows
 */
public function getListing(bool $show_all = true) : array {
	$this->_check_con();
  $res = array();

  if ($show_all) {
    $mbox = $this->getMailbox();
    $overview = imap_fetch_overview($this->con, '1:'.$mbox->Nmsgs, 0);
  }
  else {
		$this->_check_id();
    $overview = imap_fetch_overview($this->con, $this->id, $this->id_as_uid);
  }

  foreach ($overview as $o) {
    array_push($res, $this->_convert_overview($o));
  }

  return $res;
}


/**
 * Return mail structure.
 */
public function getStructure() : object {
	$this->_check_id();
  return imap_fetchstructure($this->con, $this->id, $this->id_as_uid);
}


/**
 * Return headers. Parameter are: date, subject, message_id, to, from.
 * 
 * @param string $head
 * @return hash
 */
public function parseHeaders(string $head) : array {
	$h = imap_rfc822_parse_headers($head);
	$p = array();
	
	$p['date'] = $h->date;
	$p['subject'] = $h->subject;
	$p['message_id'] = $h->message_id;
	$p['to'] = $h->toaddress;
	// $h->reply_toaddress, $h->senderaddress
	$p['from'] = $h->fromaddress;
	
	return $p;
}


/**
 * Return attachments. If suffix is set return only matching attachments.
 * @param string $suffix
 * @return table  
 */
public function getAttachments(string $suffix = '') : array {
	$this->_check_id();
	$res = array();

  $s = $this->getStructure();

  if (!isset($s->parts) || count($s->parts) < 1) {
		return $res;      	
  }
  
	for ($i = 0; $i < count($s->parts); $i++) {
    $att = array('is_attachment' => false, 'filename' => '', 'name' => '', 'attachment' => '');

    if ($s->parts[$i]->ifdparameters) {
			foreach ($s->parts[$i]->dparameters as $obj) {
				if (strtolower($obj->attribute) == 'filename') {
					$att['is_attachment'] = true;
					$att['filename'] = $obj->value;
 				}
			}    	
		}
		
		if ($s->parts[$i]->ifparameters) {
			foreach ($s->parts[$i]->parameters as $obj) {
				if (strtolower($obj->attribute) == 'name') {
					$att['is_attachment'] = true;
					$att['name'] = $obj->value;
				}
			}
		}

		if ($att['is_attachment'] && $suffix) {
			$suffix = strtolower($suffix);
			$sl = -1 * strlen($suffix);
			$att['is_attachment'] = false;
			
			if (substr(strtolower($att['filename']), $sl) == $suffix || substr(strtolower($att['name']), $sl) == $suffix) {
				$att['is_attachment'] = true;	
			}
		}
				
		if ($att['is_attachment']) {
			$att['attachment'] = $this->_decode(imap_fetchbody($this->con, $this->id, $i+1, $this->id_as_uid), $s->parts[$i]->encoding);
			array_push($res, $att);
		}		
	} 

	return $res;
}


/**
 * Return Message as (head, body) hash. Use getAttachments() for attachment retrieval.
 */
public function getMsg() : array {
  $this->_check_id();
  $res = array();
  
  $res['head'] = imap_fetchbody($this->con, $this->id, 0, $this->id_as_uid);
  $res['body'] = imap_fetchbody($this->con, $this->id, 1, $this->id_as_uid);

  $s = $this->getStructure();
  $parts = isset($s->parts) ? $s->parts : array();
  $fpos = 2;

	// Attachment detection is only usable for special cases ...
  for ($i = 1; $i < count($parts); $i++) {
    $message["pid"][$i] = ($i);
    $part = $parts[$i];

		if (!property_exists($part, 'disposition')) {
			continue;
		}

    if ($part->disposition == "ATTACHMENT") {
      $attachment = imap_fetchbody($this->con, $this->id, $fpos, $this->id_as_uid);

      $key = isset($part->description) ? $fpos.'_'.$part->description : $fpos;   
      $res[$key] = $this->_decode($attachment, $part->type);
      $fpos++;
    }
    else {
			throw new Exception('could not parse message part', "({$this->id}) ignored part $i / $fpos = [{$part->disposition}]");
    }
  }

  return $res;
}


/**
 * Save all messages in directory. Return number of saved messages. 
 */
public function saveAll(string $dir) : int {
	$this->_check_con();

	$mbox = $this->getMailbox();
	$overview = imap_fetch_overview($this->con, '1:'.$mbox->Nmsgs, 0);
	$ov_num = count($overview);

	$curr_id = $this->id;
	$curr_id_as_uid = $this->id_as_uid;
	$this->id_as_uid = 0;

	for ($i = 0; $i < $ov_num; $i++) {
		$h = $this->_convert_overview($overview[$i]);
		$save_as = $dir.'/mail_'.date('YmdHis', $h['udate']).'_'.$h['mid'].'.ser';
		$this->id = $h['msgno'];

		if (!File::exists($save_as)) {
			$h['raw_headers'] = imap_fetchheader($this->con, $this->id, FT_PREFETCHTEXT);
			$h['body'] = imap_body($this->con, $this->id);
			$h['attachments'] = $this->getAttachments();
			File::serialize($save_as, $h);
		}
	}

	$this->id = $curr_id;
	$this->id_as_uid = $curr_id_as_uid;

	return $ov_num;
}


/**
 * Save serialized message map in directory. Parameter are getHeader() parameter plus raw_headers, body and
 * attachments.
 */
public function save(string $dir, int $msg_num) : void {
	require_once __DIR__.'/File.class.php';

	$this->selectMsg($msg_num);
	$h = $this->getHeader();

	$save_as = $dir.'/mail_'.date('YmdHis', $h['udate']).'_'.$h['mid'].'.ser';

	if (File::exists($save_as)) {
		return;
	}

	$h['raw_headers'] = imap_fetchheader($this->con, $this->id, $this->id_as_uid | FT_PREFETCHTEXT);
	$h['body'] = imap_body($this->con, $this->id, $this->id_as_uid);
	$h['attachments'] = $this->getAttachments();

	File::serialize($save_as, $h);
}


/**
 * Close imap connection.
 */
public function close() : void {
	$this->_check_con(false);

	if ($this->_expunge) {
		imap_expunge($this->con);  
	}

  imap_close($this->con);
}


/**
 * Decode text.
 * 
 * @param string $txt
 * @param int $coding
 * @return string
 */
private function _decode(string $txt, int $coding) : string {
	
  switch ($coding) {
    case 0:
      $txt = imap_8bit($txt); break;
    case 1:
      $txt = imap_8bit($txt); break;
    case 2:
      $txt = imap_binary($txt); break;
    case 3:
      $txt = imap_base64($txt); break; 
    case 4:
      $txt = imap_qprint($txt); break;
    case 5: 
      $txt = imap_base64($txt); break; 
  }

  return $txt;
}


/**
 * Check if connection exists. Throw exception if abort is true.
 */
private function _check_con(bool $is_inbox = true, bool $abort = true) : bool {
	$error_msg = '';

	if (is_null($this->con) || !is_resource($this->con)) {
		$error_msg = 'no connection';
	}

	if (!$error_msg && $is_inbox && is_null($this->inbox_count)) {
		$error_msg = 'no inbox connection';
	}

	if ($error && $abort) {
		throw new Exception($error_msg);
	}

	return empty($error_msg);
}


/**
 * Throw exception if this.id is null.
 */
private function _check_id() : void {
	if (is_null($this->id)) {
    throw new Exception('call selectMsg() or setUid() first');
  }	
}


}
