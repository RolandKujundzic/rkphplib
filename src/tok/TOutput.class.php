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
	$plugin['output:conf'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['output:init'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['output:loop'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:header'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:footer'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	$plugin['output:empty'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	return $plugin;
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
private function tok_output_loop($tpl) {
	if ($this->env['total'] == 0) {
		return '';
	}

	$start = $this->env['start'];
	$is_map = false;
	$tags = [];

	if (empty($this->conf['table.columns']) || $this->conf['table.columns'] == 'array_keys') {
		$tags = array_keys($this->table[$start]);
		$is_map = true;
	}
	else if ($this->conf['table.columns'] == 'col_1n') {
		for ($i = 0; $i < count($this->table[$start]); $i++) {
			array_push($tags, 'col_'.$i);
		}
	}
	else if ($this->conf['table.columns'] == 'first_line') {
		$tags = $this->table[0];
	}
	else {
		$tags = \rkphplib\lib\split_str(',', $this->conf['table.columns']);
		$is_map = \rkphplib\lib\is_map($this->table[$start]);
	}

	if (count($tags) == 0 || strlen($tags[0]) == 0) {
		throw new Exception('invalid table columns', 'table.columns='.$this->conf['table.columns'].' tags: '.print_r($tags, true));
	}

	$lang = empty($this->conf['language']) ? '' : $this->conf['language'];

	for ($i = $start; $i <= $this->env['end']; $i++) {
    $row = $this->table[$i];
    $row['rowpos'] = $last + $i;
    $row['rownum'] = $last + $i + 1;

		$replace = [];

		if (!$is_map) {
			$j = 0;
			foreach ($row[$i] as $key => $value) {
				if ($j >= count($tags) - 1) {
					throw new Exception('invalid tag', "i=$i j=$j key=$key value=$value tags: ".print_r($tags, true)); 
				}

				$tag = $tags[$j];
				$replace[$tag] = $value;
				$j++;
			}
		}
		else {
			for ($j = 0; $j < count($tags); $j++) {
				$tag = $tags[$j];
		
				if (mb_strpos($tag, '.') > 0) {
					throw new Exception("todo: replace $tag");
				}
				else if (!isset($row[$i][$tag]) && !array_key_exists($tag, $row[$i])) {
					throw new Exception('invalid tag '.$tag, "row[$i]: ".print_r($row[$i], true));
				}

				$replace[$tag] = $row[$i][$tag];
			}
		}

		array_push($output, $this->tok->replaceTags($tpl, $replace));

    if ($rowbreak > 0 && $i > 0 && (($i + 1) % $rowbreak) == 0 && $i + 1 != count($this->p_table)) {
      array_push($output, str_replace('{:=row}', (($i + 1) / $rowbreak), $this->p_conf['rowbreak_html']));
    }
  }

  if ($rowbreak > 0) {
    $fill_rest = $i % $rowbreak;

    for ($j = $fill_rest; $j > 0 && $j < $rowbreak; $j++) {
      array_push($output, $this->p_conf['rowbreak_fill']);
      $i++;
    }
    
    $pb = empty($this->p_conf['pagebreak']) ? 0 : intval($this->p_conf['pagebreak']);
    
    if ($pb > $rowbreak && $i < $pb && !empty($this->p_conf['pagebreak_fill']) && $this->p_conf['pagebreak_fill'] == 'yes') {
    	for ($j = $i; $j < $pb; $j++) {
    		if ($j % $rowbreak == 0) {
      		array_push($output, $this->p_conf['rowbreak_html']);    			
    		}   		
      	array_push($output, $this->p_conf['rowbreak_fill']);    		
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
			'req.rownum' => 'rownum',
			'keep' => SETTINGS_REQ_DIR,
			'pagebreak' => 0,
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
		if (!isset($this->conf[$key])) {
			$this->conf[$key] = $value;
		}
	}
}


/**
 * Initialize and retrieve data. Default parameter values are:
 *
 *  reset= 1
 *  req.last= last
 *  req.rownum= rownum
 *  keep= SETTINGS_REQ_DIR (comma separated list)
 *  hash_cols= 
 *  pagebreak= 0
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

  $this->fillTable();
	$this->env['total'] = count($this->table);

  $this->computePagebreak();
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

	$last = $pagebreak ? $this->getValue('last') : 0;

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
		$this->env['scroll_first'] = $this->scroll_link('first', 0);
		$this->env['scroll_prev'] = $this->_scroll_link('prev', ($this->env['last'] - $this->env['pagebreak']));
	}
	else {
		$this->env['scroll_first'] = $this->conf['scroll.no_first'];
		$this->env['scroll_prev'] = $this->conf['scroll.no_prev'];
	}

	if ($this->env['last'] + $this->env['pagebreak'] < $this->env['total']) {
		$this->env['next_pos'] = $this->env['last'] + $this->env['pagebreak'];
		$this->env['scroll_next'] = $this->_scroll_link('next', $this->env['next_pos']);
		$this->env['scroll_last'] = $this->_scroll_link('last', ($this->env['page_num'] - 1) * $this->env['page_break']);
	}
	else {
		$this->env['next_pos'] = 0;
		$this->env['scroll_next'] = $this->conf['scroll.no_next'];
		$this->env['scroll_last'] = $this->conf['scroll.no_last'];
	}

	if (!empty($this->conf['scroll.jump']) && $this->conf['scroll.jump_num'] > 0) {
		$this->env['scroll_jump'] = $this->_scroll_jump_html();
	}
}


/**
 * Return scroll link. Replace {:=link}, {:=keep[_crypt]} and {:=last} in conf[scroll.$key].
 * 
 * @param string $key
 * @param int $last
 * @return string
 */
private function _scroll_link($key, $last) {

  $res = str_replace('{:=link}', $this->conf['scroll.'.$key], $this->conf['scroll.link']);

  $keep = urlencode($this->conf['req.last']).'='.urlencode($last);
	$keep_param = \rkphplib\lib\split_str(',', $this->conf['keep']);
	$kv = [];

	foreach ($keep_param as $name) {
		$value = $this->getValue($name);
		$kv[$name] = $value;
	}

  $res = str_replace('{:=keep}', http_build_query($kv), $res);
  $res = str_replace('{:=keep_crypt}', TBase::encodeHash($kv), $res);

	if (strpos($res, '{:=last}') !== false) {
		$res = str_replace('{:=last}', $last, $res);
	}

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

	$table_type = \rkphplib\lib\split_str($this->conf['table.type']);
	$uri = array_shift($table_type);

	$this->table = File::loadTable($uri, $table_type);
}






/**
 * Render output. Examples:
 * 
 *	{output:init} = initialize
 *  rconf = set conf (clear existing)
 *  conf = add to conf
 *  conf.xxx = set conf key xxx
 *  show.xxx = template for show (init, header, loop, footer)
 *  env.xxx = get env key xxx
 *  get = get conf key value
 *  loop = table row template
 *  yes = show if output table is not empty
 *  no = show if output table is empty 
 *  info = replace tags in scroll block
 *  row_N = show Nth row
 *  first_row = show first row
 *  show= use show.xxx template for init + output
 */
public function tokCall($action, $param, $arg) {
   $rows = count($this->p_table);

  if (substr($param, 0, 5) == 'conf.') {
    $key = substr($param, 5);
    $this->p_conf[$key] = trim($arg);
  }
  else if (substr($param, 0, 4) == 'env.') {
    $key = substr($param, 4);

    if (!isset($this->_p[$key])) {
      lib_abort("no such parameter _p[$key]", print_r($this->_p, true));
    }

    $res = $this->_p[$key];
  }
  else if ($param == 'get') {
    $key = trim($arg);

    if (!isset($this->p_conf[$key])) {
      lib_abort('unknown parameter ['.$key.'] call ['.$this->p_plugin_name.':init|conf] first');
    }

    $res = $this->p_conf[$key];
  }
  else if ($param == 'loop') {
    $res = $this->_loop($arg);
  }
  else if ($param == 'rownum') {
    $res = $this->p_rownum;
  }
  else if ($param == 'yes') {
  	if ($rows > 0) {
	    $res = $arg;
  	}
  }
  else if ($param == 'no') {
  	if ($rows == 0) {
	    $res = $arg;
  	}
  }
  else if ($param == 'info') {
    $res = $this->_info($arg);
  }
  else if ($param == 'first_row') {
    $res = $this->_row($arg, 0);
  }
  else if (substr($param, 0, 4) == 'row_') {
    $res = $this->_row($arg, intval(substr($param, 4)) - 1);
  }
  else {
  	lib_abort("invalid parameter [$param]");
  }

  return $res;
}



/**
 * Return row template
 * 
 * @param string $arg
 * @param int $num (0, 1, ... n-1)
 * @return string
 */
private function _row($arg, $num = 0) {

  if ($num < 0 || $num >= count($this->p_table)) {
    return '';
  }

  if (count($this->_p) == 0) {
    lib_abort('call ['.$this->p_plugin_name.':init] first');
  }

  if (count($this->p_table) == 0) {
    return '';
  }

  $res = $arg;

  foreach ($this->p_table[$num] as $key => $value) {
    $res = str_replace('{:='.$key.'}', $value, $res);
  }

  return $res;
}


/**
 * Return scroll info template.
 * 
 * @param string $arg
 * @return string
 */
private function _info($arg) {

  if (count($this->_p) == 0) {
    lib_abort('call ['.$this->p_plugin_name.':init] first');
  }

  if (count($this->p_table) == 0) {
    return '';
  }

  $res = $arg;

  foreach ($this->_p as $key => $value) {
    $res = str_replace('{:='.$key.'}', $value, $res);
  }

  return $res;
}


/**
 * 
 * @return string
 */
private function _scroll_jump_html() {

  $pbreak = $this->p_conf['pagebreak'];
  $jn = $this->p_conf['scroll.jump_num'];
  $j2 = intval($jn / 2);
  $cpage = $this->_p['page'];
  $lpage = $this->_p['page_num'];

  $jfpage = min($cpage - $j2, $lpage - $jn + 1);
  $jfpage = max(1, $jfpage); 
  $jlpage = min($lpage, $jfpage + $jn - 1);
  $res = '';

  for ($i = $jfpage; $i <= $jlpage; $i++) {

    if ($i != $cpage) {
      $jump = $this->_scroll_link('jump', (($i - 1) * $pbreak));
    }
    else {
      $jump = $this->p_conf['scroll.jump_active'];
    }

    $jump = str_replace('{:=page}', $i, $jump);
    $jump = str_replace('{:=min}', (($i - 1) * $pbreak + 1), $jump);

    if ($i * $pbreak <= $this->p_rownum) {
      $jump = str_replace('{:=max}', ($i * $pbreak), $jump);
    }
    else {
      $jump = str_replace('{:=max}', $this->p_rownum, $jump);
    }

    if ($i > $jfpage) {
      $res .= $this->p_conf['scroll.jump_delimiter'];
    }

    $res .= $jump;
  }

  return $res;
}


}

