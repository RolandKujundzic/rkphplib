<?php

namespace rkphplib;

require_once __DIR__.'/Database.class.php';
require_once __DIR__.'/traits/Log.php';


/**
 * Transfer data from one database to another
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2021 Roland Kujundzic
 */
class DatabaseTransfer {
use \rkphplib\traits\Log;

private $db = null;

private $db_in = null;

private $conf = [];

private $qmap = [];


/**
 * @hash $opt …
 * dsn: required
 * latin1: 1|0=default
 * qmap: { "select.table1": "select1", "insert.table1": "insert1", … } 
 * @eol
 */
public function __construct(array $opt = []) {
	$this->db = Database::getInstance(SETTINGS_DSN);
	$this->db_in = Database::getInstance($opt['dsn']);

	if (!empty($this->conf['latin1'])) {
		$this->db_in->charset = 'latin1';
	}

	$this->qmap= $opt['qmap'];
	unset($opt['qmap']);

	$this->conf = $opt;
}


/**
 * Run "DELETE * FROM $table", conf[select.table] and conf[insert.table].
 */
public function selectInsert(string $table) : void {
	$this->db->execute("DELETE FROM ".Database::table($table));

	$iqn = 'insert.'.$table;
	if (isset($this->qmap[$table]) && is_array($this->qmap[$table])) {
		$query = $this->db->buildQuery($table, 'insert', $this->qmap[$table]);
		$this->db->setQuery($iqn, $query);
		$query = $this->db->buildQuery($table, 'select', $this->qmap[$table]);
	}
	else {
		$this->db->setQuery($iqn, $this->qmap[$iqn]);
		$query = $this->qmap['select.'.$table];
	}

	$dbres = $this->db_in->select($query);
	$this->log('Transfer '.count($dbres)." rows to $table");

	for ($i = 0; $i < count($dbres); $i++) {
		$query = $this->db->getQuery($iqn, $dbres[$i]);
		$this->log('Insert row '.($i + 1), 4);
		$this->db->execute($query);
	}
}


/**
 * Run conf[select.name] and conf[update.name].
 */
public function selectUpdate(string $name, string $desc = '') : void {
	$query = $this->qmap['select.'.$name];
	$dbres = $this->db_in->select($query);

	if ($desc) {
		$this->log($desc.' ('.count($dbres).')');
	}
	else {
		$this->log('Update '.count($dbres)." rows");
	}

	$uqn = 'update.'.$name;
	$this->db->setQuery($uqn, $this->qmap[$uqn]);
	for ($i = 0; $i < count($dbres); $i++) {
		$query = $this->db->getQuery($uqn, $dbres[$i]);
		$this->log('Update row '.($i + 1), 4);
		$this->db->execute($query);
	}
}


}

