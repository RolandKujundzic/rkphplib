<?php

namespace rkphplib;

require_once __DIR__.'/ADatabase.class.php';
require_once __DIR__.'/lib/split_str.php';

use function rkphplib\lib\split_str;


/**
 * Compute SQL where/and query part according to $_REQUEST.
 *
 * @author Roland Kujundzic <roland@inkoeln.com>
 */
class SQLSearch {

// @var hash $search
private $search = [];


/**
 * @hash $conf
 * sort:
 * req.sort: sort 
 * search: (use search.LIST if LIST=$_REQUEST[req.search])
 * search.value: (use $_REQUEST[s_COL])
 * req.search:
 * search.LIST:
 * @eol
 */
public function __construct(array $conf = []) {
	$this->setConf($conf);
}


/**
 *
 */
public function setConf(array $conf, bool $reset = true) : void {
	if ($reset) {
		$this->conf = array_merge([
			'sort' => '',
			'req.sort' => 'sort',
			'search' => '',
			'req.search' => ''
		], $conf);
	}
	else {
		$this->conf = array_merge($this->conf, $conf);
	}
}


/**
 * @example …
 * $sql = new SQL([ 'sort' => '', 'req.sort' => 'sort' ]);
 * $sql->sort('aid'); // ORDER BY id
 * $sql->sort('dsince'); // ORDER BY since DESC
 * $_REQUEST['sort'] = 'aname'; $sql->sort(); // ORDER BY name
 */
public function sort(string $sort = null) : string {
	if (!is_null($sort)) {
		// use $sort
	}
	else if (isset($_REQUEST[$this->conf['req.sort']])) {
		$sort = $_REQUEST[$this->conf['req.sort']];
	}
	else {
		$sort = $this->conf['sort'];
	}

	if (empty($sort)) {
		return '';
	}

	$direction = mb_substr($sort, 0, 1);
	$column = ADatabase::escape_name(mb_substr($sort, 1), true);
	$res = 'ORDER BY '.$column;
		
	if ($direction == 'a') {
		// ASC = ASCENDING = DEFAULT
	}
	else if ($direction == 'd') {
		$res .= ' DESC';
	}
	else {
		throw new Exception("invalid sort value [$sort]");
	}

	return $res;
}


/**
 * Replace _WHERE_SEARCH (or _AND_SEARCH) and _SORT in $query.
 * Return $query if not found or search column list is empty.
 * 
 * @example …
 * $sql = new SQLSearch([ 'search' => 'name,descr' ]);
 * $sql->query('SELECT * FROM test _WHERE_SEARCH');
 * $_REQUEST['search'] = 'new'; // use conf[search.new] instead of conf.search
 * $sql->query('SELECT * FROM test WHERE status=1 _AND_QUERY');
 * @eol
 */
public function query(string $query = '_WHERE_SEARCH') : string {
	if ((empty($query) || $query == '_WHERE_SEARCH') && !empty($this->conf['query'])) {
		$query = $this->conf['query'];
	}

	self::scanRequest();

	if (strpos($query, '_SORT') > 0) {
		$query = str_replace('_SORT', $this->sort(), $query);
	}

	if (strpos($query, '_WHERE_SEARCH') !== false) {
		$tag = '_WHERE_SEARCH';
		$type = 'where';
	}
	else if (strpos($query, '_AND_SEARCH') !== false) {
		$tag = '_AND_SEARCH';
		$type = 'and';
	}
	else {
		// \rkphplib\lib\log_debug("SQLSearch.query:126> $query");
		return $query;
	}

	$opt = [ 'where' => $type, 'cols' => [] ];
	$rkey = $this->conf['req.search'];

	if ($rkey && isset($_REQUEST[$rkey]) && !empty($this->conf['search.'.$rkey])) {
		$opt['cols'] = split_str(',', $this->conf['search.'.$rkey], true);
	}
	else if (!empty($this->conf['search'])) {
		$opt['cols'] = split_str(',', $this->conf['search'], true);
	}
	else if (($cols = self::searchCols())) {
		$opt['cols'] = $cols;
	}

	$query = str_replace($tag, $this->where($opt), $query);
	// \rkphplib\lib\log_debug("SQLSearch.query:144> $query");
	return $query;
}


/**
 * Use $_REQUEST[scol]=COLUMN ($_REQUEST[sop]) and $_REQUEST[sval]=VALUE to set
 * $_REQUEST[s_COLUMN]=VALUE (and $_REQUEST[s_COLUMN_op]=$_REQUEST[sop]).
 */
private static function scanRequest() {
	if (!empty($_REQUEST['scol']) && isset($_REQUEST['sval'])) {
		$scol = 's_'.$_REQUEST['scol'];
		$_REQUEST[$scol] = $_REQUEST['sval'];
	
		if (!empty($_REQUEST['sop'])) {
			$_REQUEST['s_'.$_REQUEST['scol'].'_op'] = $_REQUEST['sop'];
		}
	}
}


/**
 * Return names where $_REQUEST[s_NAME] not empty.
 */
public static function searchCols() : ?array {
	self::scanRequest();

	$rkeys = array_keys($_REQUEST);
	$scol = [];

	foreach ($rkeys as $key) {
		if (substr($key, 0, 2) == 's_' && substr($key, -3) !== '_op' && strlen($_REQUEST[$key]) > 0) {
			$col = substr($key, 2);
			if (!empty($_REQUEST[$key.'_op'])) {
				$col .= ':'.$_REQUEST[$key.'_op'];
			}

			array_push($scol, $col);
		}
	}

	return count($scol) > 0 ? $scol : null;
}


/**
 * Return sql search expression ([where, and]). Define search via conf.search= COLUMN:METHOD, .... 
 * Search methods: =|EQ, %$%|LIKE, %$|LLIKE, $%|RLIKE, [a,b], [] (with value = a,b), 
 * ]], [[, ][, ?|OPTION, <|LT, >|GT, <=|LE, >=|GE. Place _[WHERE¦AND]_SEARCH in query.
 *
 * Search value is either _REQUEST[s_NAME] of if not set and req.search=X: $_REQUEST[X].
 *
 * @hash $options …
 * where: where|and
 * value: (= $_REQUEST[s_COLUMN])
 * cols: e.g. name, descr or id:=, age:EQ, firstname:LIKE, lastname:%$%, …
 * @eol
 */ 
private function where(array $options = []) : string {
  $this->search = array_merge([
		'col' => '',
		'cname' => '',
		'method' => '',
		'value' => ''
	], $options);

	$this->search['expr'] = [];

	$multicol = false;
	if (isset($this->conf['search.value']) && $this->conf['search.value'] !== '') {
		$multicol = $this->conf['search.value'];
	}
	else if (empty($_REQUEST['scol']) && !empty($_REQUEST['sval'])) {
		$multicol = $_REQUEST['sval'];
	}

	foreach ($this->search['cols'] as $col_method) {
		if (strpos($col_method, ':') === false) {
			$col = $col_method;
			$method = null;
		}
		else {
			list ($col, $method) = explode(':', $col_method, 2);
		}

		$this->search['method'] = $method;
		$this->search['cname'] = $col;

		if (($pos = strpos($col, '.')) > 0) {
			$col = substr($col, $pos + 1);
		}

		$this->search['col'] = $col;
		$rkey = 's_'.$col;

		$this->search['value'] = $multicol === false ? '' : $multicol;
		if (isset($_REQUEST[$rkey]) && $_REQUEST[$rkey] !== '') {
			$this->search['value'] = $_REQUEST[$rkey];
		}

		if (is_null($method) && !empty($_REQUEST['s_'.$col.'_op'])) {
			$this->search['method'] = $_REQUEST['s_'.$col.'_op'];
		}

		// \rkphplib\lib\log_debug("SQLSearch.where:248> col=$col method={$this->search['method']} value=".$this->search['value']);
		if (strlen($this->search['value']) == 0 ||
				$this->searchDefault() ||
				$this->searchLike() ||
				$this->searchCompare() ||
				$this->searchFunc() ||
				$this->searchRange()) {
			// continue after first match
		}
	}

	if (count($this->search['expr']) == 0) {
		return '';
	}

	if ($multicol === false) {
		$sql_and = join(' AND ', $this->search['expr']);
	}
	else {
		$sql_and = join(' OR ', $this->search['expr']);
	}

	return $this->search['where'] == 'where' ? 'WHERE '.$sql_and : 'AND ('.$sql_and.')';
}


/**
 * Add search.expr and return true if search.method is EQ, LT,GT, LE, GE.
 */
private function searchCompare() : bool {
	$compare = [ 'EQ' => '=', 'LT' => '<', 'GT' => '>', 'LE' => '<=', 'GE' => '>=' ];
	$cmp = $this->search['method'];

	if (!isset($compare[$cmp])) {
		return false;
	}

	$op = $compare[$cmp];
	$value = preg_replace('/[^0-9\-\+\.]/', '', $this->search['value']);
	$expr = $this->search['cname'].' '.$compare[$cmp]." '$value'";
	array_push($this->search['expr'], $expr);
	return true;
}


/**
 * Add COL='VALUE' to search.expr if search.method is null.
 * Add COL IS NULL to search.expr if search.method is 'NULL'.
 * Specal search values are 'NULL', 'NOT NULL', 'EMPTY' and "''".
 */
private function searchDefault() : bool {
	if (is_null($this->search['method'])) {
		$expr = $this->search['cname']."='".ADatabase::escape($this->search['value'])."'";
	}
	else if ($this->search['method'] == 'NULL') {
		$expr = $this->search['cname'].' IS NULL';
	}
	else if ($this->search['value'] == 'NULL' || $this->search['value'] == 'NOT NULL') {
		$expr = $this->search['cname'].' IS '.$this->search['value'];
	}
	else if ($this->search['value'] == "''") {
		$expr = $this->search['cname']."=''";
	}
	else if ($this->search['value'] == "EMPTY") {
		$expr = '('.$this->search['cname']."='' or ".$this->search['cname'].' IS NULL)';
	}
	else {
		return false;
	}

	array_push($this->search['expr'], $expr);
	// \rkphplib\lib\log_debug("SQLSearch.searchDefault:319> expr=$expr");
	return true;
}


/**
 * Add search.expr and return true if search.method is LIKE, LLIKE, RLIKE.
 * Replace search.value = 'a b c' with 'a%b%c'.
 */
private function searchLike() : bool {
	$like = [ 'LIKE' => '%$%', 'LLIKE' => '$%', 'RLIKE' => '%$' ];
	$cmp = $this->search['method'];

	if (!isset($like[$cmp])) {
		return false;
	}

	$value = preg_replace('/ +/', '%', $this->search['value']);
	$value = str_replace('$', $value, $like[$cmp]);
	$expr = $this->search['cname']." LIKE '".ADatabase::escape($value)."'";
	array_push($this->search['expr'], $expr);
	return true;
}


/**
 * Add search.expr and return true if search.method is int, in, or.
 */
private function searchFunc() : bool {
	$func = [ 'int' => "=FLOOR('\$')", 'in' => " IN ('\$,')", 'or' => "" ];
	$cmp = $this->search['method'];

	if (!isset($func[$cmp])) {
		return false;
	}

	$value = trim($this->search['value']);
	$col = $this->search['cname'];
	$fcall = $func[$cmp];

	if ($cmp == 'or') {
		$list = preg_split('/\s*,\s*/', $value);
				
		for ($i = 0; $i < count($list); $i++) {
			$list[$i] = $col."='".ADatabase::escape($list[$i])."'";
		}

		$expr = '('.join(' OR ', $list).')';
	}
	else if (strpos($fcall, '$,') !== false) {
		$list = preg_split('/\s*,\s*/', $value);
				
		for ($i = 0; $i < count($list); $i++) {
			$list[$i] = ADatabase::escape($list[$i]);
		}

		$expr = $col.str_replace('$,', join("','", $list), $fcall);
	}
	else {
		$expr = $col.str_replace('$', ADatabase::escape($value), $fcall);
	}

	array_push($this->search['expr'], $expr);
	return true;
}

}

