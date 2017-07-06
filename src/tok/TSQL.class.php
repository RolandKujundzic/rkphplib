<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Database.class.php');
require_once(__DIR__.'/../lib/conf2kv.php');

use \rkphplib\Database;


/**
 * Execute SQL queries.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TSQL implements TokPlugin {

/** @var ADatabase $db */
protected $db = null;



/**
 * Register output plugins. Examples:
 *
 *  {sql:query}SELECT * FROM test WHERE name LIKE '{:=name}%' OR id={:=name}{:sql}
 *
 *  {sql:set:test}SELECT * FROM test WHERE name LIKE '{:=name}%' OR id={:=name}{:sql}
 *  {sql:query:test}name=something{:sql}
 *
 *  {sql:dsn}mysqli://user:pass@tcp+localhost/dbname{:sql} (use SETTINGS_DSN by default)
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
  $this->tok = $tok;

  $plugin = [];
  $plugin['sql:query'] = 0;
	$plugin['sql:dsn'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY; 
	$plugin['sql:set'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['sql'] = 0;

/*
  $plugin['output.get'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
  $plugin['output:conf'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['output:init'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  $plugin['output:loop'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
  $plugin['output:header'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
  $plugin['output:footer'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::REDO;
  $plugin['output:empty'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
*/

  return $plugin;
}


/**
 * Constructor.
 */
public function __construct() {
	if (defined('SETTINGS_DSN')) {
		$this->db = Database::getInstance();
	}
}


/**
 * Set database connection string. Example:
 *
 * {sql:dsn}mysqli://user:pass@tcp+localhost/dbname{:sql}
 *
 * @throws
 * @param string $dsn
 * @return ''
 */
public function tok_sql_dsn($dsn) {
	$this->db = Database::getInstance($dsn);
}


/**
 * Define query. Example:
 *
 * {sql:set:test}SELECT * FROM test WHERE id={:=id}{:sql}
 * {sql:query:test}
 *
 * @throws
 * @param string $qkey
 * @param string $query
 * @return ''
 */
public function tok_sql_set($qkey, $query) {
	$this->db->setQuery($qkey, $query);
}


/**
 * Execute sql query. Example:
 *
 * {sql:query}UPDATE test SET name={:=name} WHERE id={:=id}{:sql}
 *
 * {sql:set:test}SELECT * FROM test WHERE id={:=id}{:sql}
 * {sql:query:test}id=31{:sql}
 *
 * @throws
 * @param string
 * @param string
 * @return ''
 */
public function tok_sql_query($qkey, $query) {

	if (!empty($qkey)) {
		$replace = \rkphplib\lib\conf2kv($query);
		$query = $this->db->getQuery($qkey, $replace);
	}

	$this->db->execute($query);
	return '';
}


	
/**
 * @tok_plugin_list esc, null, op_html, sql_cmp, sql_name, sql_in, sql_set, 
 *   sql_password, sql_format, search_split, sql_parameter, search_range
 */
public static function getPluginList() {

  $res = array('esc', 'null', 'op_html', 'sql_cmp', 'sql_name', 'sql_in',
    'sql_set', 'sql_password', 'sql_format', 'search_split', 'sql_parameter',
  	'search_range', 'sql_check');

  return $res;
}


/**
 * 
 */
public static function call($name, $param, $arg) {
  $res = '';

  if ($name == 'esc') {
    $res = SQLPlugin::_do_esc($param, $arg);
  }
  else if ($name == 'null') {
    $res = SQLPlugin::_do_null($param, $arg);
  }
  else if ($name == 'op_html') {
    $res = SQLPlugin::_do_op_html($param, trim($arg));
  }
  else if ($name == 'sql_set') {
    $res = SQLPlugin::_do_sql_set($param, trim($arg));
  }
  else if ($name == 'sql_cmp') {
    $res = SQLPlugin::_do_sql_cmp($param, trim($arg));
  }
  else if ($name == 'sql_name') {
		if ($param == 'language') {
			$res = SQLPlugin::_do_sql_name_language(lib_arg2hash($arg));
		}
		else {
	    $res = Database::escape_name(trim($arg));
		}
  }
  else if ($name == 'sql_in') {
    $res = SQLPlugin::_do_sql_in($param, $arg);
  }
  else if ($name == 'sql_password') {
    $res = SQLPlugin::_do_sql_password($arg);
  }
  else if ($name == 'sql_check') {
    $res = SQLPlugin::_do_sql_check($param, trim($arg));
  }
  else if ($name == 'sql_format') {
    $res = SQLPlugin::_do_sql_format(lib_arg2hash($arg));
  }
  else if ($name == 'search_split') {
    $res = SQLPlugin::_do_search_split($param, $arg);
  }
  else if ($name == 'search_range') {
    $res = SQLPlugin::_do_search_range(trim($param), trim($arg));
  }
  else if ($name == 'sql_parameter') {
    $res = SQLPlugin::_do_sql_parameter($param, $arg);
  }
  else {
  	lib_abort("invalid name [$name]");
  }

  return $res;
}


/**
 * Return comma separated list of dir, last, sort, search and s_* parameter (if set).
 * If param is list like: -name1, -name2, ... remove parameter name1, name2, ... 
 * @param string $param
 * @param string_hash $arg
 * @return string
 */
private static function _do_sql_parameter($param, $arg) {
	
	$remove_param = array();
	
	if (!empty($param)) {
		$tmp = lib_explode(',', $param);
		foreach ($tmp as $name) {
			if (substr($name, 0, 1) == '-') {
				array_push($remove_param, substr($name, 1));
			}
		}
	}
	
  $param_list = empty($arg) ?
  	array('dir', 'last', 'sort', 'search') : lib_arg2hash($arg);
	
	if (count($remove_param) > 0) {
		$use_list = array();
		
		foreach ($param_list as $key) {
  		if (!in_array($key, $remove_param)) {
  			array_push($use_list, $key);
  		}
  	}
  	
  	$param_list = $use_list;
	}

  // detect search parameter ... s_*
  $req_keys = array_keys($_REQUEST);
  foreach ($req_keys as $key) {
    if (substr($key, 0, 2) == 's_') {
      array_push($param_list, $key);
    }
  }

  $res = join(',', $param_list);
  return $res;
}


/**
 * Return col='val' or (col >= 'val1' AND col <= 'val2') if val=[val1-val2].
 * If val1 or val2 is empty return col <= 'val2' or col >= 'val1'.
 * If val is empty try _REQUEST[s_col].
 * 
 * @param string $col
 * @param string $val
 * @return string
 */
private static function _do_search_range($col, $val) {

	if (empty($val) && !empty($_REQUEST['s_'.$col])) {
		$val = trim($_REQUEST['s_'.$col]);
	}
	
	if (strpos($val, '-') !== false) {
		list ($from, $to) = explode('-', $val, 2);
		
		if ($from && $to) {
			$res = "($col >= '".Database::escape(trim($from))."' AND $col <= '".
				Database::escape(trim($to))."')";
		}
		else if ($to) {
			$res = "$col <= '".Database::escape(trim($to))."'";
		}
		else if ($from) {
			$res = "$col >= '".Database::escape(trim($from))."'";			
		}
	}
	else {
		$res = "$col='".Database::escape($val)."'";
	}
	
	return $res;
}


/**
 * Plugin implementation of search_split.
 * 
 * [search_split:a,b]Hans Muster[:] = [search_split:]Hans Muster|#|a,b[:] = 
 * ((a LIKE '%Hans%' AND a LIKE '%Muster%') OR (b LIKE '%Hans%' AND b LIKE '%Muster%'))
 *
 * [search_split:a,b?]Hans Muster[:] = [search_split:?]Hans Muster|#|a,b[:] = 
 * ((a LIKE '%Hans%' OR b LIKE '%Hans%') AND (a LIKE '%Muster%' OR b LIKE '%Muster%'))
 *
 * [search_split:a,b]"John D." -Smith +123[:] =
 * ((a LIKE '%John D.' AND a NOT LIKE '%Smith%' AND a LIKE '%123%') OR
 *  (b LIKE '%John D.' AND b NOT LIKE '%Smith%' AND b LIKE '%123%'))
 *
 * [search_split:a,b?]"John D." -Smith +123[:] = ERROR, exclude makes no sense
 * 
 * @param string $param
 * @param array $arg
 * @return string
 */
private static function _do_search_split($param, $arg) {

  $tmp = lib_arg2array($arg);
  $and_or = true;

  if (substr($param, -1) == '?') {
    $param = substr($param, 0, -1);
    $and_or = false;
  }

  $cols = (count($tmp) == 2) ? lib_str2array($tmp[1]) : lib_str2array($param);

  // remove doubles and empty cols: (a, , y,a) = (a,y)
  $unique_cols = array();
  $has_col = array();

  foreach ($cols as $col) {
    if ($col && !isset($has_col[$col])) {
      array_push($unique_cols, $col);
    }
  }

  $expression = trim($tmp[0]);

  if (strlen($expression) == 0 || count($unique_cols) == 0) {
    return '';
  }

  $expr_list = SQLPlugin::_search_split_txt2sql($expression, $and_or);

  if (count($expr_list) == 0) {
    return '';
  }

  if ($and_or) {
    $and_sql = join(' AND ', $expr_list);
    $or_sql = array();

    foreach ($unique_cols as $col) {
      array_push($or_sql, str_replace('$col', $col, $and_sql));
    }

    $res = (count($or_sql) > 1) ? '('.join(') OR (', $or_sql).')' : $or_sql[0];
  }
  else {
    $and_sql = array();

    foreach ($expr_list as $expr) {
      $or_sql = array();

      foreach ($unique_cols as $col) {
        array_push($or_sql, $col.' '.$expr);
      }

      array_push($and_sql, join(' OR ', $or_sql));
    }

    $res = (count($and_sql) > 1) ? '('.join(') AND (', $and_sql).')' : $and_sql[0];
  }

  return $res;
}


/**
 * 
 * @param string $txt
 * @param boolean $allow_not
 * @return array
 */
private static function _search_split_txt2sql($txt, $allow_not) {
  // e.g. $txt = "John" -Smith +"Peter" +123 
  $list = array();

  $col = $allow_not ? '$col' : '';

  // extract "fixed string" 
  while (!empty($txt) && preg_match('/([+-]?".+?")/', $txt, $match)) {
    $txt = str_replace($match[1], '', $txt);

    $c = substr($match[1], 0, 1);
    if ($c == '-') {
      if (!$allow_not) {
        lib_abort('-"String" is forbidden', $txt);
      }

      $expr = $col.' NOT LIKE '."'%".Database::escape(substr($match[1], 2, -1))."%'";
    }
    else if ($c == '+') {
      $expr = $col.' LIKE '."'%".Database::escape(substr($match[1], 2, -1))."%'";
    }
    else {
      $expr = $col.' LIKE '."'%".Database::escape(substr($match[1], 1, -1))."%'";
    }

    array_push($list, $expr);
  }

  $words = preg_split("/[\r\n\t ]+/", trim($txt));
  $ignore = array('/', '+', '-', '%');

  foreach ($words as $word) {

    if (empty($word) || in_array($word, $ignore)) {
      continue;
    }

    $l = substr($word, -1);
    if ($l == '?' || $l == '.' || $l == ';' || $l == ',') {
      $word = substr($word, 0, -1);
    }

    $c = substr($word, 0, 1);
    if ($c == '+') {
      $expr = $col.' LIKE '."'%".Database::escape(substr($word, 1))."%'";
    }
    else if ($c == '-') {
      if (!$allow_not) {
        lib_abort('-Expression is forbidden', $word);
      }

      $expr = $col.' NOT LIKE '."'%".Database::escape(substr($word, 1))."%'";
    }
    else {
      $expr = $col.' LIKE '."'%".Database::escape($word)."%'";
    }

    array_push($list, $expr);
  }

  return $list;
}


/**
 * Return check result. Parameter:
 * 
 *  - is_select: return 1 if argument starts with select 
 * 
 * @param string $param
 * @param string $arg
 * @return string
 */
private static function _do_sql_check($param, $arg) {
  $res = '';

	if ($param == 'is_select') {
		if (strtolower(substr($arg, 0, 6)) == 'select') {
			$res = 1;
		}
	}

	return $res;
}


/**
 * 
 * @param hash $p
 * @return string
 */
private static function _do_sql_format($p) {
  $res = '';
 
  if (empty($p['col']) || empty($p['format'])) {
    lib_abort("[sql_format:]col=column_name|#|format=(|time|map)...[:sql_format]");
  }

  if (!preg_match("/^[\.a-zA-Z0-9_]+$/", $p['col'])) {
    lib_abort('invalid column name ['.$p['col'].']');
  }

  if ($p['format'] == '') {
    $res = SQLPlugin::_sql_format_($p);
  }
  else if ($p['format'] == 'time') {
    $res = SQLPlugin::_sql_format_($p, true);
  }
  else if ($p['format'] == 'map') {
    $res = SQLPlugin::_sql_format_map($p);
  }

  return $res;
}


/**
 * 
 * @param hash $p
 * @param booelan $use_time
 * @return string
 */
private static function _sql_format_($p, $use_time = false) {

  if (empty($p['lang'])) {
    lib_abort('[sql_format:]col='.$p['col'].'|#|format='.$p['format'].
      '|#|lang=(de|en)...[:sql_format]');
  }

  $time_format = $use_time ? " %H:%i:%s')" : "')";
  $res = '_FORMAT('.$p['col'].", '%Y-%m-%d".$time_format;

  if ($p['lang'] == 'de') {
    $res = '_FORMAT('.$p['col'].", '%d.%m.%Y".$time_format;
  }

  return $res;
}


/**
 * 
 * @param hash $p
 * @return string
 */
private static function _sql_format_map($p) {

  $if = array();

  foreach ($p as $key => $value) {
    if ($key != 'col' && $key != 'format') {
      $cmp = 'IF('.$p['col']."='".Database::escape($key)."'";
      $true = ", '".Database::escape($value)."'"; 
      array_push($if, $cmp.$true);
    }
  }

  $res = join(', ', $if).", ''".str_pad('', count($if), ')');
  return $res;
}


/**
 * Return sql set list (without brackets) dependent on param and arg.
 * Examples:
 * 
 * [sql_set:str2set]ABC[:] -> 'A','B','C'
 * [sql_set:hash_set]a=,b=1,c=[:] -> 'B'
 * [sql_set:req_array]x[:] and _REQUEST[x] = array('a', 'b') -> 'a', 'b'
 * [sql_set:has_xyz]ab,xyz[:] -> yes
 * [sql_set:req2int_x] AND x01=a,x02=b -> 2^1 + 2^2 = 6
 * [sql_set:int2req_x]8[:] ->  _REQUEST[x03]=3
 * [sql_set:change_col]a,b,c[:] ->  return set value of changed columns
 * 
 * @param string $param
 * @param string $arg
 * @return string
 */
private static function _do_sql_set($param, $arg) {
  $res = '';

  if ($param == 'str2set') {
    // {sql_set:str2set}ABC{:} = 'A,B,C'
    $set = array();

    for ($i = 0, $len = strlen($arg); $i < $len; $i++) {
      array_push($set, substr($arg, $i, 1));
    }

    $res = "'".join(',', $set)."'";
  }
  else if ($param == 'hash_set') {
    $hash = lib_arg2hash($arg);
    $set = array();

    foreach ($hash as $key => $value) {
      if (!empty($value)) {
        array_push($set, Database::escape_name($key, true));
      }
    }

    $res = empty($set) ? 'NULL' : "'".join(',', $set)."'";
  }
  else if ($param == 'req_array') {
    $name = trim($arg);

    if (isset($_REQUEST[$name]) && is_array($_REQUEST[$name])) {
      $set = array();

      for ($i = 0; $i < count($_REQUEST[$name]); $i++) {
        $value = Database::escape($_REQUEST[$name][$i]);
        array_push($set, $value);
      }

      $res = "'".join("','", $set)."'";
    }
  }
  else if (substr($param, 0, 4) == 'has_') {
    $name = substr($param, 4);
    if (strpos($arg, $name) !== false) {
      $res = 'yes';
    }
  }
  else if (substr($param, 0, 8) == 'req2int_') {
    // {sql_set:req2int_wert} + wert01=1, wert02=2 -> 2^1 + 2^2 = 6
    $prefix = substr($param, 8);
    $res = 0;

    for ($i = 1; $i < 64; $i++) {
      $rkey = $prefix.sprintf("%02d", $i);
      if (!empty($_REQUEST[$rkey])) {
        $res += pow(2, intval($_REQUEST[$rkey]) - 1);
      }
    }
  }
  else if (substr($param, 0, 8) == 'int2req_') {
    // {sql_set:int2req_wert}8{:} -> wert03=3
    $prefix = substr($param, 8);

    for ($i = 0; $i < 64; $i++) {
      if ($arg & pow(2, $i)) {
        $rkey = $prefix.sprintf("%02d", $i + 1);
        $_REQUEST[$rkey] = $i + 1;
      }
    }
  }
  else if ($param == 'change_col') {
  	$keys = lib_explode(',', $arg);
		$curr = isset($_REQUEST['change_col']) ? $_REQUEST['change_col'] : '';
  	$res = 0;
  	
  	for ($i = 0; $i < count($keys); $i++) {
  		$i2 = pow(2, $i);
  		$k = $keys[$i];
  	  
  		if (strpos($curr, $k) !== false) {
  			$res += $i2;
  		}
  		else if (!empty($_REQUEST['md5_'.$k]) && isset($_REQUEST[$k])) {
  	  	$md5_val = md5(preg_replace("/\r?\n/", "\n", $_REQUEST[$k]));

  			if ($_REQUEST['md5_'.$k] != $md5_val) {
  				$res += $i2;
  			}
  		}
  	
  		if ($res == 0) {
  			$res = 'NULL';
  		}
  	}
  }

  return $res;
}


/**
 * Return list of sql column names. Examples:
 * 
 *  {sql_name:language}cols=a,b,c|#|suffix=ch|#|as=x{:sql_name} = a_ch AS x_a, b_ch AS x_b, c_ch AS x_c
 *  {sql_name:language}cols=a,b,c|#|suffix=|#|as=y{:sql_name} = a AS y_a, b AS y_b, c AS y_c
 * 
 * @param hash $p
 * @return string
 */
private static function _do_sql_name_language($p) {

	if (empty($p['as']) || empty($p['cols'])) {
		lib_abort("[sql_name:language]cols=x,y,z|#|as=a|#|...[:sql_name]");
	}

	$suffix = empty($p['suffix']) ? '' : '_'.$p['suffix'];
	$cols = lib_explode(',', $p['cols']);
	$as = $p['as'].'_';
	$clist = array();

	foreach ($cols as $col) {
		array_push($clist, Database::escape_name($col.$suffix).' AS '.Database::escape_name($as.$col));
	}

	return join(', ', $clist);
}


/**
 * Return array [a,b,c] as sql string ('a', 'b', 'c').
 * You may use RETURN instead of comma if there is no comma otherwise.
 * If param is set return [param in (...)] or empty string.
 * 
 * @param string $param
 * @param string_array $arg lib_str2array
 * @return string
 */
private static function _do_sql_in($param, $arg) {

	if (strpos($arg, ',') === false) {
		$list = preg_split("/\r?\n/", trim($arg));
	}
	else {
		$list = lib_explode(',', $arg);
	}
	
  if ($param) {
  	if (strlen($arg) == 0) {
  		return '';
  	}
  		
  	for ($i = 0; $i < count($list); $i++) {
    	$list[$i] = Database::escape($list[$i]);
  	}
  	
 		$res = $param." IN ('".join("', '", $list)."')";
  }
  else {
  	for ($i = 0; $i < count($list); $i++) {
    	$list[$i] = Database::escape($list[$i]);
  	}

 		$res = "('".join("', '", $list)."')";
  }
  
  return $res;
}


/**
 * 
 * @param string_hash $arg
 * @return string
 */
private static function _do_sql_password($arg) {

  $p = lib_arg2array($arg);
  $password = $p[0];

  if (count($p) == 3) {
    $old_password = $p[1]; 
    $crypt = $p[2];

    if ($crypt == 'unix' && ($old_password != $password || 
        substr($old_password, 0, 2) != 'rk')) {
      $password = crypt($password, 'rk');
    }
  }

  $res = "'".Database::escape($password)."'";
  return $res;
}


/**
 * Escape argument (use parameter = t to trim argument). 
 * If parameter is not empty arg is empty use trim(_REQUEST[param]).
 *  
 * @param string $param
 * @param string $arg
 * @return string
 */
private static function _do_esc($param, $arg) {

  if ($param == 't') {
    $arg = trim($arg);
  }
	else if (empty($arg) && !empty($param)) {
		$arg = trim(lib_get($param));
	}

  $res = "'".Database::escape($arg)."'";
  return $res;
}


/**
 * 
 * @param string $param
 * @param string $arg
 * @return string
 */
private static function _do_null($param, $arg) {
  $res = 'NULL';

  if ($param == 't') {
    $arg = trim($arg);
  }

  if (strlen($arg) > 0) {
    $res = $arg;
  }

  if ($res != 'NULL') {
    $res = "'".Database::escape($res)."'";
  }

  return $res;
}


/**
 * If $_REQUEST[$param.'op'] and $colname are not empty return
 * sql search expresson for colname.
 * 
 * Append :date if input is not sql formated or dates are used on datetime cols.
 * 
 * @see SQLPlugin#_do_op_html for possible values of $_REQUEST[$param.'op']
 * @tok_plugin sql_cmp
 * 
 * @param string $param
 * @param string $colname
 * @return string
 */
private static function _do_sql_cmp($param, $colname) {

	$tmp = explode(':', $param);
	$param = $tmp[0];
	$action = empty($tmp[1]) ? '' : $tmp[1];
	
  if (empty($_REQUEST[$param.'op']) || empty($colname)) {
    return '';
  }  
  
  $colname = Database::escape($colname);
  $op = $_REQUEST[$param.'op'];
  
  if ($op == 'empty') {
    $res = "($colname IS NULL OR $colname = '')";
  }
  else if ($op == 'not_empty') {
    $res = "($colname IS NOT NULL AND $colname != '')";
  }

  if (!isset($_REQUEST[$param])) {
  	return '';
  }
    
  $numeric_op = array('<', '>', '<=', '>=', '!=', '=');
  $value = Database::escape($_REQUEST[$param]);
  $res = '';
  
  if ($action == 'date') {
  	$dmy = DateCalc::sql_date($value);

  	if ($op == '=') {
  		$res = '('.$colname." >= '".$dmy." 00:00:01' AND ".$colname." <= '".$dmy." 23:59:59')";
  	}
  	else if ($op == '!=') {
  		$res = '('.$colname." < '".$dmy." 00:00:01' OR ".$colname." > '".$dmy." 23:59:59')";
  	}
  	else {
    	$res = $colname.' '.$op." '".$dmy."'";
		}
	}
  else if (in_array($op, $numeric_op)) {

  	if (strtolower($_REQUEST[$param]) == 'null') {
    	$op = ($op == '=') ? 'IS' : 'IS NOT';
    	$value = 'NULL';
    }

    $res = $colname.' '.$op." '".$value."'";
	}
  else if ($op == 'contains') {
  	$res = $colname." LIKE '%$value%'";
  }
  else if ($op == 'like') {
  	$res = $colname." LIKE '$value'";
  }
  else if ($op == 'not_like') {
  	$res = $colname." NOT LIKE '$value'";
  }
  else if ($op == 'match') {
  	$res = "MATCH ($colname) AGAINST ('$value' IN BOOLEAN MODE)";
  }
  else if ($op == 'regexp') {
  	$res = "REGEXP $colname '$value'";
  }
  else if (substr($op, 0, 5) == 'hash_') {
  	$hashcol = substr($op, 5);

    if ($colname == '--word') {
    	$res = <<<END
{$hashcol} REGEXP '"[[:<:]]{$value}[[:>:]]"'
END;
    }
    else if ($colname == '--substring') {
    	// mysql> SELECT 'a="Uwe"|#|b="Hans"' REGEXP 'a=".*ans.*"'; = true
      // BAD: we want non-greedy substring search within (.*? not available)
      // we could use col LIKE '%"%value%"%' as well
      $res = <<<END
{$hashcol} REGEXP '".*{$value}.*"'
END;
    }
    else {
      if (substr($value, 0, 1) == '=') {
        $res = "$hashcol LIKE '%$colname=".'"'.$value.'"'."%'";
      }
      else if (substr($value, -1) == '%') {
        $res = "$hashcol LIKE '%$colname=".'"'.$value."'";
      }
      else {
      	$res = "$hashcol REGEXP '$colname=".'"[[:<:]]'.$value.'[[:>:]]"'."'";
      }
    }
	}

  return $res;
}


/**
 * Return html select box for numeric or string ($type = string) search.
 * 
 * Numeric search is: <, >, =, !=, <=, >=
 * 
 * String search is: contains, ,match, like, not_like, =, !=, empty,
 * not_empty, regexp
 * 
 * @tok_plugin op_html
 * 
 * @param string $name
 * @param string $type
 * @return string
 */
private static function _do_op_html($name, $type = '') {

  $res = '<select name="'.$name.'" size="1">';

  if ($type == 'string') {
    $op_list = array('contains' => 'CONTAINS', 'match' => 'MATCH',
      'like' => 'LIKE', 'not_like' => 'NOT LIKE', 
      '=' => '=', '!=' => '!=',
      'empty' => 'EMPTY', 'not_empty' => 'NOT EMPTY', '' => '',
      'regexp' => 'REGEXP');
  }
  else {
    $op_list = array('=' => '=', '&lt;' => '&lt;',
      '&gt;' => '&gt;', '&lt;=' => '&lt;=', '&gt;=' => '&gt;=', 
      '!=' => '!=', '' => '');
  }

  $sel_val = empty($_REQUEST[$name]) ? '' : $_REQUEST[$name];

  foreach ($op_list as $op => $val) {
    $op_val = $op;
    $op_val = str_replace('&lt;', '<', $op_val);
    $op_val = str_replace('&gt;', '>', $op_val);

    $selected = ($op_val == $sel_val) ? ' selected' : '';
    $res .= '<option value="'.$op.'"'.$selected.'>'.$val."</option>\n";
  }

  $res .= '</select>';
  
  return $res;
}


}




/**
 * TDatabaseOutput helper class.
 * 
 * @package phplib
 * @author Roland Kujundzic
 * @version 1.0
 * 
 */
class SQLHelper {

protected $p_db;
private $_fp;
private $_export_row_filter;


/**
 * 
 * @param string $dsn
 */
public function setDSN($dsn) {
  $this->p_db = new Database();
  $this->p_db->setDSN($dsn);
}


/**
 * 
 * @param object $db Database
 */
public function setDB(&$db) {
  $this->p_db =& $db;
}


/**
 * 
 * @param object $filter
 */
public function setExportRowFilter(&$filter) {
  $this->_export_row_filter =& $filter;
}



/**
 * Prepare hist sql queries.
 * Parameter hash must contain table, id and cols key/values.
 * Table must contain prev (unique: prev,id) and lchange columns.
 * 
 * @see SQLHelper#do_hist
 * @param hash
 */
public function init_hist($p) {
  
  if (empty($p['table']) || $p['table'] != Database::escape_name($p['table'])) {
    lib_abort('invalid table name table=['.$p['table'].']');
  }
  
  if (empty($p['id'])) {
    lib_abort('empty id colname');
  }
  
  if (empty($p['cols'])) {
    lib_abort('invalid table name table=['.$p['table'].']');
  }
  
  $cols = array();
  $p_cols = lib_explode(',', $p['cols'], true);
  foreach ($p_cols as $colname) {
    array_push($cols, Database::escape_name($colname));
  }
  
  $col_list = join(', ', $cols);
  $table = $p['table'];
  $id = $p['id'];
  
  $this->p_db->setQuery('hist_backup', "INSERT INTO $table (prev, lchange, $col_list) ".
    "SELECT $id, now(), $col_list FROM $table WHERE $id='{:=id}'");
  
  $update_query = "UPDATE $table SET lchange=now()";
  foreach ($cols as $col) {
    $update_query .= ", $col='{:=$col}'";
  }
  $update_query .= " WHERE $id='{:=id}'";
  $this->p_db->setQuery('hist_update', $update_query);

  $this->p_db->setQuery('hist_delete', "UPDATE $table SET prev=$id, lchange=now() WHERE $id='{:=id}'");
  
  $this->p_db->setQuery('hist_undelete', "UPDATE $table SET prev=0, lchange=now() WHERE prev=$id AND $id='{:=id}'");
  
  $this->p_db->setQuery('hist_select_id', "SELECT a.$id AS id, a.* FROM $table a WHERE a.$id='{:=id}'");
}


/**
 * Execute sql action on history table (call init_hist first).
 * 
 * Parameter has must contain column values and id value. 
 * edit= change and backup current entry 
 * undelete= restore deleted entry
 *
 * @see SQLHelper#do_hist
 * @param string
 * @param hash
 */
public function do_hist($do, $p) {

  if (isset($p['if']) && empty($p['if'])) {
    return '';
  }
  
  $res = !empty($p['_msg']) ? $p['_msg'] : '';
  
  if ($do == 'edit') {
    $this->p_db->execute($this->p_db->getQuery('hist_backup', $p));
    $this->p_db->execute($this->p_db->getQuery('hist_update', $p));
  }
  else if ($do == 'delete') {
    $this->p_db->execute($this->p_db->getQuery('hist_delete', $p));
  }
  else if ($do == 'undelete') {
    $this->p_db->execute($this->p_db->getQuery('hist_undelete', $p));
  }
  else if ($do == 'restore') {
    $dbres = $this->p_db->select($this->p_db->getQuery('hist_select_id', $p), 1);
    $hist = $dbres[0];
    
    $dbres = $this->p_db->select($this->p_db->getQuery('hist_select_id', array('id' => $hist['prev'])), 1);
    $curr = $dbres[0];
    
    if ($curr['id'] == $curr['prev']) {
      // entry was deleted ... undelete first ...
      $this->p_db->execute($this->p_db->getQuery('hist_undelete', $curr));
    }
    
    $this->p_db->execute($this->p_db->getQuery('hist_backup', $curr));
    
    $hist['id'] = $curr['id'];
    $this->p_db->execute($this->p_db->getQuery('hist_update', $hist));
  }

  return $res;
}


/**
 * 
 * @param hash
 */
public function delete_hist($p) {
  $res = false;

  $this->p_db->execute($this->p_db->getQuery('hist_backup', $p));
  $this->p_db->execute($this->p_db->getQuery('hist_update', $p));
}


/**
 * 
 * @param string $table
 * @return boolean
 */
public function has_table($table) {
  $res = false;

  $table_list = $this->p_db->getTableList();
  for ($i = 0; !$res && $i < count($table_list); $i++) {
    if ($table_list[$i] == $table) {
      $res = true;
    }
  }

  return $res;
}


/**
 * Return next id. 
 * Use with parameter = tablename or string hash:
 *
 * if: apply only if set
 * id: if set return this value
 * table: tablename
 *
 * @see Database#nextId
 *
 * With string hash there are two more modes:
 *
 * auto_incr_col, table, insert: apply insert and return value of auto_incr_col
 * @see Database#auto_incr_insert
 *
 * random, min, max, table: get unique random value for column random
 * @see Database#randomId
 *
 * @param string
 * @param string_hash
 * @return int
 */
public function next_id($param, $arg) {
  $res = '';

  if (!$param) {
    $p = lib_arg2hash($arg);

    if (isset($p['if']) && empty($p['if'])) {
      return '';
    }

    if (!empty($p['id']) && intval($p['id']) > 0) {
      $res = intval($p['id']);
    }
    else if (!empty($p['auto_incr_col'])) {
      if (empty($p['table']) || empty($p['insert'])) {
        lib_abort("missing table or insert parameter");
      }

      $res = $this->p_db->auto_incr_insert($p['insert'], $p['table'], $p['auto_incr_col']);
    }
    else if (!empty($p['random'])) {
      $min = empty($p['min']) ? 1000000 : intval($p['min']);
      $max = empty($p['max']) ? 9999999 : intval($p['max']); 

      $res = $this->p_db->randomId($p['table'], $p['random'], $min, $max);
    }
    else if (!empty($p['table'])) {
      $res = $this->p_db->nextId($p['table']);
    }
    else {
      lib_abort("invalid arguments");
    }
  }
  else {
    $res = $this->p_db->nextId($param);
  }

  return $res;
}


/**
 * 
 * @param hash $p
 * @return string
 */
public function desc_column_list($p) {

  if (empty($p['table'])) {
    lib_abort("missing parameter table");
  }

  if (empty($p['type'])) {
    $res = join(',', array_keys($this->p_db->getTableDesc($p['table'])));
    return $res;
  }

  $map = array(
    'string' => array('varchar', 'varbinary', 'text'),
    'number' => array('int', 'double', 'tinyint'),
    'date' => array('datetime')
  );

  if (!isset($map[$p['type']])) {
    lib_abort('use [sql_desc:column_list]type='.join('|', array_keys($map)).'|#|...');
  }

  $col_desc = $this->p_db->getTableDesc($p['table']);
  $type_list = $map[$p['type']];
  $use_col = array();

  foreach ($col_desc as $name => $info) {
    foreach ($type_list as $type) {
      if (substr($info['Type'], 0, strlen($type)) == $type) {
        array_push($use_col, $name);
      }
    }
  }

  $res = join(',', $use_col);
  return $res;
}


/**
 * 
 * @param hash $p
 * @return string
 */
public function desc_column($p) {

  if (empty($p['table'])) {
    lib_abort("missing parameter table");
  }

  if (empty($p['column'])) {
    lib_abort("missing parameter column");
  }

  $desc = $this->p_db->getTableDesc($p['table']);
  $res = $desc[$p['column']]['Type'];

  if (substr($res, 0, 5) == "set('" && substr($res, -2) == "')") {
    $res = str_replace("','", ',', substr($res, 5, -2));
  }

  return $res;
}


/**
 * Return 1=2^0=set_entry_1, 2^1=2=set_entry_2, 2^2=4=set_entry_3...
 * @param hash $p
 * @return string
 */
public function desc_set($p) {

  if (empty($p['table'])) {
    lib_abort("missing parameter table");
  }

  if (empty($p['column'])) {
    lib_abort("missing parameter column");
  }

  $desc = $this->p_db->getTableDesc($p['table']);
  $res = $desc[$p['column']]['Type'];

  if (substr($res, 0, 5) != "set('" || substr($res, -2) != "')") {
    lib_abort("Column ".$p['column']." is [$res] and not set");
  }

  $res = str_replace("','", ',', substr($res, 5, -2));
  $set_entries = explode(',', $res);
  $res = '1='.$set_entries[0];

  for ($i = 1; $i < count($set_entries); $i++) {
    $res .= ','.pow(2, $i).'='. $set_entries[$i];
  }

  return $res;
}


//-----------------------------------------------------------------------------
/**
 * 
 * @param hash $p
 * @param boolean $esc_tok
 * @return string
 */
public function desc_values($p, $esc_tok = false) {

  if (empty($p['table'])) {
    lib_abort("missing parameter table");
  }

  if (empty($p['column'])) {
    lib_abort("missing parameter column");
  }

  $limit = empty($p['limit']) ? 5 :intval($p['limit']);
  $maxlen = empty($p['maxlen']) ? 30 : intval($p['maxlen']);

  $query = 'SELECT DISTINCT('.Database::escape($p['column']).') AS val FROM '.
    Database::escape($p['table']).' LIMIT '.($limit + 3);
  $dbres = $this->p_db->select($query);

  $values = array();
  $other = true;
  $show = 1;

  if (count($dbres) <= $limit) {
    $show = $limit;
    $other = false;
  }

  for ($i = 0; $i < $show && $i < count($dbres); $i++) {
    $val = $esc_tok ? TokMarker::escape($dbres[$i]['val']) : $dbres[$i]['val'];

    if (strlen($val) > $maxlen) {
      $val = substr($val, 0, $maxlen).' ...';
    }

    array_push($values, lib_htmlescape($val));
  }

  $res = '['.join(']<br>[', $values).']';

  if ($other) {
    $res .= ' ...';
  }

  return $res;
}


/**
 * 
 * @param string $query
 * @param int $max
 * @return string
 */
public function backup($query, $max = 3) {

  if (!$query) {
    return '';
  }

  $dbres = $this->p_db->select($query, 1);

  if (isset($dbres[0]['backup'])) {
    $curr_backup = lib_arg2hash($dbres[0]['backup']);
    unset($dbres[0]['backup']);
  }
  else {
    $curr_backup = array();
  }

  $change = false;

  $ts = date('Y-m-d H:i:s', time());
  $new_backup = array('backup.1' => $ts);

  foreach ($dbres[0] as $key => $value) {
    $nkey = $key.'.1';
    $new_backup[$key.'.1'] = $dbres[0][$key];
    if (!isset($curr_backup[$nkey]) || $dbres[0][$key] != $curr_backup[$nkey]) {
      $change = true;
    }
  }

  if (!$change) {
    return '';
  }

  if ($max == 0) {
    $max = 3;
  }

  foreach ($curr_backup as $bkey => $bvalue) {
    list ($key, $n) = explode('.', $bkey);

    if ($n + 1 <= $max) {
      $nkey = $key.'.'.($n + 1);
      $new_backup[$nkey] = $bvalue;
    }
  }

  $res = ", backup='".Database::escape(lib_hash2arg($new_backup))."'";
  return $res;
}


/**
 * 
 * @param hash $p
 * @return int
 */
function export($p) {

  if (empty($p['file'])) {
    return 0;
  }

  $p = $this->_export_prepare($p);

  $this->_fp = File::open($p['file'], 'wb');

  if ($p['cols'] != '_auto_') {
    $col_names = lib_str2array($p['cols']);

    if (is_object($this->_export_row_filter)) {
      $col_names = $this->_export_row_filter->modifyExportRow($col_names, true);
    }

    if (empty($p['export_colnames']) || $p['export_colnames'] == 'yes') {
      File::write($this->_fp, $p['_q'].join($p['_qdq'], $col_names).$p['_q']."\r\n");
    }
  }
  else if (count($p['_hash_cols']) > 0) {
    lib_abort("cols=_auto_ is not possible with hash_cols");
  }

  $this->p_db->execute($p['query']);
  $n = 0;

  while (($row = $this->p_db->getNextRow())) {

    if (is_object($this->_export_row_filter)) {
      $row = $this->_export_row_filter->modifyExportRow($row);
    }

    if ($p['cols'] == '_auto_') {
      $col_names = array_keys($row);
      $p['cols'] = '';

      if (empty($p['export_colnames']) || $p['export_colnames'] == 'yes') {
        File::write($this->_fp, $p['_q'].join($p['_qdq'], $col_names).$p['_q']."\r\n");
      }
    }

    foreach ($p['_hash_cols'] as $hc) {
      $hash = lib_arg2hash($row[$hc]);

      foreach ($hash as $key => $value) {
        if (isset($row[$key])) {
          $key = $hc.'.'.$key;
        }

        $row[$key] = $value;
      }
    }

    $cols = array();

    foreach ($col_names as $key) {

      $value = (strlen($p['_add_slash']) > 0) ? addcslashes($row[$key], $p['_add_slash']) : $row[$key];

      if ($p['_crlf_to_space']) {
        $value = preg_replace("/[\r\n]+/", ' ', $value);
      }

      if ($p['csv'] != 'no' && strpos($value, $p['_q']) !== false) {
        $value = str_replace($p['_q'], $p['_q'].$p['_q'], $value);
      }

      if ($p['csv'] == 'part') {
        if (strpos($value, $p['_d']) !== false) {
          $value = $p['_q'].$value.$p['_q'];
        }
      }
      else {
        $value = $p['_q'].$value.$p['_q'];
      }

      array_push($cols, $value);
    }

    File::write($this->_fp, join($p['_d'], $cols)."\r\n");
    $n++;
  }

  File::close($this->_fp);
  File::chmod($p['file']);

  if ($n == 0 && !empty($p['remove_empty_file']) && $p['remove_empty_file'] == 'yes') {
    File::remove($p['file']);
  }

  return $n;
}


/**
 * 
 * @param hash $p
 * @return hash
 */
function _export_prepare($p) {

  $required = array('delimiter', 'cols', 'query');
  foreach ($required as $key) {
    if (empty($p[$key])) {
      lib_abort('missing [sql_export:]'.$key.'= ...');
    }
  }

  $delimiter_list = array('tab' => "\t", 'semicolon' => ';', 'comma' => ',', 
    'space' => ' ', 'colon' => ':');

  if (!isset($delimiter_list[$p['delimiter']])) {
    lib_abort("use delimiter=tab, comma or semicolon");
  }

  $p['_d'] = $delimiter_list[$p['delimiter']];
  $p['_q'] = empty($p['quote']) ? '' : $p['quote'];
  $p['_qdq'] = $p['_q'] . $p['_d'] . $p['_q'];

  if (empty($p['csv']) || $p['csv'] == 'no' || !$p['_q']) {
    $p['_add_slash'] = $p['_q'].$p['_d'];
    $p['csv'] = 'no';
  }
  else {
    $p['_add_slash'] = '';
  }

  if (empty($p['crlf']) || $p['crlf'] == 'escape') {
    $p['_add_slash'] .= "\r\n";
  }

  $p['_crlf_to_space'] = !empty($p['crlf']) && $p['crlf'] == 'space';

  $p['_hash_cols'] = empty($p['hash_cols']) ? array() : lib_str2array($p['hash_cols']);

  return $p;
}


}







/**
 * Render database output.
 * 
 * @package phplib
 * @author Roland Kujundzic
 * @version 1.0
 * 
 */
class TDatabaseOutput extends TOutput {

protected $p_db;
protected $p_sh;

private $_selected = array();
private $_last_query = '';
private $_kv = array();


/**
 * 
 * @param string $dsn
 */
public function setDSN($dsn) {
  $this->p_db = new Database();
  $this->p_db->setDSN($dsn);

  $this->p_sh = new SQLHelper();
  $this->p_sh->setDB($this->p_db);

  if (isset($this->p_conf['dsn_list']) && !isset($this->p_conf['dsn_list']['default'])) {
    $this->p_conf['dsn_list']['default'] = $dsn;
  }
}


/**
 * Save database connect string as name. 
 * Needed for [sql_dsn:xxx].
 *
 * @param string 
 * @param string 
 */
public function addDSN($name, $dsn) {

  if (!isset($this->p_conf['dsn_list'])) {
    $this->p_conf['dsn_list'] = array();
  }

  if ($name == 'default') {
    lib_abort("default is reserved");
  }

  if (is_object($this->p_db) && !isset($this->p_conf['dsn_list']['default'])) {
    $this->p_conf['dsn_list']['default'] = $this->p_db->getDSN();
  }

  $this->p_conf['dsn_list'][$name] = $dsn;
}


/**
 * (non-PHPdoc)
 * @see TOutput#addTo($tok, $name)
 */
public function addTo(&$tok, $name = 'dboutput') {

  parent::addTo($tok, $name);

  $tok->setPlugin('sort', $this);
  $tok->setPlugin('sql_dsn', $this);
  $tok->setPlugin('sql_debug', $this);
  $tok->setPlugin('sql_export', $this);
  $tok->setPlugin('sql_query', $this);
  $tok->setPlugin('sql_search', $this);
  $tok->setPlugin('sql_sort', $this);
  $tok->setPlugin('sql_execute', $this);
  $tok->setPlugin('sql_select', $this);
  $tok->setPlugin('sql_clear', $this);
  $tok->setPlugin('sql_col', $this);
  $tok->setPlugin('sql_col_fix', $this);
  $tok->setPlugin('sql_col_query', $this);
  $tok->setPlugin('sql_desc', $this);
  $tok->setPlugin('sql_row', $this);
  $tok->setPlugin('sql_distinct', $this);
  $tok->setPlugin('sql_loop', $this);
  $tok->setPlugin('sql_language', $this);
  $tok->setPlugin('sql_table', $this);
  $tok->setPlugin('sql_id2n', $this);
  $tok->setPlugin('sql_next_id', $this); 
  $tok->setPlugin('sql_backup', $this); 
  $tok->setPlugin('sql_hist', $this); 
  $tok->setPlugin('sql_update_cols', $this); 
  $tok->setPlugin('sql_has_table', $this);

  foreach (SQLPlugin::getPluginList() as $plugin) {
    $tok->setPlugin($plugin, $this);
  }
}


/**
 * (non-PHPdoc)
 * @see TOutput#tokCall($action, $param, $arg)
 * @see SQLPlugin#call($name, $param, $arg)
 * @see SQLHelper
 */
public function tokCall($action, $param, $arg) {
  $res = '';

  if (!is_object($this->p_db)) {
    lib_abort('call setDSN() first');
  }

  if ($action == 'sort') {
    $res = $this->_sort($param, lib_arg2hash($arg));
  }
  else if ($action == 'sql_query') {
    $this->p_db->setQuery($param, $arg);
  }
  else if ($action == 'sql_col') {
    $res = $this->_sql_col($param, $arg);
  }
  else if ($action == 'sql_dsn') {
    $this->_sql_dsn($param);
  }
  else if ($action == 'sql_col_fix') {
    $this->_sql_col_fix($param);
  }
  else if ($action == 'sql_col_query') {
    $res = $this->_sql_col_query($param, lib_arg2hash($arg));
  }
  else if ($action == 'sql_loop') {
    $res = $this->_sql_loop($param, $arg);
  }
  else if ($action == 'sql_distinct') {
    $res = $this->_sql_distinct($param, lib_arg2hash($arg));
  }
  else if ($action == 'sql_row') {
    $res = $this->_sql_row($param, lib_arg2hash($arg));
  }
  else if ($action == 'sql_language') {
    $this->_sql_language(lib_arg2hash($arg));
  }
  else if ($action == 'sql_table') {
    $res = $this->_sql_table(lib_arg2hash($arg));
  }
  else if ($action == 'sql_clear') {
    $this->_sql_clear();
  }
  else if ($action == 'sql_desc') {
    $res = $this->_sql_desc($param, lib_arg2hash($arg));
  }
  else if ($action == 'sql_search') {
    $res = $this->_sql_search($param);
  }
  else if ($action == 'sql_sort') {
    $res = $this->_sql_sort($param);
  }
  else if ($action == 'sql_execute') {
    $res = $this->_sql_execute($param, $arg);
  }
  else if ($action == 'sql_select') {
    $this->_sql_select($param, $arg);
  }
  else if ($action == 'sql_id2n') {
    $res = $this->_sql_id2n($param, $arg);
  }
  else if ($action == 'sql_next_id') {
    $res = $this->p_sh->next_id($param, $arg);
  }
  else if ($action == 'sql_backup') {
    $res = $this->p_sh->backup(trim($arg), intval($param));
  }
  else if ($action == 'sql_hist') {
    if ($param == 'init') {
      $this->p_sh->init_hist(lib_arg2hash($arg));
    }
    else if (substr($param, 0, 3) == 'do_') {
      $res = $this->p_sh->do_hist(substr($param, 3), lib_arg2hash($arg));
    }
  }
  else if ($action == 'sql_update_cols') {
    $res = $this->_sql_update_cols($param, $arg);
  }
  else if ($action == 'sql_has_table') {
    $res = $this->p_sh->has_table($param) ? 'yes' : '';
  }
  else if ($action == 'sql_debug') {
    $this->p_conf['_sql_debug:'.$param] = ($arg == 'on' || $arg == 'yes') ? 1 : 0;
  }
  else if (in_array($action, SQLPlugin::getPluginList())) {
    $res = SQLPlugin::call($action, $param, $arg);
  }
  else if ($action == 'sql_export') {
    $_REQUEST['export_count'] = $this->p_sh->export(lib_arg2hash($arg));
  }
  else {
    $res = parent::tokCall($action, $param, $arg);
  }

  return $res;
}


/**
 * (non-PHPdoc)
 * @see TOutput#fillTable()
 */
public function fillTable() {

  $this->_keep_search();

  if (empty($this->p_conf['query'])) {
    lib_abort('no query defined');
  }

  $this->p_db->execute($this->p_conf['query']);
  $this->p_rownum = $this->p_db->getRownum();

  $prefix_hash_cols = false;
  $add_hash_cols = false;

  if (!empty($this->p_conf['hash_cols'])) {
    $hash_cols = lib_str2array($this->p_conf['hash_cols']);
    $hash_replace = empty($this->p_conf['hash_replace']) ? 
      array() : lib_str2array($this->p_conf['hash_replace']);
    $add_hash_cols = true;

    if (!empty($this->p_conf['prefix_hash_cols']) && $this->p_conf['prefix_hash_cols'] == 'yes') {
      $prefix_hash_cols = true;
    }
  }

  $hc_delim = empty($this->p_conf['hash_cols_delimiter']) ? '_' : $this->p_conf['hash_cols_delimiter']; 
  $this->p_pagebreak();

  $pagebreak = intval($this->p_conf['pagebreak']);

  if (!empty($this->p_conf['last_id'])) {
  	$this->p_conf['last'] = $this->p_db->moveTo($this->p_conf['last_id'], 'id', $pagebreak);  	
  	$_REQUEST[$this->p_conf['req.last']] = $this->p_conf['last'];
  	$last = ($pagebreak > 0) ? intval($this->p_conf['last']) : 0;
  }
  else {
  	$last = ($pagebreak > 0) ? intval($this->p_conf['last']) : 0;
	  if ($last > 0) {
  	  $this->p_db->getRow($last - 1);
  	}
  }

  $nmax = ($pagebreak > 0 && $last + $pagebreak < $this->p_rownum) ?
    $pagebreak : $this->p_rownum - $last;

  $this->p_table = array();
  $n = 0;

  while ($n < $nmax && ($row = $this->p_db->getNextRow())) {

    if ($n == 0 && !empty($this->p_conf['ignore_first_null'])) {
      // if select query has round() there will be a result even if count = 0
      $col = $this->p_conf['ignore_first_null'];

      if (empty($row[$col])) {
        continue;
      }
    }

    if ($add_hash_cols) {

      foreach ($hash_replace as $tag) {
        $row[$tag] = '';
      }

      foreach ($hash_cols as $col) {
        $kv = lib_arg2hash($row[$col]);

        foreach ($kv as $key => $value) {
          if (isset($row[$key]) || $prefix_hash_cols) {
            // prefix hashcol if already exists
            $key = $col.$hc_delim.$key;
          }

          $row[$key] = $value;
        }
      }
    }

    array_push($this->p_table, $row);
    $n++;
  }

  if (!empty($this->p_conf['subquery'])) {
    $this->_fillTableSub();
  }
}


/**
 * 
 */
private function _keep_search() {

  if (empty($this->p_conf['search']) || strpos($this->p_conf['keep'], 'search') !== false) {
    return;
  }

  // auto append search parameter to keep ...
  $append = array('search');

  $conf_keys = array_keys($this->p_conf);
  foreach ($conf_keys as $key) {
    if (substr($key, 0, 9) == 'search.s_') {
      array_push($append, substr($key, 7));
    }
  }

  $this->p_conf['keep'] .= ', '.join(', ', $append);
}


/**
 * 
 */
private function _fillTableSub() {

  $this->p_db->setQuery('subquery', $this->p_conf['subquery']);

  $sub_id_list = array();
  $sub_tpl = array();

  for ($i = 0; $i < count($this->p_table); $i++) {
    if (isset($this->p_table[$i]['sub_id'])) {
      $sub_id = $this->p_table[$i]['sub_id'];
      array_push($sub_id_list, $sub_id);
      $sub_tpl[$sub_id] = '';
    }
  }

  if (empty($this->p_conf['sub_tpl'])) {
    lib_abort('define ['.$this->p_plugin_name.':conf]sub_tpl=...[:]');
  }

  if (count($sub_id_list) > 0) {
  	// avoid search for ('') ... it might return unwanted results ...
	  $in_sid = "'".join("','", $sub_id_list)."'";
  	$query = $this->p_db->getQuery('subquery', array('_sub_id_list' => $in_sid));
  	$dbout = $this->p_db->select($query);

  	$hash_cols = empty($this->p_conf['subquery_hash_cols']) ? 
    	array() : lib_str2array($this->p_conf['subquery_hash_cols']);
  }
  else {
		$dbout = array();
  }
  
  $erase_tags = (!empty($this->p_conf['erase_tags']) && $this->p_conf['erase_tags'] == 'yes') ?
    true : false;

  for ($i = 0; $i < count($dbout); $i++) {
    $row = $dbout[$i];
    $row['rownum'] = $i + 1;

    $tpl = $this->p_conf['sub_tpl'];
    foreach ($row as $key => $value) {

      if (in_array($key, $hash_cols)) {
        $kv = lib_arg2hash($value);
        foreach ($kv as $kv_key => $kv_value) {
          $tpl = str_replace('{:=sub_'.$key.'_'.$kv_key.'}', $kv_value, $tpl);
        }
      }

      $tpl = str_replace('{:=sub_'.$key.'}', $value, $tpl);
    }

    $sid = $row['sub_id'];

    if (!isset($sub_tpl[$sid])) {
      lib_abort("invalid sub_id [$sid]", print_r($row, true)."\nquery was:\n$query");
    }

    if ($erase_tags) {
      $tpl = TokMarker::removeTags($tpl);
    }

    $sub_tpl[$sid] .= $tpl;
  }

  for ($i = 0; $i < count($this->p_table); $i++) {
    $sid = $this->p_table[$i]['sub_id'];

    if (!isset($sub_tpl[$sid])) {
      lib_warn("row [$sid] has no sub-rows");
      $this->p_table[$i]['sub_tpl'] = '';
    }
    else {
      $this->p_table[$i]['sub_tpl'] = $sub_tpl[$sid]; 
    }
  }
}


/**
 * 
 * @param hash $p
 */
private function _sql_update_cols_init($p) {

  $this->_kv = array();

  if (isset($p['language'])) {
    $language = lib_str2array($p['language']);
  }
  else {
    $language = array();
  }

  if (empty($p['colnames'])) {
    lib_abort("[sql_update_cols:init]colnames=....");
  }

  $cols = lib_explode(',', $p['colnames'], true);

  if (count($cols) < 1) {
    lib_abort("[sql_update_cols:init]colnames=....");
  }

  foreach ($cols as $col) {

    if (count($language) > 0) {
      foreach ($language as $lang) {
        $lc = $col.'_'.$lang;

        if (!isset($_REQUEST[$lc])) {
          lib_abort("missing form parameter [$lc]");
        }

        $key = Database::escape_name($lc);
        $this->_kv[$key] = Database::escape($_REQUEST[$lc]);
      }
    }
    else {

      if (!isset($_REQUEST[$col])) {
        lib_abort("missing form parameter [$col]");
      }

      $key = Database::escape_name($col);
      $this->_kv[$key] = Database::escape($_REQUEST[$col]);
    }
  }
}


/**
 * 
 * @param string $param
 * @param string_hash $arg
 * @return string
 */
private function _sql_update_cols($param, $arg) {

  if ($param == 'init') {
    $this->_sql_update_cols_init(lib_arg2hash($arg));
    return '';
  }

  if (empty($param)) {
    $param = 'set';
    $this->_sql_update_cols_init(lib_arg2hash($arg));
  }

  if (count($this->_kv) < 1) {
    lib_abort("call [sql_update_cols:init]colnames=... first");
  }

  $res = '';

  if ($param == 'keys') {
    $res = join(', ', array_keys($this->_kv)); 
  }
  else if ($param == 'values') {
    $res = "'".join("', '", array_values($this->_kv))."'"; 
  }
  else if ($param == 'set') {
    $set_kv = array();

    foreach ($this->_kv as $key => $value) {
      array_push($set_kv, $key."='".$value."'");
    }

    $res = join(",\n", $set_kv);
  }
  else {
    lib_abort("[sql_update_cols:init|keys|values|set]");
  }

  return $res;
}


/**
 * 
 * @param string $param
 * @param string $arg
 */
private function _sql_select($param, $arg) {

  // USE * TO keep resultset !!!

  if (!$param) {
    $query = trim($arg);

    if ($query && $query != '*') {
      $param = 'tmp';
      $this->p_db->setQuery($param, $query);
    }
    else if ($query != '*') {
      // remove old result set !!!
      $this->_selected = array();
      $this->_last_query = '';
    }
  }

  if ($param) {
    $this->_last_query = $this->p_db->getQuery($param);
    $this->_selected = $this->p_db->select($this->_last_query);
  }
}


/**
 * Execute sql query. 
 * Use parameter for named sql query.
 * Single line queries starting with [--] are ignored.
 * Use configuration _sql_debug:sql_execute to return executed query
 * (with ; appended).
 *
 * @param string
 * @param string
 * @return string
 */
private function _sql_execute($param, $arg) {

  if ($param) {
    $query = $this->p_db->getQuery($param);
  }
  else {
    $query = trim($arg);

    // ignore single line query with starting with: "-- ..."
    if (substr($query, 0, 2) == '--' && strpos($query, "\n") === false) {
      $query = '';
    }
  }

  if ($query) {
    if (!empty($this->p_conf['_sql_debug:sql_execute'])) {
      return $query.";\n";
    }
    else {
      $this->p_db->execute($query);
    }
  }

  return '';
}


/**
 * 
 */
private function _sql_clear() {
  $this->_selected = array();
}


/**
 * Export colname_lang into colname in selected rows
 * 
 * @param hash $p
 */
private function _sql_language($p) {

  if (count($this->_selected) == 0 || empty($p['language'])) {
    return;
  }

  $ls = '_'.$p['language'];

  if (empty($p['cols'])) {
    $cols = array();

    foreach ($this->_selected[0] as $key => $value) {
      if (substr($key, -3) == $ls) {
        $base = substr($key, 0, -3);
        if (!isset($this->_selected[0][$base])) {
          array_push($cols, $base);
        }
      }
    }
  }
  else {
    $cols = lib_str2array($p['cols']);
  }

  $fallback = empty($p['fallback']) ? array() : lib_str2array($p['fallback']);

  for ($i = 0; $i < count($this->_selected); $i++) {
    foreach ($cols as $col) {
      $lcol = $col.$ls;
      if (isset($this->_selected[$i][$lcol])) {
        $this->_selected[$i][$col] = $this->_selected[$i][$lcol];

        for ($j = 0; empty($this->_selected[$i][$col]) && $j < count($fallback); $j++) {
          $fcol = $col.'_'.$fallback[$j];
          if (!empty($this->_selected[$i][$fcol])) {
            $this->_selected[$i][$col] = $this->_selected[$i][$fcol];
          }
        }
      }
    }
  }
}


/**
 * Switch to new database connect string.
 * Use default or addDSN name.
 *
 * @param string
 */
private function _sql_dsn($param) {

  if (!isset($this->p_conf['dsn_list']) || !isset($this->p_conf['dsn_list'][$param])) {
    lib_abort("use addDSN('$param', '...') first");
  }

  $this->setDSN($this->p_conf['dsn_list'][$param]);
}


/**
 * 
 * @param string $param
 * @param hash $p
 * @return string
 */
private function _sql_col_query($param, $p) {
  $res = '';

  if (count($this->_selected) < 1) {
    lib_abort("Nothing selected");
  }

  if ($param == 'insert') {
    $col_list = array();
    $value_list = array();

    foreach ($this->_selected[0] as $key => $value) {
      array_push($col_list, Database::escape_name($key));

      if (isset($p[$key])) {
        $value = $p[$key];
      }

      array_push($value_list, "'".Database::escape($value)."'"); 
    }

    $res = '('.join(', ', $col_list).') VALUES ('.join(', ', $value_list).')';
  }
  else if ($param == 'update') {
    $set_list = array();

    foreach ($this->_selected[0] as $key => $value) {

      if (isset($p[$key])) {
        $value = $p[$key];
      }

      array_push($set_list, Database::escape_name($key)."='".Database::escape($value)."'"); 
    }

    $res = join(",\n", $set_list);
  }
  else {
    lib_abort("invalid parameter [$param]");
  }

  return $res;
}


/**
 * 
 * @param string $param
 */
private function _sql_col_fix($param) {

  if ($param == 'tolower') {
    if (isset($this->_selected[0])) {
      $selected = $this->_selected[0];

      foreach ($selected as $key => $value) {
        $lkey = strtolower($key);
        if ($lkey != $key && !isset($this->_selected[0][$lkey])) {
          $this->_selected[0][$lkey] = $value;
          unset($this->_selected[0][$key]);
        }
      }
    }
  }
  else {
    lib_abort("unknown parameter [$param]");
  }
}


/**
 * Return value of last selection.
 * Result depends on param (or arg if param is empty):
 *
 * ? = number of last selected rows
 * * = return hash string (of first row)
 * n* = return hash string of n'th row
 * unset:colname = return column value and unset column
 * remove:colname = remove column
 * prefix:id = return table string of selection - prefix row with row[id]_ 
 *   if id is empty use rownum (1_, 2_, ...)
 * list:column = return comma separated list of column values (selection[0][column], ...)
 * list:column:n = return n'th value of column (n=7: selection[6][column])
 * split:column = split hashcol selection[0][column] and set select[0][key] = value, ...
 *   existing keys will be overwritten
 * split:column:prefix = split hashcol selection[0][column] and set selection[0][prefixkey] = value, ...
 * column= return selection[0][column]
 * column.key= split hashstring selection[0][column] and return selection[0][column][key]
 *   if selection[0][key] exists use this value instead
 * set_md5= export md5 value of normalized sql values
 *
 * @param string
 * @param string
 * @return string
 */
private function _sql_col($param, $arg) {

  $col = empty($param) ? trim($arg) : $param;

  if ($param == '?') {
    // {sql_select:}{if:}{fv:login}|#|SELECT 1 WHERE login={esc:}{fv:login}{:esc}{:if}{:sql_select}
    // {fv_set_error:login}{if_eq:0}{sql_col:?}|#|unbekannt{:if_eq}{:fv_set_error}
    $res = empty($this->_last_query) ? '' : count($this->_selected);
    return $res;
  }

  $res = '';

  if (count($this->_selected) == 0) {
    return $res;
  }

  if ($col == '*') {
    $res = lib_hash2arg($this->_selected[0]);
  }
  else if (substr($col, -1) == '*') {
    // e.g. 1* = first row, 2* = second row, ...
    $rownum = intval(substr($col, 0, -1)) - 1;

    if ($rownum >= 0 && $rownum < count($this->_selected)) {
      $res = lib_hash2arg($this->_selected[$rownum]);
    }
  }
	else if ($col == 'set_md5') {
		$col_names = lib_explode(',', $arg);
		foreach ($col_names as $key) {
			$_REQUEST['md5_'.$key] = md5(preg_replace("/\r?\n/", "\n", $this->_selected[0][$key]));
		}
	}
  else if (substr($col, 0, 6) == 'unset:') {
    $col = substr($col, 6);

    if (isset($this->_selected[0][$col])) {
      $res = $this->_selected[0][$col];
      unset($this->_selected[0][$col]);
    }
  }
  else if (substr($col, 0, 7) == 'remove:') {
    $col = substr($col, 7);

    if (isset($this->_selected[0][$col])) {
      unset($this->_selected[0][$col]);
    }
  }
  else if (substr($col, 0, 7) == 'prefix:') {
    // convert table into hash (e.g. prefix:id = use value of id col to prefix row)
    $col = substr($col, 7);
    $prefixed = array();

    for ($i = 0; $i < count($this->_selected); $i++) {
      $row = $this->_selected[$i];
      $prefix = empty($col) ? ($i + 1) : $row[$col];

      foreach ($row as $key => $value) {
        if ($key != $col) {
          $prefixed[$prefix.'_'.$key] = $value;
        }
      }
    }

    $res = lib_hash2arg($prefixed);
  }
  else if (substr($col, 0, 5) == 'list:') {
    $tmp = explode(':', $col);
    $col = $tmp[1];
    $num = (count($tmp) == 3) ? intval($tmp[2]) - 1 : -1;
    $row_col = array();

    for ($i = 0; $i < count($this->_selected); $i++) {
      if (isset($this->_selected[$i][$col])) {
        array_push($row_col, $this->_selected[$i][$col]);
      }
    }

    if ($num > -1) {
      $res = ($num < count($row_col)) ? $row_col[$num] : '';
    }
    else {
      $res = join(', ', $row_col);
    }
  }
  else if (substr($col, 0, 6) == 'split:') {
    // e.g. conf = [a=1|#|b=2|#|...]
    // split:conf = create a=1, b=2, ...
    // split:conf:conf_ = create conf_a=1, conf_b=2, ...
    $tmp = explode(':', substr($col, 6));
    $hashcol = $tmp[0];
    $prefix = isset($tmp[1]) ? $tmp[1] : '';
    if (!empty($this->_selected[0][$hashcol])) {
      $hash = lib_arg2hash($this->_selected[0][$hashcol]);
      foreach ($hash as $key => $value) {
        $this->_selected[0][$prefix.$key] = $value;
      }
    }
  }
  else if (($pos = strpos($col, '.')) !== false) {
    $hashcol = substr($col, 0, $pos);

    if (isset($this->_selected[0][$col])) {
      // x.y was already splitted - return value
      $res = $this->_selected[0][$col];
    }
    else if (!empty($this->_selected[0][$hashcol])) {
      // make conf=[a=1|#|b=2|#|...] available as conf.a=1, conf.b=2, ...
      $hash = lib_arg2hash($this->_selected[0][$hashcol]);
      foreach ($hash as $key => $value) {
        $this->_selected[0][$hashcol.'.'.$key] = $value;
      }

      if (isset($this->_selected[0][$col])) {
        $res = $this->_selected[0][$col];
      }
    }
  }
  else if (isset($this->_selected[0][$col])) {
    $res = $this->_selected[0][$col];
  }

  return $res; 
}


/**
 * Implementation of plugin {sql_desc:values|column_list}
 * 
 * @param string $param values|column_list
 * @param hash $p
 * @see SQLHelper#desc_column_list($p)
 * @see SQLHelper#desc_values($p, true)
 * @see SQLHelper#desc_column($p)
 * @return string
 */
private function _sql_desc($param, $p) {
  $res = '';

  if ($param == 'values') {
    $res = $this->p_sh->desc_values($p, true);
  }
  else if ($param == 'column_list') {
    $res = $this->p_sh->desc_column_list($p);
  }
  else if ($param == 'column') {
    $res = $this->p_sh->desc_column($p);
  }
  else if ($param == 'set') {
    $res = $this->p_sh->desc_set($p);
  }
  else {
    lib_abort("[sql_desc:values|column|column_list]");
  }

  return $res;
}


/**
 * Return distinct column values from current selection.
 * 
 * Use conf key sort_distinct=yes or sort_distinct.$param=yes to sort.
 *  
 * @param string $param
 * @param hash $p
 * @return string
 */
private function _sql_distinct($param, $p) {
  $res = '';

  if (count($p) == 0) {
    $p['col'] = $param;
    $param = 'list';
  }

  if (empty($p['col'])) {
    lib_abort("colname missing");
  }

  $distinct = array();
  $col = $p['col'];

  if (count($this->_selected) == 0) {
    return '';
  }

  if (!isset($this->_selected[0][$col])) {
    lib_abort("no such column $col");
  }
  
  for ($i = 0; $i < count($this->_selected); $i++) {
    $value = $this->_selected[$i][$col];

    if ($param == 'list') {
      $value = str_replace(',', '\,', $value);
    }

    if (strlen($value) > 0) {
      $distinct[$value] = 1;
    }
  }
  	
  if ($param == 'list') {
		$list = array_keys($distinct);

		$sckey = 'sort_distinct.'.$col;
  	if ((!empty($this->p_conf['sort_distinct']) && $this->p_conf['sort_distinct'] == 'yes') ||
  			(!empty($this->p_conf[$sckey]) && $this->p_conf[$sckey] == 'yes')) {
  		sort($list);
  	}
  	
	  $res = join(', ', $list);
  }

  return $res;
}


/**
 * Return selected rows as html. If param == count return only rownum. 
 * If param == 'dl_XXX' return <datalist id="dl_XXX"><option>1</option> ....</datalist>.
 * Default parameters are column=row, tpl=$column and delimiter=|#|.
 *
 * Use column=* and {:=colname} tags in tpl to output all values.
 * Use label2value=yes to use opt_value (and opt_label) cols.
 * Use fix_label_value=yes to replace /[^0-9A-Za-z_]/' with '_'.
 * If param == select_list use default hash setting:
 *
 *   header: ''
 *   footer: ''
 *   tpl: $opt_value=$opt_label
 *   label2value: yes
 *   column: opt_value
 *   delimiter: ", "
 *
 * and change param to "option". If param == option use default hash setting:
 *
 *   tpl: <option value="$opt_value"$selected>$opt_label</option>
 *   delimiter: \n
 *
 * @param string
 * @param hash
 * @return string
 */
private function _sql_row($param, $p) {

  if ((isset($p['if']) && empty($p['if'])) || count($this->_selected) == 0) {
    return '';
  }

  $is_option_box = false;
	$escape_char = ',';
	$escape_with = '\,';

	if (!isset($p['header'])) {
		$p['header'] = '';
	}

	if (!isset($p['footer'])) {
		$p['footer'] = '';
	}

  if ($param == 'count') {
    return count($this->_selected);
  }
	else if (substr($param, 0, 3) == 'dl_') {
		$p['header']  = '<datalist id="'.$param.'">';
		$p['footer'] = '</datalist>';
   	$is_option_box = true;
	}
  else if ($param == 'select_list') {
    $p['tpl'] = '$opt_value=$opt_label';
    $p['label2value'] = 'yes';
    $p['column'] = 'opt_value';
    $p['delimiter'] = ', ';
    $is_option_box = true;
  }
	else if ($param == 'json_all') {
		if (!empty($p['hashcols'])) {
			$hash_cols = lib_explode(',', $p['hashcols']);
			$rows = array();

			foreach ($this->_selected as $row) {
				foreach ($row as $key => $value) {
					if (in_array($key, $hash_cols)) {
						$row[$key] = lib_arg2hash($row[$key]);
					}
				}

				array_push($rows, $row);
			}

			return json_encode($rows);
		}
		else {
			return json_encode($this->_selected);
		}
	}
	else if ($param == 'json') {
		$p['header'] = '{';
		$p['tpl'] = '"$opt_value": "$opt_label"';
		$p['footer'] = '}';
		$p['delimiter'] = ', ';
		$is_option_box = true;
		$p['label2value'] = 'yes';
		$escape_char = '"';
		$escape_with = '\"';
	}
	else if ($param == 'hash') {
		$p['tpl'] = '$opt_value=$opt_label';
		$p['delimiter'] = '|#|';
		$is_option_box = true;
	}
  else if ($param == 'option') {
    $is_option_box = true;
  }

  $col = empty($p['column']) ? 'row' : $p['column'];

  $label2value = !empty($p['label2value']) && $p['label2value'] == 'yes';

  if ($is_option_box) {
    if (empty($p['tpl'])) {
      $p['tpl'] = '<option value="$opt_value"$selected>$opt_label</option>';
    }

    if (empty($p['delimiter'])) {
      $p['delimiter'] = "\n";
    }

    if (isset($this->_selected[0]['opt_value'])) {
      $col = 'opt_value';
    }
  }

  if ($col != '*' && !isset($this->_selected[0][$col])) {
    lib_abort("no such column [$col]", print_r($this->_selected[0], true));
  }

  $rows = array();

  for ($i = 0; $i < count($this->_selected); $i++) {
    $row = $this->_selected[$i];

    if ($is_option_box) {

      if ($label2value) {
        $row['opt_value'] = str_replace($escape_char, $escape_with, $row['opt_value']);

        if (isset($row['opt_label'])) {
          $row['opt_label'] = str_replace($escape_char, $escape_with, $row['opt_label']);
        }

        if (!empty($p['fix_label_value']) && $p['fix_label_value'] == 'yes') {
          $row['opt_value'] = preg_replace('/[^0-9A-Za-z_]/', '_', $row['opt_value']);
        }
      }

			if (!isset($row['opt_label'])) {
      	$row['opt_label'] = $row['opt_value'];
      }

      $selected = (isset($p['selected']) && $row['opt_value'] == $p['selected']) ?
        ' selected' : '';

      $row_txt = str_replace('$opt_value', lib_htmlescape($row['opt_value']), $p['tpl']);
      $row_txt = str_replace('$opt_label', lib_htmlescape($row['opt_label']), $row_txt);
      $row_txt = str_replace('$selected', $selected, $row_txt);
    }
    else if ($col == '*') {
      $row_txt = TokMarker::replace($p['tpl'], $row);
    }
    else {

      if ($label2value) {
        $row[$col] = str_replace($escape_char, $escape_with, $row[$col]);
      }

      $row_txt = empty($p['tpl']) ? $row[$col] : str_replace('$column', $row[$col], $p['tpl']);
    }

    array_push($rows, $row_txt);
  }

  $delimiter = isset($p['delimiter']) ? $p['delimiter'] : '|#|';
  $res = $p['header'].join($delimiter, $rows).$p['footer'];

  return $res; 
}


/**
 * 
 * @param string $prefix
 * @param string $tpl
 * @return string
 */
private function _sql_loop($prefix, $tpl) {

  $rows = array();

  foreach ($this->_selected as $row) {
    $row_html = $tpl;

    foreach ($row as $key => $value) {
      $row_html = str_replace('{:='.$prefix.$key.'}', $value, $row_html);
    }

    array_push($rows, $row_html);
  }

  $res = join("\n", $rows);
  return $res;
}


/**
 * 
 * @param hash $p
 * @return string
 */
private function _sql_table($p) {

  if ((isset($p['if']) && empty($p['if'])) || count($this->_selected) == 0) {
    return '';
  }

  $res = lib_table2arg($this->_selected);
  return $res; 
}


/**
 * Return "ORDER BY ...". Use conf.sort_default or _REQUEST[conf.sort.request].
 * Sort value is [a|d|@]colname. If prefix is @ use sort.colname as result.
 * 
 * @param string $prefix
 * @return string
 */
private function _sql_sort($prefix) {
  $res = '';

  $req_param = empty($this->p_conf['sort.request']) ? 'sort' : $this->p_conf['sort.request'];

  if (!empty($prefix)) {
   $prefix .= '.';
  }

  $sort = '';

  if (!empty($_REQUEST[$req_param])) {
    $sort = $_REQUEST[$req_param];
  }
  else if (!empty($this->p_conf['sort.default'])) {
    $sort = $this->p_conf['sort.default'];
  }

  if (!empty($sort)) {
    $order = substr($sort, 0, 1);
    $col = $prefix.substr($sort, 1);

    if ($order == 'd') {
      $res = 'ORDER BY '.$col.' DESC';
    }
    else if ($order == 'a') {
      $res = 'ORDER BY '.$col;
    }
		else if ($order == '@') {
			$res = $this->p_conf['sort.'.$col];
		}
	}

  return $res;
}


/**
 * 
 * @param string $param
 * @return string
 */
private function _sql_search($param) {
  $res = '';

  if (!isset($this->p_conf['search'])) {
    lib_abort('use ['.$this->p_plugin_name.':conf]search=...[:dboutput] first');
  }

  if (empty($this->p_conf['search'])) {
    return $res;
  }

  $where = array();

  foreach ($this->p_conf as $key => $value) {
    if (substr($key, 0, 7) == 'search.') {
      $rkey = substr($key, 7);
			$rv = $this->getValue($rkey);
			
			if (is_array($rv) && (count($rv) > 0 || !empty($rv[0]))) {
				array_push($where, $value);
			}
			else if (strlen($rv) > 0 && strlen($value) > 0) {

        if (substr($value, 0, 1) == '@' && substr($rkey, 0, 2) == 's_') {
          $colname = substr($rkey, 2);

          if ($rv == '!') {
            $value = "($colname='' OR $colname IS NULL)";
          }
          else if ($value == '@EQUAL') {
            $value = $colname."='".Database::escape($rv)."'";
          }
          else if ($value == '@LIKE') {
            $value = $colname." LIKE '%".Database::escape($rv)."%'";
          }
          else if ($value == '@RLIKE') {
            $value = $colname." LIKE '".Database::escape($rv)."%'";
          }
        }

 				array_push($where, $value);
      }
    }
  }

  if (count($where) > 0) {
    $res = ($param == 'and') ? ' AND ' : ' WHERE ';
    $res .= '('. join(') AND (', $where) .')';
  }

  return $res;
}


/**
 * 
 * @param string $col
 * @param hash $p
 * @return string
 */
private function _sort($col, $p) {
  $res = '';

  if (empty($col) && !empty($p['col'])) {
    $col = $p['col'];
  }

  if (!empty($p['click'])) {
    $col = $p['click'];
  }

  $req_param = empty($this->p_conf['sort.request']) ? 'sort' : $this->p_conf['sort.request'];
  $sort = '';

  if (isset($_REQUEST[$req_param])) {
    $sort = $_REQUEST[$req_param];
  }
  else if (!empty($this->p_conf['sort.default'])) {
    $sort = $this->p_conf['sort.default'];
  }

  if ($sort == 'd'.$col) {
    $res = !empty($this->p_conf['sort.desc']) ? $this->p_conf['sort.desc'] :
      '<a href="{:=link}">{:=txt}<img src="img/sort/desc.gif" border="0" alt=""></a>';
    $new_sort = $req_param.'=';
  }
  else if ($sort == 'a'.$col) {
    $res = !empty($this->p_conf['sort.asc']) ? $this->p_conf['sort.asc'] :
      '<a href="{:=link}">{:=txt}<img src="img/sort/asc.gif" border="0" alt=""></a>';
    $new_sort = $req_param.'=d'.$col;
  }
  else {
    $res = !empty($this->p_conf['sort.no']) ? $this->p_conf['sort.no'] :
      '<a href="{:=link}">{:=txt}<img src="img/sort/no.gif" border="0" alt=""></a>';
    $new_sort = $req_param.'=a'.$col;
  }

  isset($this->p_conf['keep_sort']) && $new_sort .= $this->p_conf['keep_sort']; 
  
  $txt = empty($p['txt']) ? '' : $p['txt'];
  $res = str_replace('{:=txt}', $txt, $res);

  $link = empty($this->p_conf['sort.link']) ? 'index.php?{:=keep}' : $this->p_conf['sort.link'];
  $link = str_replace('{:=keep}', $new_sort, $link);
  $res = str_replace('{:=link}', $link, $res);

  if (!empty($p['click'])) {
    $res = ' style="cursor:pointer" onClick="window.location.href='."'".$link."'".'"';
    if (!empty($p['title'])) {
      $res .= ' title="'.$p['title'].'"';
    }
    else if (!empty($this->p_conf['sort.click_title'])) {
      $res .= ' title="'.$this->p_conf['sort.click_title'].'"';
    }
  }

  return $res;
}


/**
 * 
 * @param string $qkey
 * @param string $arg
 * @return string
 */
private function _sql_id2n($qkey, $arg = '') {
  $res = '';

  $db_res = $this->p_db->select($this->p_db->getQuery($qkey));

  if (count($db_res) == 0) {
    lib_abort('id2n query "'.$qkey.'" has no result');
  }

  if (empty($arg)) {

    if (!isset($db_res[0]['num'])) {
      lib_abort('query result "'.$qkey.'" has no num column');
    }

    $num = intval($db_res[0]['num']);
    if ($num + 1 > 63) {
      lib_abort('table "'.$table.'" has too many entries ('.$num.' > 63) for id2n');
    }

    $res = 'POW(2,'.($num + 1).')';
  }
  else {
    if (!isset($db_res[0]['option']) || !isset($db_res[0]['id'])) {
      lib_abort('query result "'.$qkey.'" must have id and option column');
    }

    $option_list = lib_arg2array($arg);
    $res = 0;

    for ($i = 0; $i < count($db_res); $i++) {
      $option = $db_res[$i]['option'];
      $id = $db_res[$i]['id'];

      if (in_array($option, $option_list)) {
        $res += pow(2, $id);
      }
    }
  }

  return $res;
}


}

?>
