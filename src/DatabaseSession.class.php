<?php

namespace rkphplib;

require_once __DIR__.'/Database.class.php';

use rkphplib\ADatabase;
use rkphplib\Database;


/**
 * Database session handler.
 *
 * @code 
 * 
 * $handler = new MySessionHandler();
session_set_save_handler($handler, true);
session_start();
 
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @phpVersionLt 7.0 class DatabaseSession implements SessionHandlerInterface {
 */
class DatabaseSession implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {

// @param ADatabase $db = null
private $db = null;


/**
 * Call session_set_save_handler($this, true).
 */
public function __construct() {
	$query_map = [
		'insert' => "INSERT INTO cms_session (id, until, data) VALUES ({:=id}, {:=until}, {:=data})",
		'update' => "UPDATE cms_session SET until={:=until}, data={:=data} WHERE id={:=id}",
		'update_until' => "UPDATE cms_session SET until={:=until} WHERE id={:=id}",
		'update_data' => "UPDATE cms_session SET data={:=data} WHERE id={:=id}",
		'delete' => "DELETE FROM cms_session WHERE id={:=id}"
		];

	$this->db = Database::getInstance(SETTINGS_DSN, $query_map); 

	$tconf = [];
	$tconf['@table'] = 'cms_session';
	$tconf['@timestamp'] = 2;
	$tconf['id'] = 'binary:16::3';
	$tconf['until'] = 'datetime:::9';
	$tconf['data'] = 'blob:::1';
	$this->db->createTable($tconf);

	session_set_save_handler($this, true);
}


/**
 * 
 */
public function close() : bool {
	
}


/**
 * 
 */
public function destroy(string $sessionId) : bool {
	try {
		$this->db->execute($this->db->getQuery('delete', [ 'id' => $sessionId ]));
	}
	catch (\Exception $e) {
		return false;
	}

	return true;
}


/**
 * 
 */
public function gc(int $maxLifetime) : bool {
}


/**
 * 
 */
public function open(string $sessionSavePath, string $sessionName) : bool {
}


/**
 * 
 */
public function read(string $sessionId) : string {
}


/**
 * 
 */
public function write(string $sessionId, string $sessionData) : bool {
}


/**
 * 
 */
public function create_sid() : string {
}


/**
 * @phpVersionLt 7.0 skip
 */
public function validateId(string $sessionId) : bool {
}


/**
 * @phpVersionLt 7.0 skip 
 */
public function updateTimestamp(string $sessionId, string $sessionData) : bool {
}


}

