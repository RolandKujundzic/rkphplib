<?php

namespace rkphplib\tok;


require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/AMenu.class.php');


/**
 * Tokenizer Menu plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TMenu extends AMenu implements TokPlugin {

/**
 * Set default conf. Configuration keys (N = 1 ... 6, IDENT: 1=[], 2=[\t], 3=[\t\t], ...):
 *
 * menu: conf.level_1 = {:=level_1}
 * level_N_header: IDENT_N<ul>
 * level_N_delimiter: \n
 * level_N_footer: IDENT_N</ul>
 * level_N: IDENT_(N+1)<li><a href="{:=link}">{:=label}</a></li>
 * level_N_hi: IDENT_(N+1)<li><a href="{:=link}">{:=label}</a>\n{:=level_(N+1)}</li>
 *
 */
public function __construct() {
	$this->conf['menu'] = '{:=level_1}';

	for ($i = 0; $i < 7; $i++) {
		$ln = 'level_'.($i + 1);
		$tab = str_pad("", $i, "\t", STR_PAD_LEFT);
		$this->conf[$ln.'_header'] = $tab.'<ul>';
		$this->conf[$ln.'_footer'] = $tab.'</ul>';
		$this->conf[$ln.'_delimiter'] = "\n";
		$this->conf[$ln] = $tab."\t".'<li><a href="{:=link}" class="menu_'.$ln.'">{:=label}</a></li>';
		$this->conf[$ln.'_hi'] = $tab."\t".'<li><a href="{:=link}" class="menu_'.$ln.'_hi">{:=label}</a>'."\n{:=level_".($i + 2)."}\n</li>";
	}
}


/**
 * Return menu html.
 * 
 * @throws
 * @param string $tpl (if empty use "{:=level_1}")
 * @return string
 */
public function tok_menu($tpl) {

	$this->addNodeHi();

	if (!empty($tpl)) {
		$this->conf['menu'] = $tpl;
	}

	$html = [ ];
	$this->level_n(0, $html);

	$res = $this->conf['menu'];

	for ($i = 1; $i <= count($html); $i++) {
		$lname = 'level_'.$i;
		$out = $this->conf[$lname.'_header'].join($this->conf[$lname.'_delimiter'], $html[$i]).
			$this->conf[$lname.'_footer'];
		// \rkphplib\lib\log_debug("TMenu.tok_menu> $lname:\n$out");
		$res = $this->tok->replaceTags($res, [ $lname => $out ]);
	}

	// remove next level include
	$res = $this->tok->replaceTags($res, [ 'level_'.$i => '' ]);

	return $res;
}


/**
 * Compute level html.
 *
 * @param int $start 0 ... count(this.node) - 1
 * @param vector-reference &$html
 */
private function level_n($start, &$html) {
	$level = $this->node[$start]['level'];
	$parent = $this->node[$start]['parent'];

	if (!isset($html[$level])) {
		$html[$level] = [];
	}

	for ($i = $start; $i < count($this->node); $i++) {
		$node = $this->node[$i];

		if ($node['level'] == $level && $node['parent'] == $parent) {
			array_push($html[$level], $this->node_html($i));
			if ($node['type'] == 'b' && !empty($node['hi'])) {
				$this->level_n($i + 1, $html);
			}
		}
	}

	return;
}


/**
 * Compute node html.
 * 
 * @param int $pos
 */
private function node_html($pos) {
	$node = $this->node[$pos];
	$lname = 'level_'.$node['level'];

	if (!empty($node['label'])) {
		$node['label_length'] = mb_strlen($node['label']);
	}

	if (empty($node['link'])) {
		if (!empty($node['dir'])) {
			$node['link'] = '{link:}@='.$node['dir'].'{:link}';
		}
	}

	if (!empty($node['_tpl'])) {
		$tpl = $this->conf[$node['_tpl']];
	}
	else {
		$tpl = (!empty($this->conf[$lname.'_hi']) && !empty($node['hi'])) ? $this->conf[$lname.'_hi'] : 
			((!empty($this->conf['level_curr']) && !empty($node['curr'])) ? 
				$this->conf['level_curr'] : $this->conf[$lname]);

		if (empty($node['hi'])) {
			// remove next level include
			$include_tag = 'level_'.($node['level'] + 1);
			$tpl = $this->tok->replaceTags($tpl, [ $include_tag => '' ]);
		}
		else if (!empty($this->conf['level_curr'])) {
			$include_tag = $this->tok->getTag('level_'.($node['level'] + 1));
			$tpl = $this->tok->replaceTags($tpl, [ 'level_next' => $include_tag ]);
		}
	}

	$tpl = $this->tok->replaceTags($tpl, $node);
	// \rkphplib\lib\log_debug("TMenu.node_html> pos=$pos tpl=[$tpl] node: ".print_r($node, true));
	return $tpl;
}


}

