<?php

namespace rkphplib;

require_once __DIR__.'/Database.class.php';
require_once __DIR__.'/lib/latin1_to_utf8.php';
require_once __DIR__.'/traits/Log.php';

use function rkphplib\lib\latin1_to_utf8;


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

	$this->qmap= $opt['qmap'];
	unset($opt['qmap']);

	$this->conf = $opt;
}


/**
 * Run "DELETE * FROM $table", conf[table.select] and conf[table.insert].
 */
public function selectInsert(string $table) : void {
	$this->log("Transfer into $table");

	$this->db->execute("DELETE FROM ".Database::table($table));

	$query = $this->qmap['select.'.$table];
	$this->log(substr($query, 0, 80).' …', 4);
	$dbres = $this->db_in->select($query);

	$iqn = 'insert.'.$table;
	$this->db->setQuery($iqn, $this->qmap[$iqn]);
	foreach ($dbres as $row) {
		if (!empty($this->conf['latin1'])) {
			latin1_to_utf8($row);
		}

		$query = $this->db->getQuery($iqn, $row);
		$this->log(substr($query, 0, 80).' …', 4);
		$this->db->execute($query);
	}
}


}

