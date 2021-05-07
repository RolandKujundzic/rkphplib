<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Database.class.php';
require_once __DIR__.'/../traits/Map.php';
require_once __DIR__.'/../File.class.php';
require_once __DIR__.'/../lib/conf2kv.php';
require_once __DIR__.'/../lib/kv2conf.php';

use rkphplib\Exception;
use rkphplib\ADatabase;
use rkphplib\Database;
use rkphplib\File;

use function rkphplib\lib\conf2kv;
use function rkphplib\lib\kv2conf;


/**
 * Database configuration plugin. Table is cms_conf.
 * Use cms_conf.lid = cms_login.id for user data.
 * Use lid=0 for system data.
 *
 * If {conf:load}path/to/configuration.json|conf{:conf} 
 * is used try configuration value from this file first. 
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TConf implements TokPlugin {
use \rkphplib\traits\Map;

// @var ADatabase $db
private $db = null;

// @var int $lid
private $lid = null;

// @var array $conf
private $conf = null;


/**
 * @hash $options …
 * dsn: empty (default = SETTINGS_DSN)
 * table.conf: cms_conf
 * table.link: cms_login
 * @eol
 */
public function __construct(array $options = []) {
	$default = [
		'table.conf' => 'cms_conf',
		'table.login' => 'cms_login',
		'json' => '',
		'dsn' => ''
	];

	if (!defined('SETTINGS_DSN') && empty($options['dsn'])) {
		return;
	}

	$opt = array_merge($default, $options);
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
public function getPlugins(Tokenizer $tok) : array {
  $plugin = [];
	$plugin['conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REDO;
	$plugin['conf:append'] = TokPlugin::REQUIRE_PARAM | TokPlugin::TEXT;
	$plugin['conf:get'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REDO;
	$plugin['conf:get_path'] = TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY | TokPlugin::REDO;
	$plugin['conf:id'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['conf:load'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['conf:save'] = TokPlugin::KV_BODY;
	$plugin['conf:set'] = TokPlugin::TEXT;
	$plugin['conf:set_path'] = TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['conf:set_default'] = TokPlugin::TEXT;
	$plugin['conf:var'] = TokPlugin::REQUIRE_PARAM;
  return $plugin;
}


/**
 * Save hash to file (.json|.conf). Use $p['save_as'] or $file.
 * If $p is empty save $_REQUEST.
 */
public function tok_conf_save(string $file, array $p) : void {
	if (empty($file) && !empty($p['save_as'])) {
		$file = $p['save_as'];
		unset($p['save_as']);
	}

	if (empty($file)) {
		return;
	}

	if (is_null($p) || count($p) == 0) {
		$p = $_REQUEST;
	}

	if (substr($file, -5) == '.conf') {
		$this->conf = File::saveConf($file, $p);
	}
	else if (substr($file, -5) == '.json') {
		$this->conf = File::saveJSON($file, $p);
	}
	else {
		throw new Exception('invalid configuration file suffix (use .conf|.json)', $file);
	}
}


/**
 * Load configuration file (.json|.conf). Set conf[@file] = $file.
 * @tok {conf:load}path/file.conf{:conf}{set:}{conf:get:*}{:set}
 */
public function tok_conf_load(string $file) : void {
	// \rkphplib\lib\log_debug("TConf.tok_conf_load:134> $file");
	if (!File::exists($file)) {
		$this->conf = [];
	}
	else if (substr($file, -5) == '.conf') {
		$this->conf = File::loadConf($file);
	}
	else if (substr($file, -5) == '.json') {
		$this->conf = File::loadJSON($file);
	}
	else {
		throw new Exception('invalid configuration file suffix (use .conf|.json)', $file);
	}

	$this->conf['@file'] = $file;
}


/**
 * Return tokenized configuration value.
 * If configuration value is not in database add it tokenized.
 * 
 * @tok {conf:since}{date:now}{:conf} - set since=NOW() if not already set
 */
public function tok_conf(string $key, ?string $value) : string {
	if (!is_null($this->conf)) {
		$val = self::array_get($key, $this->conf);
	}
	else {
		$qtype = ($this->lid > 0) ? 'select_user_path' : 'select_system_path';
		$dbres = $this->db->select($this->db->getQuery($qtype, [ 'lid' => $this->lid, 'path' => $key ]));
		$val = (count($dbres) == 0) ? null : $dbres[0]['value'];
	}

	if (is_null($val)) {
		// \rkphplib\lib\log_debug("TConf.tok_conf:169> set [$key]=[$value]");
		$this->set($key, $value);
		$val = is_null($value) ? '' : $value;
	}

	// \rkphplib\lib\log_debug("TConf.tok_conf:174> [$key]=[$val]");
	return $val;
}


/**
 * @example …
 * $x = [ 'a' => [ 'b' => 1, [ 'c' => 2 ], 'b2.c' => 3 ] ];
 * array_get('a', $x) == [ 'b' => 1, [ 'c' => 2 ] ];
 * array_get('a.b', $x) == 1;
 * array_get('a.b.c', $x) == 2;
 * array_get('a.b2.c', $x) == 3;
 * @eol
 * @return any
 */
private static function array_get(string $key, array $p) {
  $path = explode('.', $key);
	$res = null;

  while (!is_null($p) && count($path) > 0) {
		$pkey = join('.', $path);
    $key = array_shift($path);
		if (isset($p[$pkey])) {
			$res = $p[$pkey];
			$p = null;
		}
    if (isset($p[$key])) {
			$p = $p[$key];
			$res = $p;
    }
    else {
     	$p = null;
    }
  }

  return $res;
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
 * Get raw (untokenized = no redo on output) configuration value.
 */
public function tok_conf_var(string $key) : string {
	if (!is_null($this->conf) && isset($this->conf[$key])) {
		return $this->conf[$key];
	}

	return $this->get($key);
}


/**
 * Return tokenized configuration value. Return value hash if key is *.
 *
 * @tok {conf:get:key}
 * @tok {conf:get:*}
 */
public function tok_conf_get(string $key) : string {
	if (!is_null($this->conf)) {
		if ($key == '*') {
			return kv2conf($this->conf);
		}
		else if (isset($this->conf[$key])) {
			return $this->conf[$key];
		}
	}

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
 *
 */
private function updateConfFile() : void {
	$conf = $this->conf;
	$file = $conf['@file'];
	unset($conf['@file']);

	if (substr($file, -5) == '.conf') {
		File::saveConf($file, $conf);
	}
	else {
		File::saveJSON($file, $conf);
	}
}


/**
 * Set configuration value, return id. 
 */
public function set(string $name, ?string $value) : int {
	if (!is_null($this->conf)) {
		// \rkphplib\lib\log_debug("TConf.set:392> [$name]=[$value]");
		$this->conf[$name] = $value;
		$this->updateConfFile();
		return 0;
	}

	// \rkphplib\lib\log_debug("TConf.set:398> [$name]=[$value]");
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
