<?php

namespace rkphplib;

require_once(PATH_RKPHPLIB.'Database.class.php');
require_once(PATH_RKPHPLIB.'Exception.class.php');



/**
 * Syncronize local database with remote database.
 * Retrieve only partial data.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class DatabaseSync {

/** @var ADatabase $local_db */
protected $local_db = null;

/** @var ADatabase $remote_db */
protected $remote_db = null;

/** @var config */
private $config = [ 'table' => '', 'id_col' => 'id', 'id' => '' ];

/** @var map $cache */
private $cache = [];



/**
 * Initialize database connections. Options:
 *
 * - remote_dsn: 
 * - local_dsn: 
 *
 */
public function __construct($options) {
	$required = [ 'local_dsn', 'remote_dsn' ];

	foreach ($required as $key) {
		if (empty($options[$key])) {
			throw new Exception('empty option '.$key);
		}
	}

	$this->local_db = Database::getInstance($options['local_dsn']);
	$this->remote_db = Database::getInstance($options['remote_dsn']);
}


/**
 * Set configuration value (trim). Keys: table, id.
 * Return value.
 *
 * @param string $key
 * @param string $value
 * @return string 
 * @throws
 */
public function set($key, $value) {

	if (!isset($this->config[$key])) {
		throw new Exception('no such configuration key '.$key);
	}

	$value = trim($value);

	if (empty($value)) {
		if (isset($this->config[$key])) {
			return $this->config[$key];
		}

		throw new Exception('empty configuration key '.$key.' value');
	}

	$this->config[$key] = $value;

	return $value;
}


/**
 * Get configuration value.
 */
public function get($key) {

	if (!isset($this->config[$key])) {
		throw new Exception('no such configuration key '.$key);
	}

	$value = $this->config[$key];

	if (empty(trim($value))) {
		throw new Exception('empty configuration key '.$key.' value');
	}

	return $value;
}


/**
 * Return true if local table is superset of remote table.
 *
 * @param string $table (optional)
 * @return bool
 */
public function compareTable($table = '') {
	$remote_desc = $this->remote_db->getTableDesc($table);
	$local_desc = $this->remote_db->getTableDesc($table);
	$table = $this->set('table', $table);

	foreach ($remote_desc as $col => $info) {
		if (!isset($local_desc[$col])) {
			return false;
		}

		foreach ($info as $key => $value) {
			if ($local_desc[$col][$key] != $value) {
				return false;
			}
		}
	}

	return true;
}


/**
 * Sync entry from remote to local database. If custom_data is set
 * overwrite remote data with custom data. If $id value column is
 * not id use table.column_name as $table.
 *
 * @param string $table
 * @param int $id
 * @param array $custom_data
 * @throws
 */
public function syncEntry($table = '', $id = '', $custom_data = []) {

	$id_col = 'id';
	if (($pos = strpos($table, '.')) > 0) {
		$table = substr($table, 0, $pos);
		$id_col = self::escape_name(substr($table, $pos + 1));
	}

	$table = $this->set('table', $table);
	$id_col = $this->set('id_col', $id_col);
	$id = $this->set('id', $id);

	$r = [ '_table' => $table, 'id' => $id, '_id_col' => $id_col ];
	$query = "SELECT * FROM {:=_table} WHERE {:=_id_col}={:=id}";

	$this->remote_db->setQuery('select_remote', $query);
	$remote = array_merge($this->remote_db->selectOne($this->remote_db->getQuery('select_remote', $r)), $custom_data);

	$this->local_db->setQuery('select_local', $query);
	$tmp = $this->local_db->select($this->local_db->getQuery('select_local', $r));
	$local = (count($tmp) == 1) ? $tmp[0] : [];

	if ($this->compareRows($remote, $local)) {
		$this->log("$table.{$id_col}=$id ok");
	}
	else {
		$type = 'insert';
		if (count($local) == 1) {
			$type = 'update';
			$remote['@where'] = "WHERE $id_col={:=id}";
		}

		$this->log("$table.{$id_col}=$id $type");
		$query = $this->local_db->buildQuery($table, $type, $remote);
		$this->local_db->execute($query);
	}

	$this->syncForeignKeyReferences($table, $id_col, $id);
}


/**
 * Recursive loop syncEntry - syncForeignKeyReferences.
 *
 * @param string $table
 * @param string $id_col
 * @param string $id
 */
private function syncForeignKeyReferences($table, $id_col, $id) {

	$references = $this->local_db->getReferences($table, $id_col);

	foreach ($references as $r_table => $r_cols) {
		if (count($r_cols) == 1) {
			$all_references = $this->local_db->getReferences($r_table, '*');

			if (count($all_references) == 2) {
				// select distinct tour(=curr_table.col) AS value from pano_tour_conf_hist where uid=13(=pano_login.id);
				// foreach result: syncEntries(in_table.col, value)
				continue;
			}
			else if (count($all_references) > 2) {
				print "ToDo: ...\n";
				continue;				
			}

			$r = [ '_table' => $r_table, '_id_col' => $r_cols[0], 'id' => $id ];
			$dbres = $this->remote_db->select($this->remote_db->getQuery('select_remote', $r));
			foreach ($dbres as $row) {
				$this->syncEntry($r_table, $row['id']);
			}
		}
		else {
			print "SKIP 2 - $r_table has ".join(', ', $r_cols)."\n";
		}
	}
}


/**
 * Return true if values in $a are the same in $b.
 *
 * @param array $a
 * @param array $b
 * @return bool
 */
private function compareRows($a, $b) {
	foreach ($a as $key => $value) {
		if (!array_key_exists($key, $b) || $b[$key] != $value) {
			return false;
		}
	}

	return true;
}


/**
 * Print message.
 *
 * @param string $message
 */
protected function log($message) {
	print $message."\n";
}


}

