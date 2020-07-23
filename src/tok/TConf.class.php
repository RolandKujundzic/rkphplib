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

use function rkphplib\lib\conf2kv;
use function rkphplib\lib\kv2conf;



/**
 * Database configuration plugin. Table is cms_conf.
 * Use cms_conf.lid = cms_login.id for user data.
 * Use lid=0 for system data.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TConf implements TokPlugin {
use \rkphplib\traits\Map;

// @param ADatabase $db
private $db = null;

// @param int $lid
private $lid = null;



/**
 * Constructor options:
 *
 * - dsn = empty (default = SETTINGS_DSN)
 * - table.conf = cms_conf
 * - table.link = cms_login
 */
public function __construct(array $options = []) {
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
 * @plugin conf:id|var|get|get_path|set|set_path|set_default|append
 */
public function getPlugins(Tokenizer $tok) : array {
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
 */
public function tok_conf(string $key, string $value) : string {
	$qtype = ($this->lid > 0) ? 'select_user_path' : 'select_system_path';
	$dbres = $this->db->select($this->db->getQuery($qtype, [ 'lid' => $this->lid, 'path' => $key ]));
	$current = (count($dbres) == 0) ? null : $dbres[0]['value'];

	if (is_null($current)) {
		// \rkphplib\lib\log_debug("TConf.tok_conf:110> set [$key]=[$value]");
		$this->set($key, $value);
	}
	else {
		$value = $this->get($key);
	}

	return $value;
}


/**
 * Set login id. If id is empty reset to null (= no login).
 *
 * @tok {conf:id}{login:id}{:conf}
 */
public function tok_conf_id(string $id) : void {
	if (empty($id)) {
		$this->lid = null;
	}
	else if (preg_match('/^[1-9][0-9]*$/', $id)) {
		$this->lid = (int) $id;
	}
	else {
		throw new Exception('invalid id', "id=[$id]");
	}
}


/**
 * Set/Change configuration value.
 *
 * @tok {conf:set:spread_sheet}{:conf}
 * @tok {conf:set}spread_sheet|#|x{:conf}
 */
public function tok_conf_set(string $key, string $value) : void {
	if (empty($key)) {
		list ($key, $value) = explode(HASH_DELIMITER, $value, 2);
	}

	$this->set($key, $value);
}


/**
 * Set/Change configuration map $name path value.
 *
 * @tok {conf:set_path:spread_sheet.shop_item}column_{get:column}.label|#|{get:label}{:conf}
 * @tok {conf:set_path}spread_sheet.{get:table}|#|column_{get:column}.label|#|{get:label}{:conf}
 */
public function tok_conf_set_path(string $name, array $p) : void {

	if (empty($name)) {
		$name = array_shift($p);
	}

	if (count($p) < 2 || empty($p[0])) {
		throw new Exception('invalid argument', "name=$name p: ".print_r($p, true));
	}

	$path = array_shift($p);
	$value = join(HASH_DELIMITER, $p);

	$map = conf2kv($this->get($name));
	self::setMapPathValue($map, $path, $value);
	$this->set($name, kv2conf($map));
}


/**
 * Set configuration value if still unset.
 * 
 * @tok {conf:set_default:since}{date:now}{:conf} - set since="{date:now}" if not already set
 * @tok {conf:set_default}since|#|{date:now}{:conf}
 */
public function tok_conf_set_default(string $key, string $value) : void {
	if (empty($key)) {
		list ($key, $value) = explode(HASH_DELIMITER, $value, 2);
	}

	$qtype = ($this->lid > 0) ? 'select_user_path' : 'select_system_path';
	$dbres = $this->db->select($this->db->getQuery($qtype, [ 'lid' => $this->lid, 'path' => $key ]));
	$current = (count($dbres) == 0) ? null : $dbres[0]['value'];

	if (is_null($current)) {
		$this->set($key, $value);
	}
}


/**
 * Append value to current value. Do not append multiple times (if value is already suffix).
 */
public function tok_conf_append(string $key, string $value) : void {
	$current = $this->get($key);
	$vlen = strlen($value);

	if (substr($current, -1 * $vlen) != $value) {
		$this->set($key, $current.$value);
	}
}


/**
 * Get raw (untokenized) configuration value.
 */
public function tok_conf_var(string $key) : string {
	return $this->get($key);
}


/**
 * Return tokenized configuration value.
 */
public function tok_conf_get(string $key) : string {
	return $this->get($key);
}


/**
 * Return tokenized configuration key.subkey value. If not found
 * return empty string.
 * 
 * @tok {conf:get_path:spread_sheet.shop_item}table{:conf}
 * @tok {conf:get_path}spread_sheet.shop_item|#|table{:conf}
 */
public function tok_conf_get_path(string $name, array $p) : string {
	if (empty($name)) {
		$name = array_shift($p);
	}

	$path = array_shift($p);

	if (count($p) > 0) {
		throw new Exception('invalid parameter list', "name=$name path=$path p: ".print_r($p, true));
	}

	$map = conf2kv($this->get($name));
	$res = kv2conf(self::getMapPathValue($map, $path));
	return $res;
}


/**
 * Return configuration value ($lid null = system).
 */
public function get(string $name) : string {
	$qtype = ($this->lid > 0) ? 'select_user_path' : 'select_system_path';
	$dbres = $this->db->select($this->db->getQuery($qtype, [ 'lid' => $this->lid, 'path' => $name ]));
	return (count($dbres) == 0) ? '' : $dbres[0]['value'];
}


/**
 * Set configuration value, return id. 
 */
public function set(string $name, string $value) : int {
	$qtype = ($this->lid > 0) ? 'select_user_path' : 'select_system_path';
	$path = explode('.', $name);

	if (count($path) > 1) {
		// we need pid
		$path_last = array_pop($path);
		$path_pid = join('.', $path);

		$r = [ 'lid' => $this->lid, 'path' => $path_pid ];

		try {
			$parent = $this->db->selectOne($this->db->getQuery($qtype, $r));
		}
		catch (\Exception $e) {
			if ($e->getMessage() == 'no result' && count($path) == 1) {
				$rp = [ 'lid' => $this->lid, 'pid' => null, 'path' => $path[0], 'name' => '', 'value' => '' ];
				$this->db->execute($this->db->getQuery('insert', $rp));
				$parent = $this->db->selectOne($this->db->getQuery($qtype, $r));
			}
			else {
				throw $e;
			}
		}

		$r = [ 'lid' => $this->lid, 'pid' => $parent['id'], 'path' => $name, 'name' => $path_last, 'value' => $value ];
	}
	else {
		$r = [ 'lid' => $this->lid, 'pid' => null, 'path' => $name, 'name' => $name, 'value' => $value ];
	}

	// check if replace or insert is necessary
	$dbres = $this->db->select($this->db->getQuery($qtype, [ 'lid' => $this->lid, 'path' => $name ]));

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
