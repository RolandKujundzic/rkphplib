<?php

namespace rkphplib;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/Exception.class.php');
require_once(__DIR__.'/lib/conf2kv.php');

use rkphplib\Exception;


/**
 * Tokenizer base Menu plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
abstract class AMenu implements TokPlugin {

/** @var map $conf */
protected $conf = [];

/** @var vector<map> $node */
protected $node = [];

/** @var vector<int> $path */
protected $path = [];

/** @var int $ignore_level */
private  $ignore_level = 0;



/**
 * Return Tokenizer plugin list:
 *
 *  menu, menu:add, menu:conf
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$plugin = [];
	$plugin['menu'] = TokPlugin::NO_PARAM;
	$plugin['menu:add'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['menu:conf'] = TokPlugin::REQUIRE_PARAM;
	return $plugin;
}


/**
 * Set menu configuration. Example (see subclass constructor for default configuration):
 * 
 * @throws
 * @param string $name (required)
 * @param string $value
 */
public function tok_menu_conf($name, $value) {

	if  (!isset($this->conf[$name])) {
		throw new Exception('invalid configuration key', $name);
	}

	$this->conf[$name] = $value;
}


/**
 * Implement menu output.
 *
 * @param string $tpl
 * @return string
 */
abstract public function tok_menu($tpl);


/**
 * Add menu node. Call in correct order. Example:
 *
 * {menu:add:1}label=Main|#|dir=/|#|if={:menu} -> ignore empty if, use dir=/ for active root
 * {menu:add:2}label=Sub 1|#|if_table=shop_customer, shop_item{:menu} -> ignore if table does not exist
 * {menu:add:}level=2|#|lable=sub 2|#|if_priv={:menu} -> ignore if privilege does not exist it current TLogin
 * {menu:add:1}label=Main 2|#|dir=main2{:menu} -> active if dir=main2|main2/*
 * 
 * Parameter: 
 * 
 * - label:
 * - if: ignore if empty
 * - if_table: table1, table2, ... (ignore if one table is missing)
 * - if_priv: shop | admin (one privilege is enough) or shop, admin, ... (all privileges necessary)
 * - dir: e.g. apps/shop/config
 * - level (= param)
 * - type (l|b, autoset)
 * 
 * @param int $level
 * @param map $node
 */
public function tok_menu_add($level, $node) {
	$level = intval($level);

	if (!$level && !empty($node['level'])) {
		$level = intval($node['level']);
	}

	if ($level < 1) {
		throw new Exception('invalid level', print_r($node, true));
	}

	if ($this->ignore_level > 0 && $level > $this->ignore_level) {
		// do not append descendant node
		return;
	}

	$this->ignore_level = 0;

	$nc = count($this->node);
	$prev = ($nc > 0) ? $this->node[$nc - 1] : null;
	$parent = 0;

	if ($prev) {
		if ($level === $prev['level'] + 1) {
			$parent = $nc;
			$prev_node['type'] = 'b'; // set previous node type to branch
		}
		else if ($level === $prev['level']) {
			$parent = $prev['parent'];
		}
		else if ($level > $prev['level'] + 1) {
			throw new Exception('invalid level', print_r($node, true));
		}
		else if ($level > 1) {
			// level < $prev['level']
			for ($i = $nc - 1; $parent === 0 && $i > 0; $i--) {
				$predecessor = $this->node[$i];

				if ($predecessor['level'] === $level) {
					$parent = $predecessor['parent'];
				}
			}
		}
	}

	if (isset($node['if']) && empty($node['if'])) {
		$this->ignore_level = $level + 1;
		return;
	}

	if (!empty($node['if_table'])) {
		require_once(__DIR__.'/Database.class.php');
		$db = Database::getInstance();
		$table_list = \rkphplib\lib\split_str(',', $node['if_table']);
		foreach ($table_list as $table) {
			if (!$db->hasTable($table)) {
				$this->ignore_level = $level + 1;
				return;
			}
		}
	}

	if (!empty($node['if_priv'])) {
		throw new Exception('ToDo: if_priv');
	}

	$node['parent'] = $parent;
	$node['level'] = $level;
	$node['type'] = 'l';	// set to leaf first

	if (!empty($node['dir'])) {
		if (mb_substr($node['dir'], -1) === '/') {
			$node['dir'] = mb_substr($node['dir'], 0, -1);

			if (empty($node['dir'])) {
				array_push($this->path, $nc);
			}
		}

		$dir = empty($_REQUEST[SETTINGS_REQ_DIR]) ? '' : $_REQUEST[SETTINGS_REQ_DIR];
		if (!empty($dir) && ($node['dir'] === $dir || mb_strpos($dir, $node['dir'].'/') === 0)) {
			array_push($this->path, $nc);
		}
	}

	array_push($this->node, $node);
}


/**
 * Return node html. Example:
 *
 * <a href="{:=href}" {:=target}>{:=label}</a>
 * 
 * - href: !empty(node.url) ? node.url : isset(node.dir) ? "index.php?dir=node.dir" : ''
 * - target: !empty(node.target) ? target="node.target" : ''
 * - label: isset(node.label) ? node.label : ''
 * 
 * @param int $n
 * @param string $tpl
 * @return string
 */
protected function getNodeHTML($n, $tpl) {

	$node = $this->node[$n];
	$r = [ 'href' => '' ];

  if (!empty($node['url'])) {
    $r['href'] = $node['url'];
  }
	else if (isset($node['dir'])) {
		$r['href'] = 'index.php?dir='.$node['dir'];
	}

	$r['target'] = empty($node['target']) ? '' : ' target="'.$node['target'].'"';
	$r['label'] = isset($node['label']) ? $node['label'] : '';

  $res = TokMarker::replace($tpl, $tok_replace);
  return $res;
}


}
