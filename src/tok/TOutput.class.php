<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Exception.class.php');
require_once(__DIR__.'/../File.class.php');
require_once(__DIR__.'/../lib/split_str.php');
require_once(__DIR__.'/../lib/is_map.php');

use \rkphplib\Exception;
use \rkphplib\File;


/**
 * Render table data into output template.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TOutput implements TokPlugin {

/** @var array $table */
protected $table = [];

/** @var array[string]string $env */
protected $env = [];

/** @var array[string]string $conf */
protected $conf = [];

/** @var Tokenizer $tok */
private $tok = null;



/**
 * Register output plugin {output:conf|init|loop}.
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$this->tok = $tok;

	$plugin = [];
	$plugin['output:set'] = TokPlugin::REQUIRE_PARAM;
	$plugin['output.get'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['output:conf'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['output:init'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
	$plugin['output:loop'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:header'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:footer'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:empty'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['output'] = 0; // no callback for base plugin

	return $plugin;
}


/**
 * Set conf.name=value.
 *
 * @param string $name
 * @param string $value
 * @return ''
 */
public function tok_output_set($name, $value) {
	$this->conf[$name] = $value;
	return '';
}


/**
 * Get env ($name = key) or conf key ($name = conf.key) value.
 *
 * @throws
 * @param string $name
 * @return string
 */
public function tok_output_get($name) {

	if (mb_substr($name, 0, 5) == 'conf.') {
		$name = mb_substr($name, 5);

		if (!isset($this->conf[$name])) {
			throw new Exception('No such conf key', $name);
		}
		
		return $this->conf[$name];
	}
	
	if (!isset($this->env[$name])) {
		throw new Exception('No such env key', $name);
	}

	return $this->env[$name];
}


/**
 * Show if table is empty.
 *
 * @param string $tpl
 * @return string
 */
public function tok_output_empty($tpl) {
	if ($this->env['total'] > 0) {
		return '';
	}

	return $tpl;
}


/**
 * Show if table is not empty.
 *
 * @param string $tpl
 * @return string
 */
public function tok_output_header($tpl) {
	if ($this->env['total'] == 0) {
		return '';
	}

	if (!empty($this->env['tags'][0]) && $this->tok->hasReplaceTags($tpl, [ $this->env['tags'][0] ])) {
		$replace = [];

		for ($i = 0; $i < count($this->env['tags']); $i++) {
			$tag = $this->env['tags'][$i];

			if ($this->conf['table.columns'] == 'col_1n') {
				// A=chr(65) ... Z=chr(90) AA ... AZ ... ZA .. ZZ
				$replace[$tag] = ($i < 26) ? chr($i + 65) : chr((intval($i / 26) - 1) + 65).chr(($i % 26) + 65);
			}
			else {
				$replace[$tag] = $tag;
  		}

			$tpl = $this->tok->replaceTags($tpl, $replace);
  	}
	}

	return $tpl;
}


/**
 * Show if table is not empty.
 *
 * @param string $tpl
 * @return string
 */
public function tok_output_footer($tpl) {
	if ($this->env['total'] == 0) {
		return '';
	}

	if (isset($this->env['scroll'])) {
		$tpl = $this->tok->replaceTags($tpl, $this->env['scroll'], 'scroll.');
	}

	error_log($tpl."\n", 3, '/tmp/php.fatal');
	return $tpl;
}


/**
 * Show if table is not empty. Concat $tpl #env.total. Replace {:=tag} with row value.
 * Default tags are array_keys(row[0]) ({:=0} ... {:=n} if vector or {:=key} if map).
 * If conf.table.columns=col_1n use {:=col_1} ... {:=col_n} as tags. If col.table.columns=first_list
 * use values from row[0] as tags. Otherwise assume conf.table.columns is a comma separted list
 * of tag names.
 *
 * @throws
 * @param string $arg
 * @return string
 */
public function tok_output_loop($tpl) {
	if ($this->env['total'] == 0) {
		return '';
	}

	$start = $this->env['start'];
	$lang = empty($this->conf['language']) ? '' : $this->conf['language'];
	$output = [];

	for ($i = $start; $i <= $this->env['end']; $i++) {
    $row = $this->table[$i];

		$replace = [];
    $replace['rowpos'] = $this->env['last'] + $i;
    $replace['rownum'] = $this->env['last'] + $i + 1;

		if (!$this->env['is_map']) {
			$j = 0;
			foreach ($row as $key => $value) {
				if ($j >= count($this->env['tags'])) {
					throw new Exception('invalid tag', "i=$i j=$j key=$key value=$value tags: ".
						print_r($this->env['tags'], true).' row: '.print_r($row, true)); 
				}

				$tag = $this->env['tags'][$j];
				$replace[$tag] = $value;
				$j++;
			}
		}
		else {
			$tag_num = count($this->env['tags']);
			for ($j = 0; $j < $tag_num; $j++) {
				$tag = $this->env['tags'][$j];
		
				if (mb_strpos($tag, '.') > 0) {
					throw new Exception("todo: replace $tag");
				}
				else if (!isset($row[$tag]) && !array_key_exists($tag, $row)) {
					throw new Exception('invalid tag '.$tag, "row[$i]: ".print_r($row, true));
				}

				$replace[$tag] = $row[$tag];
			}
		}

		array_push($output, $this->tok->replaceTags($tpl, $replace));

		if ($this->env['rowbreak'] > 0 && $i > 0 && (($i + 1) % $this->env['rowbreak']) == 0 && $i != $this->env['end']) {
			$rowbreak_html = $this->tok->replaceTags($this->conf['rowbreak_html'], [ 'row' =>  ($i + 1) / $this->env['rowbreak'] ]);
			array_push($output, $rowbreak_html);
		}
	}

	if ($this->env['rowbreak'] > 0) {
		$fill_rest = $i % $this->env['rowbreak'];

		for ($j = $fill_rest; $j > 0 && $j < $this->env['rowbreak']; $j++) {
			array_push($output, $this->conf['rowbreak_fill']);
			$i++;
		}
    
		if ($this->env['pagebreak'] > $this->env['rowbreak'] && $i < $this->env['pagebreak'] && 
				!empty($this->conf['pagebreak_fill']) && !empty($this->conf['pagebreak_fill'])) {
			for ($j = $i; $j < $this->env['pagebreak']; $j++) {
				if ($j % $this->env['rowbreak'] == 0) {
					array_push($output, $this->conf['rowbreak_html']);    			
				}

				array_push($output, $this->conf['rowbreak_fill']);    		
    	} 
   	}	
  }

  $res = join('', $output);
  return $res;
}


/**
 * Use if you need more configuration blocks beside {output:init}. Parameter are
 * the same as in tok_output_init. Fill this.conf with default value if 
 * this.conf == [] or $p['reset'] = 1. Overwrite with values from $p. 
 * 
 * @param array[string]string $p
 */
public function tok_output_conf($p) {

	if (count($this->conf) == 0 || !empty($p['reset'])) {
		$this->conf = [
			'reset' => 1,
			'req.last' => 'last',
			'keep' => SETTINGS_REQ_DIR,
			'pagebreak' => 0,
			'pagebreak_fill' => 1,
			'rowbreak' => 0,
			'rowbreak_html' => '</tr><tr>',
			'rowbreak_fill' => '<td></td>',
			'table.columns' => 'array_keys',
			'table.type' => '',			
			'table.data' => '',
			'table.url' => '',
			'scroll.link' => '<a href="index.php?{:=keep}">{:=link}</a>',
			'scroll.first' => '<img src="img/scroll/first.gif" border="0">',
			'scroll.prev' => '<img src="img/scroll/prev.gif" border="0">',
			'scroll.next' => '<img src="img/scroll/next.gif" border="0">',
			'scroll.last' => '<img src="img/scroll/last.gif" border="0">',
			'scroll.no_first' => '<img src="img/scroll/no_first.gif" border="0">',
			'scroll.no_prev' => '<img src="img/scroll/no_prev.gif" border="0">',
			'scroll.no_next' => '<img src="img/scroll/no_next.gif" border="0">',
			'scroll.no_last' => '<img src="img/scroll/no_last.gif" border="0">',
			'scroll.jump' => '{:=min} - {:=max}',
			'scroll.jump_active' => '<b>{:=min} - {:=max}</b>',
			'scroll.jump_delimiter' => '&nbsp;|&nbsp;',
			'scroll.jump_num' => '4'
		];
	}

	foreach ($p as $key => $value) {
		$this->conf[$key] = $value;
	}
}


/**
 * Initialize and retrieve data. Default parameter values are:
 *
 *  reset= 1
 *  req.last= last
 *  keep= SETTINGS_REQ_DIR (comma separated list)
 *  pagebreak= 0
 *  pagebreak_fill= 1
 *  rowbreak= 0
 *  rowbreak_html= </tr><tr>
 *  rowbreak_fill= <td></td>
 *  table.type= (use: "split, |&|, |@|", "split, |&|, |@|, =", csv, unserialize or json)
 *  table.columns= array_keys (or: col_1n / first_list / tag1, ... )
 *  table.url= (e.g. path/to/file = file://path/to/file or http[s]://...) 
 *  table.data= 
 *  scroll.link= <a href="index.php?{:=keep}">{:=link}</a>
 *  scroll.first= <img src="img/scroll/first.gif" border="0">
 *  scroll.prev= <img src="img/scroll/prev.gif" border="0">
 *  scroll.next= <img src="img/scroll/next.gif" border="0">
 *  scroll.last= <img src="img/scroll/last.gif" border="0">
 *  scroll.no_first= <img src="img/scroll/no_first.gif" border="0">
 *  scroll.no_prev= <img src="img/scroll/no_prev.gif" border="0">
 *  scroll.no_next= <img src="img/scroll/no_next.gif" border="0">
 *  scroll.no_last= <img src="img/scroll/no_last.gif" border="0">
 *  scroll.jump= {:=min} - {:=max}
 *  scroll.jump_active= <b>{:=min} - {:=max}</b>
 *  scroll.jump_delimiter= &nbsp;|&nbsp;
 *  scroll.jump_num= 4
 *
 * @see File::loadTable for table.type 
 * @param array[string]string $p
 */
public function tok_output_init($p) {
	$this->tok_output_conf($p);

	$this->env['is_map'] = false;
	$this->env['tags'] = [];

  $this->fillTable();

	if ($this->conf['table.columns'] == 'first_line') {
		$this->env['tags'] = array_shift($this->table);
	}

	$this->env['total'] = count($this->table);
	$this->env['rowbreak'] = intval($this->conf['rowbreak']);

  $this->computePagebreak();

	$start = $this->env['start'];

	if (empty($this->conf['table.columns']) || $this->conf['table.columns'] == 'array_keys') {
		$this->env['tags'] = array_keys($this->table[$start]);
		$this->env['is_map'] = true;
	}
	else if ($this->conf['table.columns'] == 'col_1n') {
		for ($i = 1; $i <= count($this->table[$start]); $i++) {
			array_push($this->env['tags'], 'col_'.$i);
		}
	}
	else if (count($this->env['tags']) == 0) {
		$this->env['tags'] = \rkphplib\lib\split_str(',', $this->conf['table.columns']);
		$this->env['is_map'] = \rkphplib\lib\is_map($this->table[$start]);
	}

	if (count($this->env['tags']) == 0 || strlen($this->env['tags'][0]) == 0) {
		throw new Exception('invalid table columns', 'table.columns='.$this->conf['table.columns'].' tags: '.
			print_r($this->env['tags'], true));
	}
}


/**
 * Compute pagebreak. Set env keys:
 * 
 *  pagebreak= conf.pagebreak
 *  total= #rows
 *  visible= #rows visible
 *  cols= col_1, ... , col_n (if column names are not set)
 *  start= position of first visible row (= 0 if no pagebreak)
 *  end= position of last visible row (= #rows-1 if no pagebreak)
 *  last= 0 or start-1 if pagebreak and start > 0
 *  page= 1
 *  page_num= 1
 *
 */
private function computePagebreak() {

	$pagebreak = intval($this->conf['pagebreak']);
	$this->env['pagebreak'] = $pagebreak;

	if ($pagebreak == 0) {
		$this->env['visible'] = $this->env['total'];
		$this->env['start'] = 0;
		$this->env['end'] = $this->env['total'] - 1;
		$this->env['last'] = 0;
		$this->env['page'] = 1;
		$this->env['page_num'] = 1;
		return;
	}

	$last = $pagebreak ? intval($this->getValue('last')) : 0;

	if ($last < 0) {
		throw new Exception('scroll error', "last=$last < 0");
  }

  if ($last % $pagebreak != 0 || $last != intval($last / $pagebreak) * $pagebreak) {
		throw new Exception('scroll error', "last % pagebreak = $last % $pagebreak != 0 or last != intval(last/pagebreak) * pagebreak");
	}

  $this->env['last'] = $last;
  $this->env['start'] = $last;
  $this->env['end'] = ($last + $pagebreak < $this->env['total']) ? $last + $pagebreak - 1 : $this->env['total'] - 1;
  $this->env['visible'] = $this->env['end'] - $this->env['start'] + 1;
	$this->env['page'] = ($last / $pagebreak) + 1;
	$this->env['page_num'] = ceil($this->env['total'] / $pagebreak);

	$this->computeScroll();
}


/**
 * Set scroll keys in this.env:
 *
 *  scroll_first= link
 *  scroll_prev= link
 *  next_pos= int
 *  scroll_next= link
 *  scroll_last= link
 *  scroll_jump= html
 */
private function computeScroll() {

	if ($this->env['last'] > 0) {
		$scroll['first'] = $this->_scroll_link('first', 0);
		$scroll['prev'] = $this->_scroll_link('prev', ($this->env['last'] - $this->env['pagebreak']));
	}
	else {
		$scroll['first'] = $this->conf['scroll.no_first'];
		$scroll['prev'] = $this->conf['scroll.no_prev'];
	}

	if ($this->env['last'] + $this->env['pagebreak'] < $this->env['total']) {
		$this->env['next_pos'] = $this->env['last'] + $this->env['pagebreak'];
		$scroll['next'] = $this->_scroll_link('next', $this->env['next_pos']);
		$scroll['last'] = $this->_scroll_link('last', ($this->env['page_num'] - 1) * $this->env['pagebreak']);
	}
	else {
		$this->env['next_pos'] = 0;
		$scroll['next'] = $this->conf['scroll.no_next'];
		$scroll['last'] = $this->conf['scroll.no_last'];
	}

	if (!empty($this->conf['scroll.jump']) && $this->conf['scroll.jump_num'] > 0) {
		$scroll['jump'] = $this->getScrollJumpHtml();
	}

	$this->env['scroll'] = $scroll;
}


/**
 * Return scroll jump html.
 * 
 * @return string
 */
private function getScrollJumpHtml() {

	$pbreak = $this->conf['pagebreak'];
	$jn = $this->conf['scroll.jump_num'];
	$j2 = intval($jn / 2);
	$cpage = $this->env['page'];
	$lpage = $this->env['page_num'];

	$jfpage = min($cpage - $j2, $lpage - $jn + 1);
	$jfpage = max(1, $jfpage); 
	$jlpage = min($lpage, $jfpage + $jn - 1);

	$res = '';

	for ($i = $jfpage; $i <= $jlpage; $i++) {
		if ($i != $cpage) {
			$jump = $this->_scroll_link('jump', (($i - 1) * $pbreak));
    }
		else {
			$jump = $this->conf['scroll.jump_active'];
		}

		$jump = $this->tok->replaceTags($jump, [ 'page' => $i ]);
		$jump = $this->tok->replaceTags($jump, [ 'min' => ($i - 1) * $pbreak + 1 ]);

		if ($i * $pbreak <= $this->env['total']) {
			$jump = $this->tok->replaceTags($jump, [ 'max' => $i * $pbreak ]);
    }
    else {
      $jump = $this->tok->replaceTags($jump, [ 'max' => $this->env['total'] ]);
    }

    if ($i > $jfpage) {
      $res .= $this->conf['scroll.jump_delimiter'];
    }

    $res .= $jump;
  }

  return $res;
}


/**
 * Return scroll link. Replace {:=link}, {:=keep[_crypt]} and {:=last} in conf[scroll.$key].
 * 
 * @param string $key
 * @param int $last
 * @return string
 */
private function _scroll_link($key, $last) {

	$kv = [ $this->conf['req.last'] => rawurlencode($last) ];
	$keep_param = \rkphplib\lib\split_str(',', $this->conf['keep']);

	foreach ($keep_param as $name) {
		if (isset($_REQUEST[$name])) {
			$value = $this->getValue($name);
			$kv[$name] = $value;
		}
	}

	$res = $this->tok->replaceTags($this->conf['scroll.link'], [
		'link' => $this->conf['scroll.'.$key],
		'keep' => http_build_query($kv), 
		'keep_crypt' => TBase::encodeHash($kv),
		'last' => $last
	]);

  return $res;
}


/**
 * Get request parameter value. Request key ist $name or this.conf[req.$name] (if defined).
 * 
 * @param string $name
 * @return string
 * 
 */
protected function getValue($name) {
	$key = empty($this->conf['req.'.$name]) ? $name : $this->conf['req.'.$name];
	return isset($_REQUEST[$key]) ? $_REQUEST[$key] : '';
}


/**
 * Load table data from table.data or retrieve from table.url = file|http[s]://.
 *
 */
protected function fillTable() {

	if (!empty($this->conf['table.url'])) {
		$uri = strpos($this->conf['table.url'], '://') ? $this->conf['table.url'] : 'file://'.$this->conf['table.url'];
	}
	else if (!empty($this->conf['table.data'])) {
		$uri = 'string://';
	}
	else {
		throw new Exception('empty table.data and table.url');
	}

	if (empty($this->conf['table.type'])) {
		throw new Exception('empty table.type');
	}

	$table_type = \rkphplib\lib\split_str(',', $this->conf['table.type']);
	$uri = array_shift($table_type).':'.$uri;

	$this->table = File::loadTable($uri, $table_type);
}


}

