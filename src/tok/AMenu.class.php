<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Exception.class.php');
require_once(__DIR__.'/../lib/split_str.php');

use \rkphplib\Exception;



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

/** @var Tokenizer $tok */
protected $tok = null;

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
	$this->tok = $tok;

	$plugin = [];
	$plugin['menu'] = TokPlugin::NO_PARAM | TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['menu:add'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['menu:conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::TEXT;
	$plugin['menu:has_priv'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['menu:privileges'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;

	return $plugin;
}


/**
 * Set 2^N map for login.priv.
 *
 * @tok {menu:privileges}super=1|#|site=2|#|....{:menu}
 *
 * @param map $map
 */
public function tok_menu_privileges($map) {
	// \rkphplib\lib\log_debug('AMenu.tok_privileges> set conf.privileges: '.print_r($map, true));
	$this->conf['privileges'] = $map;
}


/**
 * Set menu configuration. Example (see subclass constructor for default configuration):
 *
 * @tok {menu:conf:custom}CUSTOM{:menu}
 * @tok {menu:add:1}_tpl=custom|#|...{:menu}
 *  
 * @throws
 * @param string $name (required)
 * @param string $value
 */
public function tok_menu_conf($name, $value) {

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
 *
 * @param string $tpl
 * @return string
 */
abstract public function tok_menu($tpl);


/**
 * Add menu node. Call in correct order. Example:
 *
 * {menu:privilege}@priv={login:priv}|#|super=1|#|other=2^N|#|...{:menu}
 *
 * {menu:add:1}label=Main|#|dir=/|#|if={:menu} -> ignore empty if, use dir=/ for active root
 * {menu:add:2}label=Sub 1|#|if_table=shop_customer, shop_item{:menu} -> ignore if table does not exist
 * {menu:add:}level=2|#|lable=sub 2|#|if_priv=super,other{:menu} -> active if user has super and other priv
 * {menu:add:1}label=Main 2|#|dir=main2{:menu} -> active if dir=main2|main2/*
 * 
 * Parameter: 
 * 
 * - label:
 * - if: ignore if empty
 * - if_table: table1, table2, ... (ignore if one table is missing)
 * - if_priv: name (see {menu:privileges}name=2^N|#|...{:menu})
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

	$label = isset($node['label']) ? $node['label'] : (isset($node['dir']) ? $node['dir'] : $level); 

	// \rkphplib\lib\log_debug("AMenu.tok_menu_add($label)> level=$level ignore_level=".$this->ignore_level);
	if ($this->ignore_level > 0 && $level >= $this->ignore_level) {
		// \rkphplib\lib\log_debug("AMenu.tok_menu_add($label)> level=$level > ignore_level=".$this->ignore_level.' - return');
		return;
	}

	$this->ignore_level = 0;

	$nc = count($this->node);
	$prev = ($nc > 0) ? $this->node[$nc - 1] : null;
	$node['id'] = $nc + 1;
	$node['parent'] = 0;

	// \rkphplib\lib\log_debug("AMenu.tok_menu_add($label)> nc=$nc id=".($nc + 1)." parent=0 prev=node.".($nc - 1));
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
		// \rkphplib\lib\log_debug("AMenu.tok_menu_add($label)> return - if = false");
		return;
	}

	if (!empty($node['if_table']) && !$this->hasTables($node['if_table'])) {
		$this->ignore_level = $level + 1;
		// \rkphplib\lib\log_debug("AMenu.tok_menu_add($label)> return - no such table ".$node['if_table']);
		return;
	}

	if (isset($node['if_priv']) && !$this->checkPrivileges($node['if_priv'], $node['dir'])) {
		$this->ignore_level = $level + 1;
		return;
	}
		
	$node['level'] = $level;
	$node['type'] = 'l';	// set to leaf first

	if (!empty($node['dir']) && mb_substr($node['dir'], -1) === '/') {
		$node['dir'] = mb_substr($node['dir'], 0, -1);
	}

	// \rkphplib\lib\log_debug("AMenu.tok_menu_add($label)> add node: ".print_r($node, true));
	array_push($this->node, $node);
}


/**
 * Add hi=1 to this.node if on $_REQUEST[SETTINGS_REQ_DIR] path.
 * Add curr=1 to this.node if node is end of current path.
 */
public function addNodeHi() {
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
				// \rkphplib\lib\log_debug("AMenu.addNodeHi> ($i, $j): curr_path=$curr_path node.dir=".$node['dir']);
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
 * Return true if table exists.
 *
 * @param string $tables
 * @return boolean
 */
private function hasTables($tables) {
	require_once(__DIR__.'/../Database.class.php');
	$db = \rkphplib\Database::getInstance();

	$table_list = \rkphplib\lib\split_str(',', $tables);
	foreach ($table_list as $table) {
		if (!$db->hasTable($table)) {
			// \rkphplib\lib\log_debug("AMenu.hsaTables> if_table = false - missing $table");
			return false;
		}
	}

	return true;
}


/**
 * Return 1 if privileges are ok (otherwise '').
 *
 * @tok {menu:has_priv}shop{:menu} -> 1 (if [shop] privilege)
 * @tok {menu:has_priv}super & shop{:menu} -> '' (if [shop && !super] privilege)
 *
 * @param string $priv
 * @return 1|''
 */
public function tok_has_priv($priv) {
	return $this->checkPrivileges($priv) ? 1 : '';
}


/**
 * Return false if privileges do not exist.
 *
 * @param string $require_priv boolean expression e.g (priv1 | priv2) & !priv3 
 * @param string 
 * @return boolean
 */
private function checkPrivileges($require_priv, $dir = '') {

	if (strlen(trim($require_priv)) == 0) {
		return true;
	}

	if (empty($this->conf['privileges'])) {
		throw new Exception('missing login.priv 2^N map - call [menu:privileges]super=1|#|...[:menu]');
	}

	$priv = intval($this->tok->callPlugin('login', 'tok_login', [ 'priv' ])); // 2^n | 2^m | ...
	$tmp = \rkphplib\lib\conf2kv($this->tok->callPlugin('login', 'tok_login', [ 'conf.role' ]));
	$privileges = str_replace('=,', '', join(',', $tmp)); // app1.priv1,app1.priv2,app2.priv1,...

	\rkphplib\lib\log_debug("AMenu.checkPrivileges> require_priv=[$require_priv] dir=[$dir] priv=[$priv] privileges=[$privileges]");
	$priv_list  = explode(',', $privileges);
	$priv_expr  = ' '.$require_priv.' ';

	foreach ($priv_list as $pname) {
		$priv_expr = str_replace(" $pname ", ' 1 ', $priv_expr);
	}

	\rkphplib\lib\log_debug("AMenu.checkPrivileges> priv_expr=[$priv_expr] after @privileges");
	foreach ($this->conf['privileges'] as $pname => $pval) {
		if (mb_strpos($require_priv, $pname) === false) {
			continue;
		}

		$pval = intval($pval);
		$pval = ($priv & $pval) ? 1 : 0;
		$priv_expr = str_replace(" $pname ", " $pval ", $priv_expr);
	}

	\rkphplib\lib\log_debug("AMenu.checkPrivileges> priv_expr=[$priv_expr] after @priv");
	$priv_expr = preg_replace('/ [a-z_\.]+ /', 0, $priv_expr);
	$priv_expr = str_replace(' ', '', $priv_expr);

  $rp_check = trim(strtr($priv_expr, '01)(&|!', '       '));
 	if ($rp_check != '') {
		throw new Exception('invalid privilege ['.$rp_check.']', "priv_expr=[$priv_expr] require_priv=[$require_priv]");
	}

	$res = eval('return '.$priv_expr.';');
	\rkphplib\lib\log_debug("AMenu.checkPrivileges> res=[$res] priv_expr=[$priv_expr]");
	return $res;
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
