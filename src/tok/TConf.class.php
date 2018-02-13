<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/Database.class.php');

use \rkphplib\Exception;
use \rkphplib\ADatabase;
use \rkphplib\Database;



/**
 * Database configuration plugin. Table is cms_conf.
 * Use cms_conf.lid = cms_login.id for user data.
 * Use lid=NULL for system data.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TConf implements TokPlugin {

/** @param map system configuration */
public $sconf = [];

/** @param map user configuration */
public $uconf = [];

/** @param ADatabase $db */
public $db = null;

/** @param int $lid */
public $lid = null;



/**
 * Constructor options:
 *
 * - dsn = empty (default = SETTINGS_DSN)
 * - table.conf = cms_conf
 * - table.link = cms_login
 *
 * @param map $options
 */
public function __construct($options = []) {
	$default_options = [
		'table.conf' => 'cms_conf',
		'table.login' => 'cms_login',
		'dsn' => ''
		];

	if (!defined('SETTINGS_DSN')) {
		return;
	}

	$opt = array_merge($default_options, $options);
  $table = ADatabase::escape($opt['table.conf']);
  $table_login = ADatabase::escape($opt['table.login']);

  $query_map = [
		'insert' => "INSERT INTO $table (lid, pid, path, name, value) VALUES ({:=lid}, {:=pid}, {:=path}, {:=name}, {:=value})",
		'update' => "UPDATE $table SET value={:=value} WHERE id={:=id}",
		'select_user_path' => "SELECT id, pid, path, name, value FROM $table WHERE lid={:=lid} AND path={:=path}",
    'select_system_path' => "SELECT id, pid, path, name, value FROM $table WHERE lid IS NULL AND path={:=path}",
  ];

	$this->db = Database::getInstance($opt['dsn'], $query_map);
}


/**
 *
 */
public function getPlugins($tok) {
  $plugin = [];
	$plugin['conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REDO;
	$plugin['conf:id'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['conf:var'] = TokPlugin::REQUIRE_PARAM;
	$plugin['conf:get'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REDO;
	$plugin['conf:set'] = TokPlugin::REQUIRE_PARAM | TokPlugin::TEXT;
	$plugin['conf:set_default'] = TokPlugin::REQUIRE_PARAM | TokPlugin::TEXT;
	$plugin['conf:append'] = TokPlugin::REQUIRE_PARAM | TokPlugin::TEXT;
  return $plugin;
}


/**
 * Return tokenized configuration value. If configuration value is not in database add it tokenized.
 * 
 * @tok {conf:since}{date:now}{:conf} - set since=NOW() if not already set
 *
 * @param string $key
 * @param string $value
 * @return string
 */
public function tok_conf($key, $value) {
	$qtype = (intval($this->lid) > 0) ? 'select_user_path' : 'select_system_path';
	$lid = ($this->lid > 0) ? intval($this->lid) : null;

	$dbres = $this->db->select($this->db->getQuery($qtype, [ 'lid' => $lid, 'path' => $key ]));
	$current = (count($dbres) == 0) ? null : $dbres[0]['value'];

	if (is_null($current)) {
		// \rkphplib\lib\log_debug("tok_conf: set [$key]=[$value]");
		$this->set($lid, $key, $value);
	}
	else {
		$value = $this->get($lid, $key);
	}

	return $value;
}


/**
 * Set login id. If id is empty reset to null (= no login).
 *
 * @param string
 * @return ''
 */
public function tok_conf_id($id) {
	if (intval($id) < 1) {
		if ($id == '') {
			$this->lid = null;
		}
		else {
			throw new Exception('invalid id', "id=[$id]");
		}
	}

	$this->lid = $id;
	return '';
}


/**
 * Set configuration value. 
 *
 * @param string $key
 * @param string $value
 * @return ''
 */
public function tok_conf_set($key, $value) {
	$this->set($this->lid, $key, $value);
	return '';
}


/**
 * Set configuration value if still unset.
 * 
 * @tok {conf:set_default:since}{date:now}{:conf} - set since="{date:now}" if not already set
 *
 * @param string $key
 * @param string $value
 * @return string
 */
public function tok_conf_set_default($key, $value) {
	$qtype = (intval($this->lid) > 0) ? 'select_user_path' : 'select_system_path';
	$lid = ($this->lid > 0) ? intval($this->lid) : null;

	$dbres = $this->db->select($this->db->getQuery($qtype, [ 'lid' => $lid, 'path' => $key ]));
	$current = (count($dbres) == 0) ? null : $dbres[0]['value'];

	if (is_null($current)) {
		$this->set($lid, $key, $value);
	}

	return '';
}



/**
 * Append value to current value. Do not append multiple times (if value is already suffix).
 *
 * @param string $key
 * @param string $value
 * @return ''
 */
public function tok_conf_append($key, $value) {
	$current = $this->get($this->lid, $key);

	$vlen = strlen($value);
	if (substr($current, -1 * $vlen) == $value) {
		return '';
	}

	$this->set($this->lid, $key, $current.$value);
	return '';
}


/**
 * Get raw (untokenized) configuration value.
 *
 * @param string $key
 * @return string
 */
public function tok_conf_var($key) {
	return $this->get($this->lid, $key);
}


/**
 * Return tokenized configuration value.
 *  
 * @param string $key
 * @return string
 */
public function tok_conf_get($key) {
	return $this->get($this->lid, $key);
}


/**
 * Return configuration value.
 *
 * @throws
 * @param int $lid (0 = system)
 * @param string $name
 * @return string
 */
public function get($lid, $name) {
	$qtype = (intval($lid) > 0) ? 'select_user_path' : 'select_system_path';
	$lid = ($lid > 0) ? intval($lid) : null;

	$dbres = $this->db->select($this->db->getQuery($qtype, [ 'lid' => $lid, 'path' => $name ]));
	return (count($dbres) == 0) ? '' : $dbres[0]['value'];
}


/**
 * Set configuration value, return id. 
 *
 * @throws
 * @param int $lid (0 = system)
 * @param string $name
 * @param string $value
 * @return int
 */
public function set($lid, $name, $value) {
	$qtype = (intval($lid) > 0) ? 'select_user_path' : 'select_system_path';
	$lid = ($lid > 0) ? intval($lid) : null;
	$path = explode('.', $name);

	if (count($path) > 1) {
		// we need pid
		$path_last = array_pop($path);
		$path_pid = join('.', $path);

		$r = [ 'lid' => $lid, 'path' => $path_pid ];

		try {
			$parent = $this->db->selectOne($this->db->getQuery($qtype, $r));
		}
		catch (\Exception $e) {
			if ($e->getMessage() == 'no result' && count($path) == 1) {
				$rp = [ 'lid' => $lid, 'pid' => null, 'path' => $path[0], 'name' => '', 'value' => '' ];
				$this->db->execute($this->db->getQuery('insert', $rp));
				$parent = $this->db->selectOne($this->db->getQuery($qtype, $r));
			}
			else {
				throw $e;
			}
		}

		$r = [ 'lid' => $lid, 'pid' => $parent['id'], 'path' => $name, 'name' => $path_last, 'value' => $value ];
	}
	else {
		$r = [ 'lid' => $lid, 'pid' => null, 'path' => $name, 'name' => $name, 'value' => $value ];
	}

	// check if replace or insert is necessary
	$dbres = $this->db->select($this->db->getQuery($qtype, [ 'lid' => $lid, 'path' => $name ]));

	if (count($dbres) == 0) {
		$this->db->execute($this->db->getQuery('insert', $r));
		$id = $this->db->getInsertId();
	}
	else {
		$id = $dbres[0]['id'];
		$r['id'] = $id;
		$this->db->execute($this->db->getQuery('update', $r));
	}

	return $id;
}


}
