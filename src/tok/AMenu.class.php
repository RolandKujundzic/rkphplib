<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Exception.class.php';
require_once __DIR__.'/../lib/split_str.php';
require_once __DIR__.'/../lib/redirect.php';

use rkphplib\Exception;

use function rkphplib\lib\split_str;
use function rkphplib\lib\redirect;



/**
 * Tokenizer base Menu plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class AMenu implements TokPlugin {

/** @var map $conf */
protected $conf = [];

/** @var vector<map> $node */
protected $node = [];

/** @var Tokenizer $tok */
protected $tok = null;

/** @var int $ignore_level */
private  $ignore_level = 0;



/**
 * @plugin menu, menu:add, menu:conf 
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [];
	$plugin['menu'] = TokPlugin::NO_PARAM | TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['menu:add'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['menu:conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::TEXT;

	return $plugin;
}


/**
 * Set menu configuration. 
 *
 * @tok {menu:conf:custom}CUSTOM{:menu}
 * @tok {menu:add:1}_tpl=custom|#|...{:menu}
 */
public function tok_menu_conf(string $name, string $value) : void {

	if (mb_strpos($name, 'level_') === 0 && !empty($this->conf['level_6'])) {
		// reset default configuration
	  for ($i = 0; $i < 7; $i++) {
  	  $ln = 'level_'.($i + 1);
			$this->conf[$ln.'_header'] = '';
			$this->conf[$ln.'_footer'] = '';
			$this->conf[$ln.'_delimiter'] = '';
			$this->conf[$ln] = '';
			$this->conf[$ln.'_hi'] = '';
  	}
	}

	$this->conf[$name] = $value;
}


/**
 * Implement menu output.
 */
abstract public function tok_menu(string $tpl) : string;


/**
 * Add menu node. Call in correct order. Example:
 *
 * {menu:privilege}@priv={login:priv}|#|super=1|#|other=2^N|#|...{:menu}
 *
 * {menu:add:1}label=Main|#|dir=/|#|if={:menu} -> ignore empty if, use dir=/ for active root
 * {menu:add:2}label=Sub 1|#|if_table=shop_customer, shop_item{:menu} -> ignore if table does not exist
 * {menu:add:}level=2|#|lable=sub 2|#|if_priv={:=super} | {:=shop.super}{:menu} -> active if user has super or shop.super priv
 * {menu:add:1}label=Main 2|#|dir=main2{:menu} -> active if dir=main2|main2/*
 * 
 * Parameter: 
 * 
 * - label:
 * - if: ignore if empty
 * - if_table: table1, table2, ... (ignore if one table is missing)
 * - if_priv: name  (super = 2^0 = 1, ToDo = 2^1 = 2, see cms_conf.role.* for app privileges)
 * - dir: e.g. apps/shop/config
 * - level (= param)
 * - type (l|b, autoset)
 */
public function tok_menu_add(string $level, array $node) : void {
	$level = intval($level);

	if (!$level && !empty($node['level'])) {
		$level = intval($node['level']);
	}

	if ($level < 1) {
		throw new Exception('invalid level', print_r($node, true));
	}

	$label = isset($node['label']) ? $node['label'] : (isset($node['dir']) ? $node['dir'] : $level); 

	// \rkphplib\lib\log_debug("AMenu.tok_menu_add:127> label=$label level=$level ignore_level=".$this->ignore_level);
	if ($this->ignore_level > 0 && $level >= $this->ignore_level) {
		// \rkphplib\lib\log_debug("AMenu.tok_menu_add:129> call skipNode and return - label=$label level=$level ignore_level=".$this->ignore_level);
		$this->skipNode($node);
		return;
	}

	$this->ignore_level = 0;

	$nc = count($this->node);
	$prev = ($nc > 0) ? $this->node[$nc - 1] : null;
	$node['id'] = $nc + 1;
	$node['parent'] = 0;

	// \rkphplib\lib\log_debug("AMenu.tok_menu_add:141> nc=$nc id=".($nc + 1)." parent=0 prev=node.".($nc - 1));
	if ($prev) {
		if ($level === $prev['level'] + 1) {
			$node['parent'] = $prev['id'];
			$this->node[$nc - 1]['type'] = 'b'; // set previous node type to branch
		}
		else if ($level === $prev['level']) {
			$node['parent'] = $prev['parent'];
		}
		else if ($level > $prev['level'] + 1) {
			throw new Exception('invalid level', print_r($node, true));
		}
		else if ($level > 0) {
			// level < $prev['level']
			for ($i = $nc - 1; $node['parent'] === 0 && $i >= 0; $i--) {
				$predecessor = $this->node[$i];

				if ($predecessor['level'] === $level) {
					$node['parent'] = $predecessor['parent'];
				}
			}
		}
	}

	if (isset($node['if']) && empty($node['if'])) {
		$this->ignore_level = $level + 1;
		// \rkphplib\lib\log_debug("AMenu.tok_menu_add:167> skipNode and return - if = false");
		$this->skipNode($node);
		return;
	}

	if (!empty($node['if_table']) && !$this->hasTables($node['if_table'])) {
		$this->ignore_level = $level + 1;
		// \rkphplib\lib\log_debug("AMenu.tok_menu_add:174> skipNode and return - no such table ".$node['if_table']);
		$this->skipNode($node);
		return;
	}

	if (isset($node['if_priv']) && !$this->tok->callPlugin('login', 'hasPrivileges', [ $node['if_priv'], true ])) {
		$this->ignore_level = $level + 1;
		$this->skipNode($node);
		return;
	}
	else if (preg_match('/^apps\/([A-Za-z0-9_\-]+)$/', $node['dir'], $match) && !empty($match[1]) && 
						!$this->tok->callPlugin('login', 'tok_login', [ 'conf.'.$match[1].'?' ])) {
		$this->ignore_level = $level + 1;
		$this->skipNode($node);
		return;
	}
		
	$node['level'] = $level;
	$node['type'] = 'l';	// set to leaf first

	if (!empty($node['dir']) && mb_substr($node['dir'], -1) === '/') {
		$node['dir'] = mb_substr($node['dir'], 0, -1);
	}

	// \rkphplib\lib\log_debug("AMenu.tok_menu_add:198> add node: ".print_r($node, true));
	array_push($this->node, $node);
}


/**
 * If skipped node is no current path redirect to conf.redirect_access_denied (= login/access_denied).
 */
private function skipNode(array $node) : void {
	$dir = empty($_REQUEST[SETTINGS_REQ_DIR]) ? '' : $_REQUEST[SETTINGS_REQ_DIR];

	if (empty($dir) || empty($node['dir']) || mb_strpos($dir, $node['dir']) !== 0) {
		return;
	}

	if (isset($node['if_priv']) && !$this->tok->callPlugin('login', 'hasPrivileges', [ $node['if_priv'] ])) {
		// \rkphplib\lib\log_debug("AMenu.skipNode:216> current dir is forbidden - node: ".join('|', $node));
		$redir_url = empty($this->conf['redirect_access_denied']) ? 'login/access_denied' : $this->conf['redirect_access_denied'];
		redirect($redir_url, [ '@link' => 1, '@back' => 1 ]);
	}
}


/**
 * Add hi=1 to this.node if on $_REQUEST[SETTINGS_REQ_DIR] path.
 * Add curr=1 to this.node if node is end of current path.
 */
public function addNodeHi() : void {
	$dir = empty($_REQUEST[SETTINGS_REQ_DIR]) ? '' : $_REQUEST[SETTINGS_REQ_DIR];
	$path = explode('/', $dir);
	$curr_path = '';

	$nc = count($this->node) - 1;
	if ($this->node[$nc]['type'] == 'b') {
		// fix last node type
		$this->node[$nc]['type'] = 'l';
	}

	for ($i = 0; $i < count($path); $i++) {	
		$curr_path .= ($i > 0) ? '/'.$path[$i] : $path[$i];
		$found = false;

		for ($j = 0; !$found && $j < count($this->node); $j++) {
			$node = $this->node[$j];

			if (!isset($node['dir'])) {
				continue;
			}

			if ($node['dir'] == $curr_path) {
				// \rkphplib\lib\log_debug("AMenu.addNodeHi:250> ($i, $j): curr_path=$curr_path node.dir=".$node['dir']);
				$this->node[$j]['hi'] = 1;
				$found = true;

				if ($curr_path == $dir) {
					$this->node[$j]['curr'] = 1;
				}
			}
		}
	}
}


/**
 * Return true if table exists. Parameter is string array with [,] as delimiter.
 */
private function hasTables(string $tables) : bool {
	require_once __DIR__.'/../Database.class.php';
	$db = \rkphplib\Database::getInstance();

	$table_list = split_str(',', $tables);
	foreach ($table_list as $table) {
		if (!$db->hasTable($table)) {
			// \rkphplib\lib\log_debug("AMenu.hasTables:276> if_table = false - missing $table");
			return false;
		}
	}

	return true;
}


/**
 * Return node html. Example:
 *
 * <a href="{:=href}" {:=target}>{:=label}</a>
 * 
 * - href: !empty(node.url) ? node.url : isset(node.dir) ? "index.php?dir=node.dir" : ''
 * - target: !empty(node.target) ? target="node.target" : ''
 * - label: isset(node.label) ? node.label : ''
 */
protected function getNodeHTML(int $n, string $tpl) : string {

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
