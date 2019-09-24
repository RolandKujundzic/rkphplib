<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once __DIR__.'/TokPlugin.iface.php';
require_once $parent_dir.'/Database.class.php';
require_once $parent_dir.'/traits/Map.trait.php';
require_once $parent_dir.'/lib/conf2kv.php';
require_once $parent_dir.'/lib/kv2conf.php';

use rkphplib\Exception;
use rkphplib\ADatabase;
use rkphplib\Database;




/**
 * Database configuration plugin. Table is cms_conf.
 * Use cms_conf.lid = cms_login.id for user data.
 * Use lid=NULL for system data.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TConf implements TokPlugin {
use \rkphplib\traits\Map;

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
 * Return {conf:[id|var|get|get_path|set|set_path|set_default|append}
 */
public function getPlugins($tok) {
  $plugin = [];
	$plugin['conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REDO;
	$plugin['conf:id'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['conf:var'] = TokPlugin::REQUIRE_PARAM;
	$plugin['conf:get'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REDO;
	$plugin['conf:get_path'] = TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY | TokPlugin::REDO;
	$plugin['conf:set'] = TokPlugin::TEXT;
	$plugin['conf:set_path'] = TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['conf:set_default'] = TokPlugin::TEXT;
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
		// \rkphplib\lib\log_debug("TConf.tok_conf:114> set [$key]=[$value]");
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
 * @tok {conf:id}{login:id}{:conf}
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
 * Set/Change configuration value.
 *
 * @tok {conf:set:spread_sheet}{:conf}
 * @tok {conf:set}spread_sheet|#|x{:conf}
 *
 * @param string $key
 * @param string $value
 * @return ''
 */
public function tok_conf_set($key, $value) {
	if (empty($key)) {
		list ($key, $value) = explode(HASH_DELIMITER, $value, 2);
	}

	$this->set($this->lid, $key, $value);
	return '';
}


/**
 * Set/Change configuration map $name path value.
 *
 * @tok {conf:set_path:spread_sheet.shop_item}column_{get:column}.label|#|{get:label}{:conf}
 * @tok {conf:set_path}spread_sheet.{get:table}|#|column_{get:column}.label|#|{get:label}{:conf}
 *
 * @param string $name
 * @param vector $p ([name|#|]path|#|value)
 * @return ''
 */
public function tok_conf_set_path($name, $p) {

	if (empty($name)) {
		$name = array_shift($p);
	}

	if (count($p) < 2 || empty($p[0])) {
		throw new Exception('invalid argument', "name=$name p: ".print_r($p, true));
	}

	$path = array_shift($p);
	$value = join(HASH_DELIMITER, $p);

	$map = \rkphplib\lib\conf2kv($this->get($this->lid, $name));
	self::setMapPathValue($map, $path, $value);
	$this->set($this->lid, $name, \rkphplib\lib\kv2conf($map));
	return '';
}


/**
 * Set configuration value if still unset.
 * 
 * @tok {conf:set_default:since}{date:now}{:conf} - set since="{date:now}" if not already set
 * @tok {conf:set_default}since|#|{date:now}{:conf}
 *
 * @param string $key
 * @param string $value
 * @return string
 */
public function tok_conf_set_default($key, $value) {
	if (empty($key)) {
		list ($key, $value) = explode(HASH_DELIMITER, $value, 2);
	}

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
 * Return tokenized configuration key.subkey value. If not found
 * return empty string.
 * 
 * @tok {conf:get_path:spread_sheet.shop_item}table{:conf}
 * @tok {conf:get_path}spread_sheet.shop_item|#|table{:conf}
 * 
 * @param string $name
 * @param vector $p
 * @return string
 */
public function tok_conf_get_path($name, $p) {
	
	if (empty($name)) {
		$name = array_shift($p);
	}

	$path = array_shift($p);

	if (count($p) > 0) {
		throw new Exception('invalid parameter list', "name=$name path=$path p: ".print_r($p, true));
	}

	$map = \rkphplib\lib\conf2kv($this->get($this->lid, $name));
	$res = \rkphplib\lib\kv2conf(self::getMapPathValue($map, $path));
	return $res;
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
