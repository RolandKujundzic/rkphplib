<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once __DIR__.'/TokPlugin.iface.php';
require_once $parent_dir.'/ADatabase.class.php';
require_once $parent_dir.'/Database.class.php';
require_once $parent_dir.'/File.class.php';

use rkphplib\Exception;
use rkphplib\ADatabase;
use rkphplib\Database;
use rkphplib\File;



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
 * Return sitemap plugin. 
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok =& $tok;

	$plugin = [];
	$plugin['sitemap'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;

	return $plugin;
}


/**
 * Convert name to SEO url.
 */
public static function name2url(string $name) : string {
	$url = str_replace([ 'Ä', 'ä', 'Ü', 'ü', 'Ö', 'ö', 'ß', ' ' ], [ 'Ae', 'ae', 'Ue', 'ue', 'Oe', 'oe', 'ss', '-' ], trim($name));
	$url = preg_replace('/[^A-Z|a-z|0-9|\-|\_|\%]/', '-', $url);
	$url = preg_replace('/\-+/', '-', $url);

	if (substr($url, -1) == '-') {
		$url = substr($url, 0, -1);
	}

	if (substr($url, 0, 1) == '-') {
		$url = substr($url, 1);
	}

	return $url;
}


/**
 * Return query to fix url with sql.
 */
public static function sqlFixUrl(string $table) : string {

	$rx_url = "REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(url, 'Ü', 'Ue'), ".
		"'Ä', 'Ae'), 'Ö', 'Oe'), 'ä', 'ae'), 'ö', 'oe'), 'ü', 'ue'), 'ß', 'ss')";

	$query = "UPDATE ".ADatabase::escape_name($table)." SET url=".
		"REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE($rx_url, ".
		"'[^A-Z|a-z|0-9|\\-|\\_|\\.|\\%]', '-'), '\\-+', '-'), '\\-\\\.', ''), '^\\-', '') ".
		"WHERE url IS NOT NULL AND url != ''";

	return $query;
}


/**
 * Use alias in /.htaccess:
 *
 * Options +FollowSymlinks
 * RewriteEngine on
 * RewriteRule ^(.+)\.([0-9]+)(1|2)\.html$ /index.php?alias=$1.$2$3&atype=$3&%{QUERY_STRING} [nc]
 *
 * @export dir, url, id, name, cat, url_append
 */
public function __construct(array $custom_query_map = []) {

	if (is_null($this->db)) {
		$default_query_map = [
			'update_shop_item_url' => "UPDATE shop_item i, shop_customer c SET i.url=".
				"CONCAT(brand, ' ', i.model, ' in ', c.city, '.', SUBSTRING(i.id, -3), '2.html') ".
				"WHERE i.owner=3 AND c.owner=3 AND i.seller=c.id",

			'update_shop_cat_url' => "UPDATE shop_category SET url=".
				"CONCAT(name, '.', SUBSTRING(id, -3), '1.html') WHERE owner=3",

			'fix_shop_item_url' => self::sqlFixUrl('shop_item'),

			'fix_shop_cat_url' => self::sqlFixUrl('shop_category'),

			'shop_cat_alias' => "SELECT id AS s_cat_id, name, 'brands' AS dir, url, DATE_FORMAT(lchange, '%Y-%m-%d') AS lchange ".
				"FROM shop_category WHERE url={:=url} AND owner=3 AND (ti > 0 OR tii > 0) AND status='active'",

			'shop_item_alias' => "SELECT id, brand AS cat, IF(model, model, brand) AS name, 'watch' AS dir, ".
				"url, DATE_FORMAT(lchange, '%Y-%m-%d') AS lchange FROM shop_item WHERE url={:=url} AND owner=3 AND status='active'"
			];

		$this->db = Database::getInstance(SETTINGS_DSN, array_merge($default_query_map, $custom_query_map));

		$this->db->setQuery('shop_cat_alias_all', str_replace("url='url' AND ", '', 
			$this->db->getQuery('shop_cat_alias', [ 'url' => 'url' ])));

		$this->db->setQuery('shop_item_alias_all', str_replace("url='url' AND ", '', 
			$this->db->getQuery('shop_item_alias', [ 'url' => 'url' ])));
	}

	if (empty($_REQUEST['alias']) || empty($_REQUEST['atype'])) {
		return;
	}

	$url = $_REQUEST['alias'];
	$type = intval($_REQUEST['atype']);

	switch ($type) {
		case 1:
			$dbres = $this->db->selectOne($this->db->getQuery('shop_cat_alias', [ 'url' => $url.'.html' ]));
			break;
		case 2:
			$dbres = $this->db->selectOne($this->db->getQuery('shop_item_alias', [ 'url' => $url.'.html' ]));
			break;
	}

	foreach ($dbres AS $key => $value) {
		$_REQUEST[$key] = $value;
	}
}


/**
 * Update url in shop_item or shop_category table ($qkey = shop_cat_url|shop_item_url).
 */
public function updateShopUrl(string $qkey) : void {
	if ($this->db->hasQuery('update_'.$qkey)) {
		$this->db->execute($this->db->getQuery('update_'.$qkey));
		$this->db->execute($this->db->getQuery('fix_'.$qkey));
	}
}


/**
 * Create sitemap.xml in DOCROOT. Parameter:
 * 
 * @if= 1 [ignore if empty|otherwise execute only if non-empty]
 * @domain= https://www.domain.tld
 */
public function tok_sitemap(array $kv) : void {
	$xml = chr(60).'?xml version="1.0" encoding="UTF-8"?'.chr(62)."\n".chr(60).
		'urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'.chr(62)."\n";

	File::save(DOCROOT.'/sitemap.xml', $xml);
	$xml = '';
	$n = 0;

	$qlist = [ 'shop_cat_url' => 'shop_cat_alias_all', 'shop_item_url' => 'shop_item_alias_all' ];

	foreach ($qlist as $qkey_url => $qkey_select) {
		$this->updateShopUrl($qkey_url);

		if (!$this->db->hasQuery($qkey_select)) {
			continue;
		}

		$this->db->execute($this->db->getQuery($qkey_select), true);

		while (($row = $this->db->getNextRow())) {
			$xml .= "<url>\n<loc>".$kv['@domain'].'/'.str_replace([ '&' ], [ '&amp;' ], $row['url'])."</loc>\n";
			$xml .= "\t<lastmod>".$row['lchange']."</lastmod>\n";
			$xml .= "\t<changefreq>weekly</changefreq>\n";
			$xml .= "</url>\n";
			$n++;

			if ($n % 100 == 0) {
				File::append(DOCROOT.'/sitemap.xml', $xml);
				$xml = '';
			}
		}
	}

	$xml .= '</urlset>';
	File::append(DOCROOT.'/sitemap.xml', $xml);
}


}
