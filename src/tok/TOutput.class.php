<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Exception.class.php');
require_once(__DIR__.'/../File.class.php');
require_once(__DIR__.'/../lib/split_str.php');

use \rkphplib\Exception;
use \rkphplib\File;


/**
 * Render table data into output template.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TOutput implements TokPlugin {

/*
protected $p_table = array();
protected $p_rownum = 0;
protected $p_conf = array();
protected $p_plugin_name;

private $_p = array();
private $_tpl = array();
private $_reset = true;
*/

/** @var array $table */
protected $table = [];

/** @var array[string]string $env */
protected $env = [];

/** @var array[string]string $conf */
protected $conf = [];


/**
 * Register output plugin {output:conf|init|loop}.
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$plugin = [];
	$plugin['output:conf'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['output:init'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['output:loop'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
	return $plugin;
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
			'hash_cols' => '',
			'pagebreak' => 0,
			'rowbreak' => 0,
			'rowbreak_html' => '</tr><tr>',
			'rowbreak_fill' => '<td></td>',
			'table.type' => 'split,|&|,|@|',			
			'table.data' => 
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
 *  template.loop= loop
 *  template.scroll= scroll
 *  table.type= split, |&|, |@| (or csv, unserialize and json)
 *  table.url= 
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

  if ($this->conf['template.loop'] != 'loop' || $this->conf['template.search'] != 'search') {
    $this->customTemplate();
  }

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
 * Return scroll link. Replace {:=link}, {:=keep} and {:=last} in conf[scroll.$key].
 * 
 * @param string $key
 * @param int $last
 * @return string
 */
private function _scroll_link($key, $last) {

  $res = str_replace('{:=link}', $this->conf['scroll.'.$key], $this->conf['scroll.link']);
  $keep = urlencode($this->conf['req.last']).'='.urlencode($last).$this->p_conf['keep'];
  $res = str_replace('{:=keep}', $keep, $res);

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
  $res = '';

  if ($action != $this->p_plugin_name) {
    return $res;
  }

  $rows = count($this->p_table);

  else if ($param == 'show') {
    $res = $this->_show(lib_arg2hash($arg));
  }
  else if (substr($param, 0, 5) == 'show.') {
    $key = substr($param, 5);
    $this->_tpl[$key] = trim($arg);
  }
  else if (substr($param, 0, 5) == 'conf.') {
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
 * Show custom template.
 */
private function customTemplate() {

  $cookie = empty($this->p_conf['template.cookie']) ? '' : $this->p_conf['template.cookie'];
  $tpl_req= $this->p_conf['template'];
  $tpl = '';

  if (!$cookie) {
    $this->p_conf['keep'] .= ','.$tpl_req;
  }

  if (!empty($_REQUEST[$tpl_req])) {
    $tpl =  $_REQUEST[$tpl_req];

    if ($cookie) {
      setcookie($cookie, $tpl, time() + 3600 * 24 * 30);
    }
  }
  else if (!empty($_COOKIE[$cookie])) {
    $tpl = $_COOKIE[$cookie];
    $_REQUEST[$tpl_req] = $tpl;
  }

  if (!$tpl) {
    $tpl_list = explode(',', $this->p_conf['template.default']);

    if (strpos($tpl_list[0], ':') !== false) {
      for ($i = 0; !$tpl && $i < count($tpl_list); $i++) {
        list ($num, $name) = explode(':', trim($tpl_list[$i]));

        if ($this->p_rownum <= $num) {
          $tpl = $name;
        }
      }
    }
    else {
      $tpl = $tpl_list[0];
    }

    if (!$tpl) {
      $tpl = $name;
    }

    $_REQUEST[$tpl_req] = $tpl;

    if ($cookie) {
      setcookie($cookie, $tpl, time() + 3600 * 24 * 30);
    }
  }

  $tpl_param = array('pagebreak', 'rowbreak', 'rowbreak_html', 'rowbreak_fill');
  foreach ($tpl_param as $key) {
    if (isset($this->p_conf['template.'.$tpl.'.'.$key])) {
      $this->p_conf[$key] = $this->p_conf['template.'.$tpl.'.'.$key];
    }
  }
}



/**
 * Use conf.show_init for _init() and conf.show_loop for loop.
 * Return conf.show_header + conf.show_loop + conf.show_footer.
 * Replace parameter in header, footer and loop block.
 * 
 * @param hash
 */
private function _show($p) {
  $res = '';

  $this->_reset = true;

  $this->_init(TokMarker::replace($this->_tpl['init'], $p));

  if ($this->p_rownum > 0) {
    $res = TokMarker::replace($this->_tpl['header'], $p).
       $this->_loop(TokMarker::replace($this->_tpl['loop'], $p)).
       TokMarker::replace($this->_tpl['footer'], $p);
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
 * @param array $row
 * @param array $lang_cols
 * @return array
 */
private function _language_fix($row, $lang_cols) {

  $cols = array_keys($row);

  foreach ($cols as $col) {

    if (substr($col, -3, 1) != '_') {
      continue;
    }

    $base = substr($col, 0, -3);
    if (isset($row[$base]) || !isset($lang_cols[$base])) {
      continue;
    }

    for ($i = 0; !isset($row[$base]) && $i < count($lang_cols[$base]); $i++) {
      $lc = $base.'_'.$lang_cols[$base][$i];

      if (strlen($row[$lc]) > 0) {
        $row[$base] = $row[$lc];
      }
    }

    if (!isset($row[$base])) {
      // all entries are empty ...
      $row[$base] = '';
    }
  }

  return $row;
}


/**
 * 
 * @param array $cols
 * @return array
 */
private function _language_cols($cols) {

  if (empty($this->p_conf['language'])) {
    return array();
  }

  $lang_suffix = lib_str2array($this->p_conf['language']);

  if (count($lang_suffix) < 1 || empty($lang_suffix[0])) {
    return array();
  }

  if (count($lang_suffix) < 2 || $lang_suffix[0] == $lang_suffix[1]) {
    // if we have no fallback language use fallback en, de, ... 
    $lang_suffix[1] = ($lang_suffix[0] == 'en') ? 'de' : 'en';
  }

  $lcol = array();
  foreach ($cols as $col) {

    if (substr($col, -3, 1) != '_') {
      continue;
    }

    if (!in_array(substr($col, -2), $lang_suffix)) {
      continue;
    }

    $base = substr($col, 0, -3);
    if (!isset($lcol[$base])) {
      $lcol[$base] = 0;
    }

    $lcol[$base]++;
  }

  $res = array();
  foreach ($lcol as $base => $num) {
    if ($num > 1) {
      $res[$base] = $lang_suffix;
    }
  }

  return $res;
}


/**
 * 
 * @param string $arg
 * @return string
 */
private function _loop($arg) {

  if (!empty($this->p_conf['loop_tag'])) {
    $arg = str_replace($this->p_conf['loop_tag'], ':=', $arg);
  }

  $this->_reset = true;

  if (count($this->_p) == 0) {
    lib_abort('call ['.$this->p_plugin_name.':init] first');
  }

  if (count($this->p_table) == 0) {
    return '';
  }

  $rowbreak = $this->p_conf['rowbreak'];
  $last = $this->p_conf['last'];

  $output = array();
  $lang_cols = array();

  $erase_tags = (!empty($this->p_conf['erase_tags']) && $this->p_conf['erase_tags'] == 'yes') ? 
    true : false;
  $erase_tags_with = ($erase_tags && !empty($this->p_conf['erase_tags_with'])) ? 
    $this->p_conf['erase_tags_with'] : '';

  for ($i = 0; $i < count($this->p_table); $i++) {
    $row = $this->p_table[$i];
    $row['rowpos'] = $last + $i;
    $row['rownum'] = $last + $i + 1;

    if ($i == 0) {
      $lang_cols = $this->_language_cols(array_keys($row));
    }

    if (count($lang_cols) > 0) {
      $row = $this->_language_fix($row, $lang_cols);
    }

    $entry = TokMarker::replace($arg, $row, $erase_tags, $erase_tags_with);

    if (strpos($entry, '{:=_column_hash}') !== false) {
      $entry = str_replace('{:=_column_hash}', lib_hash2arg($row), $entry);
    }

    array_push($output, $entry);

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

?>
