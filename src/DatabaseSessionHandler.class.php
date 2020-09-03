<?php

namespace rkphplib;

require_once __DIR__.'/Database.class.php';
require_once __DIR__.'/lib/dec2n.php';

use rkphplib\ADatabase;
use rkphplib\Database;
use rkphplib\Exception;


/**
 * Database session handler.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @phpVersionLt 7.0 class DatabaseSession implements SessionHandlerInterface {
 */
class DatabaseSessionHandler implements \SessionHandlerInterface, \SessionIdInterface, \SessionUpdateTimestampHandlerInterface {

// @param ADatabase $db = null
private $db = null;

// @param int $ttl = 3600 (1h valid)
private $ttl = 3600;

//
private $missing = '';


/**
 * Initialize database and call session_set_save_handler($this, true) and session_start().
 * Default $dsn is SETTINGS_DSN.
 */
public function __construct(string $dsn = '') {
	// \rkphplib\lib\log_debug('DatabaseSessionHandler.__construct:36> create database');
	$query_map = [
		'select' => "SELECT data FROM cms_session WHERE id={:=id} AND until > NOW()",
		'insert' => "REPLACE INTO cms_session (id, until, data) VALUES ({:=id}, {:=until}, {:=data})",
		'update' => "UPDATE cms_session SET until={:=until}, data={:=data} WHERE id={:=id} AND until > NOW()",
		'delete' => "DELETE FROM cms_session WHERE id={:=id}",
		'garbage_collect' => "DELETE FROM cms_session WHERE until < '{:=until}'"
		];

	$this->db = Database::getInstance($dsn, $query_map); 

	$tconf = [];
	$tconf['@table'] = 'cms_session';
	$tconf['@timestamp'] = 2;
	$tconf['id'] = 'binary:16::3';
	$tconf['until'] = 'datetime:::9';
	$tconf['data'] = 'blob:::1';
	$this->db->createTable($tconf);

	$this->db->abort = false;

	// \rkphplib\lib\log_debug('DatabaseSessionHandler.__construct:57> start session');
	if (!isset($_SESSION)) {
		session_set_save_handler($this, true);
		if (!session_start()) {
			throw new Exception('session_start() failed', print_r($_SERVER, true));
		}
	}
}


/**
 * Set session value (_SESSION[key]=value wrapper).
 * @param any $value
 */
public function set(string $key, $value) : void {
	$_SESSION[$key] = $value;
}


/**
 * Return session value (_SESSION[key] wrapper).
 * @return any
 */
public function get(string $key) {
	return isset($_SESSION[$key]) ? $_SESSION[$key] : '';
}


/**
 * True if session key exists (_SESSION[key] wrapper).
 */
public function has(string $key) : bool {
	return isset($_SESSION[$key]) ? $_SESSION[$key] : '';
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function close() {
	// \rkphplib\lib\log_debug('DatabaseSessionHandler.close:97> close()');
	return true;
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function destroy($sid) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.destroy:106> destroy($sid)");
	return $this->db->execute($this->db->getQuery('delete', [ 'id' => $sid ]));
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function gc($lifetime) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.gc:115> gc($lifetime)");
	return $this->db->execute($this->db->getQuery('garbage_collect', [ 'until' => date('Y-m-d H:i:s', time() - $lifetime) ]));
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function open($save_path, $sname) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.open:124> open($save_path, $sname)");
	return $this->db->connect();
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function read($sid) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.read:133> read($sid)");
	$query = $this->db->getQuery('select', [ 'id' => $sid ]);
	$dbres = $this->db->selectOne($query);
	$res = is_null($dbres) ? '' : $dbres['data'];

	if ($res == '') {
		// \rkphplib\lib\log_debug("DatabaseSessionHandler.read:139> no result: $query");
		$this->missing = $sid;
	}

	return $res;
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function write($sid, $data) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.write:151> write($sid, ".substr($data, 0, 40)."…)");
	$until = date('Y-m-d H:i:s', time() + $this->ttl);

	if ($this->missing == $sid) {
		$query = $this->db->getQuery('insert', [ 'id' => $sid, 'data' => $data, 'until' => $until ]);
	}
	else {
		$query = $this->db->getQuery('update', [ 'id' => $sid, 'data' => $data, 'until' => $until ]);
	}

	// \rkphplib\lib\log_debug("DatabaseSessionHandler.write:161> $query");
	$res = $this->db->execute($query);
	if ($res === true) \rkphplib\lib\log_debug('DatabaseSessionHandler.write:163> ok');
	return $res;
}


/**
 * Session callback (SessionIdInterface)
 */
public function create_sid() {
  $id = '';

  for ($i = 0; $i < 4; $i++) {
    $n = mt_rand(4096, 65535);
    $id .= \rkphplib\lib\dec2n(mt_rand(4096, 65535), 16);
  }

	// \rkphplib\lib\log_debug("DatabaseSessionHandler.create_sid:179> create_sid() = $id");
  return $id;
}


/**
 * Session callback (SessionUpdateTimestampHandlerInterface)
 * @phpVersionLt 7.0 skip
 */
public function validateId($sid) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.validateId:189> validateId($sid)");
	$dbres = $this->db->selectOne($this->db->getQuery('select', [ 'id' => $sid ]));
	return !is_null($dbres);
}


/**
 * Session callback (SessionUpdateTimestampHandlerInterface)
 * @phpVersionLt 7.0 skip 
 */
public function updateTimestamp($sid, $data) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.updateTimestamp:200> updateTimestamp($sid, ".substr($data, 0, 40)."…)");
	$until = date('Y-m-d H:i:s', time() + $this->ttl);
	return $this->db->execute($this->db->getQuery('update', [ 'id' => $sid, 'data' => $data, 'until' => $until ]));
}


}

