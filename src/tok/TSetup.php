<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Database.php';
require_once __DIR__.'/../File.php';

use rkphplib\Exception;
use rkphplib\File;


/**
 * Website setup plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TSetup implements TokPlugin {

// @var rkphplib\tok\Tokenizer $tok
private $tok = null;


/**
 * Return Tokenizer plugin list:  setup
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

  $plugin = [];
  $plugin['setup:database'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  $plugin['setup:table'] = TokPlugin::KV_BODY;
  $plugin['setup:install'] = TokPlugin::KV_BODY;
	$plugin['setup'] = 0;
  return $plugin;
}


/**
 * Install from source to docroot. Checkout to source directory. Copy files and directories. 
 * Execute {load:static/*} and {include:preload/*} in all *.inc.html files. Parameter:
 *
 *  - source: ../src
 *  - git: user@host:/path/to/git
 *  - copy_directory: img, css, fonts, js, ...
 * 
 * @throws
 * @param string $file
 * @return string
 */
public function tok_setup_install($p) {
	throw new Exception('ToDo ...');
}


/**
 * Setup database. Example:
 * 
 * {setup:database}engine=|#|login=|#|password=|#|name=|#|host=|#|dsn=|#|
 *   admin_login=|#|admin_password=|#|admin_name=|#|admin_host=|#|admin_dsn={:setup}
 *
 * @param map<string:string> $p
 * @return string
 */
public function tok_setup_database($p) {

	if (empty($p['admin_dsn']) && !empty($p['admin_login']) && !empty($p['admin_password']) && 
			!empty($p['admin_name']) && !empty($p['admin_host'])) {
	}

	if (empty($p['admin_dsn'])) {
		return $this->inputDSN();
	}

	$db = Database::getInstance();
	$db->createDatabase($dsn);
}


/**
 * Setup table. Example:
 * 
 * {login_check:}redirect_login=...{:} -> check login authentication - if not found or expired redirect to redirect_login
 *
 * @param map<string:string> $p
 * @return ''
 */
public function tok_setup_table($p) {
	$db = Database::getInstance();
	$db->createTable($p);
}


}
