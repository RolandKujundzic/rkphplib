<?php

namespace rkphplib;

require_once __DIR__.'/Database.class.php';

use rkphplib\ADatabase;
use rkphplib\Database;
use rkphplib\Exception;


/**
 * Database session handler.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @phpVersionLt 7.0 class DatabaseSession implements SessionHandlerInterface {
 */
class DatabaseSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface {

// @param ADatabase $db = null
private $db = null;

// @param int $ttl = 3600 (1h valid)
private $ttl = 3600;


/**
 * Initialize database and call session_set_save_handler($this, true) and session_start().
 * Default $dsn is SETTINGS_DSN.
 */
public function __construct(string $dsn = '') {
	// \rkphplib\lib\log_debug('DatabaseSessionHandler.__construct:31> create database');
	$query_map = [
		'select' => "SELECT data FROM cms_session WHERE id={:=id} AND until > NOW()",
		'insert' => "INSERT INTO cms_session (id, until, data) VALUES ({:=id}, {:=until}, {:=data})",
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

	// \rkphplib\lib\log_debug('DatabaseSessionHandler.__construct:52> start session');
	session_set_save_handler($this, true);
	if (session_start()) {
		throw new Exception('session_start() failed');
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
 * Session callback.
 */
public function close() {
	// \rkphplib\lib\log_debug('DatabaseSessionHandler.close:90> close()');	
	return $this->db->close();
}


/**
 * Session callback.
 */
public function destroy($sessionId) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.destroy:99> destroy($sessionId)");	
	return $this->db->execute($this->db->getQuery('delete', [ 'id' => $sessionId ]));
}


/**
 * Session callback.
 */
public function gc($maxLifetime) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.gc:108> gc($maxLifetime)");	
	return $this->db->execute($this->db->getQuery('garbage_collect', [ 'until' => date('Y-m-d H:i:s', time() - $maxLifetime) ]));
}


/**
 * Session callback.
 */
public function open($sessionSavePath, $sessionName) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.open:117> open($sessionSavePath, $sessionName)");	
	return $this->db->connect();
}


/**
 * Session callback.
 */
public function read($sessionId) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.read:126> read($sessionId)");	
	$dbres = $this->db->selectOne($this->db->getQuery('select', [ 'id' => $sessionId ]));
	return is_null($dbres) ? '' : $dbres[0]['data'];
}


/**
 * Session callback.
 */
public function write($sessionId, $sessionData) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.write:136> write($sessionId, ".substr($sessionData, 0, 40)."…)");
	$until = date('Y-m-d H:i:s', time() + $this->ttl);
	return $this->db->execute($this->db->getQuery('update', [ 'id' => $sessionId, 'data' => $sessionData, 'until' => $until ]));
}


/**
 * Session callback.
 */
public function create_sid() {
  $id = '';

  for ($i = 0; $i < 4; $i++) {
    $n = mt_rand(4096, 65535);
    $id .= \rkphplib\lib\dec2n(mt_rand(4096, 65535), 16);
  }

	// \rkphplib\lib\log_debug("DatabaseSessionHandler.create_sid:153> create_sid() = $id");	
  return $id;
}


/**
 * Session callback.
 * @phpVersionLt 7.0 skip
 */
public function validateId($sessionId) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.validateId:163> validateId($sessionId)");	
	$dbres = $this->db->selectOne($this->db->getQuery('select', [ 'id' => $sessionId ]));
	return !is_null($dbres);
}


/**
 * Session callback.
 * @phpVersionLt 7.0 skip 
 */
public function updateTimestamp($sessionId, $sessionData) {
	// \rkphplib\lib\log_debug("DatabaseSessionHandler.updateTimestamp:174> updateTimestamp($sessionId, ".substr($sessionData, 0, 40)."…)");	
	$until = date('Y-m-d H:i:s', time() + $this->ttl);
	return $this->db->execute($this->db->getQuery('update', [ 'id' => $sessionId, 'data' => $sessionData, 'until' => $until ]));
}


}

