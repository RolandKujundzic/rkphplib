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

	if (!empty($tpl)) {
		$this->conf['menu'] = $tpl;
	}

	$html = [ ];

	for ($i = 0; $i < count($this->node); $i++) {
		$node = $this->node[$i];
		$level = $node['level'];

		if (!isset($html[$level])) {
			$html[$level] = [];
		}

		array_push($html[$level], $this->node_html($i));
	}

	$res = $this->conf['menu'];

	for ($i = 1; $i <= count($html); $i++) {
		$lname = 'level_'.$i;
		$out = $this->conf[$lname.'_header'].join($this->conf[$lname.'_delimiter'], $html[$i]).
			$this->conf[$lname.'_footer'];
		\rkphplib\lib\log_debug("TMenu.tok_menu> $lname:\n$out");
		$res = $this->tok->replaceTags($res, [ $lname => $out ]);
	}

	return $res;
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

	$hi_name = empty($this->conf[$lname.'_hi']) ? $lname : $lname.'_hi';
	$tpl = isset($this->path[$pos]) ? $this->conf[$hi_name] : $this->conf[$lname];
	$tpl = $this->tok->replaceTags($tpl, $node);

	return $tpl;
}


}

