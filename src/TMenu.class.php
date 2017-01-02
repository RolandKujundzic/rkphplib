<?php

namespace rkphplib;

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
		if (mb_strpos($tpl, '{:=level_1}') === false) {
			throw new Exception('invalid level_1 template', 'no {:=level_1} tag in: '.$tpl);
		}

		$this->conf['level_1'] = $tpl;
	}

	return $this->level_n(0);
}


/**
 * Return level n html.
 * 
 * @param int $pos
 * @return string
 */
private function level_n($pos) {
	$curr_level = 0;
	$res = '';

	for ($i = $pos; $i < count($this->node); $i++) {
		$node = $this->node[$i];
		$level = $node['level'];

		if (!$curr_level) {
			$curr_level = $level;
		}

		if (isset($this->path[$i])) {
			if ($node['type'] === 'b') {
				$sublevel_tag = '{:=level_'.($curr_level + 1).'}';
				$res .= str_replace($sublevel_tag, $this->level_n($i + 1), $this->level_html($i, $curr_level, true));
			}
			else {
				$res .= $this->level_html($i, $curr_level, true);
			}
		}
		else {
			$res .= $this->level_html($i, $curr_level, false);
		}
	}

	return $res;
}


/**
 * Return level html.
 * 
 * @param int $pos
 * @param int $level
 * @param bool $is_active
 * @return string
 */
private function level_html($pos, $level, $is_active) {

	$has_delimiter = (isset($this->node[$pos - 1]) && $this->node[$pos - 1]['level'] === $level) ? true : false;

	$lname = 'level_'.$level;
	$header = empty($this->conf[$lname.'_header']) ? '' : $this->conf[$lname.'_header'];
	$delimiter = (!$has_delimiter || empty($this->conf[$lname.'_delimiter'])) ? '' : $this->conf[$lname.'_delimiter'];
	$footer = empty($this->conf[$lname.'_footer']) ? '' : $this->conf[$lname.'_footer'];
	$hi_name = empty($this->conf[$lname.'_hi']) ? $lname : $lname.'_hi';
	$tpl = $is_active ? $this->conf[$hi_name] : $this->conf[$lname];

	foreach ($this->node[$pos] as $key => $value) {
		$tpl = str_replace('{:='.$key.'}', $value, $tpl);
	}

	return $delimiter.$header.$tpl.$footer;
}


}

