<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/Database.class.php');

use \rkphplib\Exception;
use \rkphplib\Database;



/**
 * Sitemap plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TSitemap implements TokPlugin {

public $tok = null;

protected $conf = [];

protected $db = null;



/**
 * 
 */
public function getPlugins($tok) {
	$this->tok =& $tok;
  $plugin = [];

  $plugin['sitemap'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY;

  return $plugin;
}


/**
 * Use alias in /.htaccess:
 *
 * Options +FollowSymlinks
 * RewriteEngine on
 * RewriteRule ^(.*)\.([0-9]+)\.html$ /index.php?alias=$1.$2&%{QUERY_STRING} [nc]
 *
 * SELECT REGEXP_REPLACE(REPLACE(model, ' ', '-'), '[^A-Za-z0-9\-]', '') AS url
 */
public function __construct() {

	if (empty($_REQUEST['alias'])) {
		return;
	}

	$qmap = [
		'shop_cat_alias' => "SELECT id, name, CONCAT('&s_cat_id=', id) AS url_append, 'watches' AS dir, name ".
			"FROM shop_category WHERE name='{:=url}' AND status='active'",

		'shop_item_alias' => "SELECT id, 'watch' AS dir, brand, model ".
			"FROM shop_item WHERE model='{:=url}' AND status='active'",

		'content_alias' => "SELECT 'ToDo'"
	];

  $this->db = Database::getInstance(SETTINGS_DSN, $qmap);

	$tmp = explode('.', $_REQUEST['alias']);
	$url = str_replace(' ', '%20', $tmp[0]);
	$type = $tmp[1];
	$extra = (count($tmp) > 2) ? $tmp[2] : '';

	switch ($type) {
		case 1:
			$dbres = $this->db->selectOne($this->db->getQuery('shop_cat_alias', [ 'url' => $url ]));
			break;
		case 2:
			$dbres = $this->db->selectOne($this->db->getQuery('shop_item_alias', [ 'url' => $url ]));
			break;
		case 3:
			$dbres = $this->db->selectOne($this->db->getQuery('content_alias', [ 'url' => $url ]));
	}

	foreach ($dbres AS $key => $value) {
		$_REQUEST[$key] = $value;
	}
}


/**
 *
 */
public function tok_sitemap($action, $param, $arg) {
  $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
		'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

// | MAAS-RX-40-Kreuzzeiger-SWR--PWR-Meter          | MAAS RX-40 Kreuzzeiger SWR & PWR Meter 

  if ($action == 'sitemap') {
		if ($param == 'init') {
			$this->_init(lib_arg2hash($arg));
		}
		else if ($param == 'meta') {
			$res = $this->_meta($arg);
		}
	}
	else if ($action == 'tok_replace') {
		$this->_metatag['found'] = true;

		if (substr($param, 0, 5) == 'meta_') {
			$meta = substr($param, 5);
			$this->_metatag[$meta] = trim($arg);
		}

		if ($this->_metatag['update']) {
			return '{tok_replace:'.$param.'}'.preg_replace("/[\r\n]+/", '', trim($_REQUEST[$meta])).'{:tok_replace}';
		}
	}
}


}
