<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/Database.class.php');
require_once($parent_dir.'/File.class.php');
require_once($parent_dir.'/Dir.class.php');
require_once($parent_dir.'/lib/conf2kv.php');
require_once($parent_dir.'/lib/kv2conf.php');
require_once($parent_dir.'/lib/split_str.php');

use \rkphplib\Database;
use \rkphplib\ADatabase;
use \rkphplib\File;
use \rkphplib\Dir;



/**
 * Execute SQL queries.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TSQL implements TokPlugin {

/** @var Tokenizer $tok */
protected $tok = null;

/** @var ADatabase $db */
protected $db = null;

/** @var map first_row = null */
protected $first_row = null;



/**
 * Register output plugins. Examples:
 *
 * @tok {sql:query}SELECT * FROM test WHERE name LIKE '{:=name}%' OR id={esc:name}{:sql}
 * @tok {sql:query}UPDATE test SET name={:=name} WHERE id={:=id}{:sql}
 *
 * @tok {sql:qkey:test}SELECT * FROM test WHERE name LIKE '{:=name}%' OR id={:=name}{:sql}
 * @tok {sql:query:test}name=something{:sql}
 *
 * @tok {sql:dsn}mysqli://user:pass@tcp+localhost/dbname{:sql} (use SETTINGS_DSN by default)
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$this->tok = $tok;

	$plugin = [];
	$plugin['sql:query'] = 0;
	$plugin['sql:dsn'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY; 
	$plugin['sql:name'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['sql:qkey'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['sql:json'] = TokPlugin::REQUIRE_BODY;
	$plugin['sql:col'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['sql:options'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['sql:getId'] = TokPlugin::NO_PARAM;
	$plugin['sql:nextId'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['sql:in'] = TokPlugin::CSLIST_BODY;
	$plugin['sql:hasTable'] = 0;
	$plugin['sql:password'] = 0;
	$plugin['sql:import'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['sql'] = 0;
	$plugin['null'] = TokPlugin::NO_PARAM;

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
 * Return 1 if table(s) exist.
 *
 * @tok {sql:hasTable:login} = 1 if table login exists (otherwise '')
 * @tok {sql:hasTable}login,user{:sql} = 1 if table login and user exists
 *
 * @param string $param
 * @param string $arg
 * @return 1|''
 */
public function tok_sql_hasTable($param, $arg) {
	$list = empty($param) ? $arg : $param;
	$tables = \rkphplib\lib\split_str(',', $list);
	$res = true;

	for ($i = 0; $res && $i < count($tables); $i++) {
		if (!$this->db->hasTable($tables[$i])) {
			$res = false;
		}
	}

	// \rkphplib\lib\log_debug("tok_sql_hasTable> list=[$list] res=[$res]");
	return $res ? 1 : '';
}


/**
 * Import sql dump. 
 *
 * @tok {sql:import}directory=apps/shop/setup/sql|#|tables={const:DOCROOT}/setup/sql{:sql}
 *
 * If table parameter not empty try: directory/table.sql, directory/alter/table.sql, directory/insert/table.sql.
 * Otherwise import all sql files (directory/[alter/|insert/]*.sql). If cms/[APP|cms=]/setup/sql/tables.txt exists 
 * assume tables= table names in tables.txt.
 *
 * @tok {sql:import}dump=path/to/dump.sql|#|drop_table=1|#|ignore_foreign_keys=0{:sql}
 *
 * If drop is true add "DROP TABLE IF EXISTS $table; 
 *
 * @tok_log log.sql_import
 * @throws
 * @param map $p
 * @return ''
 */
public function tok_sql_import($p) {

	if (empty($p['directory'])) {
		if (!empty($p['dump'])) {
			$flags = 0;

			if (!empty($p['drop_table'])) {
				$flags = $flags | ADatabase::LOAD_DUMP_ADD_DROP_TABLE;
			}

			if (!empty($p['ignore_foreign_keys'])) {
				$flags = $flags | ADatabase::LOAD_DUMP_ADD_IGNORE_FOREIGN_KEYS;
			}

			$flags = $flags | ADatabase::LOAD_DUMP_USE_SHELL;
			$this->db->loadDump($p['dump'], $flags);
		}

		return '';
	}

	if (!Dir::exists($p['directory'])) {
		return '';
	}

	$cms_setup = $p['directory'];
	$cms_app = basename($p['directory']);
	if (basename(getcwd()) == 'cms') {
		$cms_setup = ($cms_app == 'cms') ? 'setup/sql' : 'apps/'.$cms_app.'/setup/sql';
	}

	if (!empty($p['tables'])) {
		$files = \rkphplib\lib\split_str(',', $p['tables']);
	}
	else if (File::exists($p['directory'].'/tables.txt')) {
		$files = \rkphplib\lib\split_str("\n", File::load($p['directory'].'/tables.txt'), true);
	}
	else if (File::exists($cms_setup.'/tables.txt')) {
		$files = \rkphplib\lib\split_str("\n", File::load($cms_setup.'/tables.txt'), true);
	}
	else {
		$files = Dir::scanDir($p['directory'], [ '.sql' ]);
	}

	foreach ($files as $file) {
		$base = basename($file);

		$flags = ADatabase::LOAD_DUMP_ADD_DROP_TABLE | ADatabase::LOAD_DUMP_ADD_IGNORE_FOREIGN_KEYS | ADatabase::LOAD_DUMP_USE_SHELL;

		$file = $p['directory'].'/'.$base.'.sql';
		if (File::exists($file)) {
			$this->db->loadDump($file, $flags);
		}

		$flags = ADatabase::LOAD_DUMP_ADD_IGNORE_FOREIGN_KEYS | ADatabase::LOAD_DUMP_USE_SHELL;

		$file = $p['directory'].'/alter/'.$base.'.sql';	
		if (File::exists($file)) {
			$this->db->loadDump($file, $flags);
		}

		$file = $p['directory'].'/insert/'.$base.'.sql';
		if (File::exists($file)) {
			$this->db->loadDump($file, $flags);
		}
	}

	$file = $p['directory'].'/view.sql';
	if (File::exists($file)) {
		$this->db->loadDump($file, $flags);
	}

	return '';
}


/**
 * Return next unique id. 
 *
 * @tok {sql:nextId:$table}
 * @tok {sql:nextId:$ignore_owner.$name_id} ({login:owner}, $name_min and $name_max must exist too)
 *
 * @see ADatabase.nextId($table)
 * @param string $table
 * @return int
 */
public function tok_sql_nextId($table) {

	if (($pos = strpos($table, '_owner.')) !== false) {
		$owner_id = $this->tok->callPlugin('login', 'tok_login', [ 'owner' ]);
		$owner_table = ADatabase::escape_name(substr($table, 0, $pos + 6));
		$id_count = ADatabase::escape_name(substr($table, $pos + 7));
		$id_max = str_replace('_id', '_max', $id_count);
		$id_min = str_replace('_id', '_max', $id_count);
		$qkey = str_replace('.', '_next_', $table);

		if (!$this->db->hasQuery($qkey)) {
	    $this->db->setQuery($qkey, "UPDATE $owner_table SET $id_count = LAST_INSERT_ID($id_count + 1) ".
				"WHERE id={:=id} AND $id_count + 1 < $id_max AND $id_count + 1 > $id_min");
		}

		$this->db->execute($this->db->getQuery($qkey, [ 'id' => $owner_id ]));
		return $this->db->getInsertId();
	}
	else {
		return $this->db->nextId($table);
	}
}


/**
 * Return result of mysql query "SELECT PASSWORD('$password')".
 *
 * @tok {sql:password:kv} = '' if _REQUEST[password] empty or [password=PASSWORD(_REQUEST[password])] otherwise
 * @tok {sql:password}secret{:sql} = PASSWORD('secret') = *0B32... 
 *
 * @param string $password
 * @return string
 */
public function tok_sql_password($param, $password) {

	if ($param == 'kv') {
		if (!empty($_REQUEST['password'])) {
			$mysql_pass = '*'.strtoupper(sha1(sha1($_REQUEST['password'], true)));
			$mysql_pass = empty($_REQUEST['password']) ? '' : 'password='.$mysql_pass;
		}
		else {
			$mysql_pass = '';
		}
	}
	else {
		$mysql_pass = '*'.strtoupper(sha1(sha1($password, true)));
	}

	// \rkphplib\lib\log_debug("tok_sql_password($param, $password)> $mysql_pass");
	return $mysql_pass;
}


/**
 * Return last auto_increment id.
 * 
 * @tok {sql:getId}[$query]{:sql}
 *
 * @see ADatabase.getInsertId()
 * @param string $query (optional)
 * @return int
 */
public function tok_sql_getId($query) {
	if (!empty($query)) {
		$this->db->execute($query);
	}

	return $this->db->getInsertId();
}


/**
 * Convert list into escaped sql list.
 *
 * @tok {sql:in:age}18,19,20{:sql} -> age IN ('18', '19', '20')
 * @tok {sql:in}admin, user{:sql} -> ('admin', 'user')
 * 
 * @param string $param
 * @param array $list
 */
public function tok_sql_in($param, $list) {
	$in = [];

	for ($i = 0; i < count($list); $i++) {
		array_push($in, $this->db->esc($list[$i]));
	}

	$res = "('".join("', '", $in)."')";

	if ($param) {
		$res = $this->db->esc_name($param).' IN '.$res;
	}

	return $res;
}


/**
 * Set database connection string. Example:
 *
 * @tok {sql:dsn}mysqli://user:pass@tcp+localhost/dbname{:sql}
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
 * @tok {sql:qkey:test}SELECT * FROM test WHERE id={:=id}{:sql}
 * @tok {sql:query:test}
 *
 * @throws
 * @param string $qkey
 * @param string $query
 * @return ''
 */
public function tok_sql_qkey($qkey, $query) {
	$this->db->setQuery($qkey, $query);
}


/**
 * Execute sql query. Example:
 *
 * @tok {sql:query}UPDATE test SET name={:=name} WHERE id={:=id}{:sql}
 *
 * @tok {sql:qkey:test}SELECT * FROM test WHERE id={:=id}{:sql}
 * @tok {sql:query:test}id=31{:sql}
 *
 * @throws
 * @param string
 * @param string
 * @return ''
 */
public function tok_sql_query($qkey, $query) {

	if (empty($qkey) && mb_strpos($query, $this->tok->getTag('TAG:PREFIX')) !== false) {
		$this->db->setQuery('current_query', $query);
		$query = $this->db->getQuery('current_query', $_REQUEST);
	}

	if (!empty($qkey)) {
		$replace = \rkphplib\lib\conf2kv($query);
		$query = $this->db->getQuery($qkey, $replace);
	}

	$query_prefix = strtolower(substr(trim($query), 0, 20));
	$use_result = (strpos($query_prefix, 'select ') === 0) || (strpos($query_prefix, 'show ') === 0);

	if ($use_result) {
		$this->first_row = null;
	}

	$this->db->execute($query, $use_result);
	return '';
}


/**
 * Return options list. Parameter:
 *
 * - mode = html (default)
 * - selected = ...
 *
 * @tok {sql:query}SELECT country_id AS value, name AS label FROM shop_country_translation 
 *   WHERE language={esc:}{language:get}{:esc} AS label ORDER BY language{:sql}
 * @tok {sql:options}selected=de{:sql}
 * 
 * @param hash $p
 * @return string
 */
public function tok_sql_options($p = []) {
	$default = [ 'mode' => 'html' ];
	$p = array_merge($default, $p);
	$res = '';

	if ($p['mode'] == 'html') {
		$country = empty($p['selected']) ? '' : $p['selected'];

		while (($row = $this->db->getNextRow())) {
			$selected = ($row['value'] == $country) ? ' selected' : '';
			$res .= '<option value="'.\rkphplib\lib\htmlescape($row['value']).'"'.$selected.'>'.
				\rkphplib\lib\htmlescape($row['label'])."</option>\n";
		}
	}

	return $res;
}


/**
 * Return colum value of last {sql_query:}.
 *
 * @tok {sql:col:name} -> NAME
 * @tok {sql:col:*} -> name=NAME|#|id=ID
 *  
 * @throws
 * @param string $name
 * @return string
 */
public function tok_sql_col($name) {
	if (is_null($this->first_row)) {
		$this->first_row = $this->db->getNextRow();

		if (is_null($this->first_row)) {
			$this->first_row = [];
		}
	}

	if ($name == '*') {
		return \rkphplib\lib\kv2conf($this->first_row);
	}

	return (isset($this->first_row[$name]) || array_key_exists($name, $this->first_row)) ? $this->first_row[$name] : '';
}


/**
 * Return query result as json. Use mode = hash (key AS name, value AS value) for hash result.
 * Use spreadsheet for table in spreadsheet (vector<vector>) format.
 * Use spreadsheet_js for "var spreadsheet_rows = [ [ ... ] ... ]; var spreadsheet_cols = [ '...', ... ];",
 * configure via {var:=#sql:json:spreadsheet_js}...{:var} (@see spreadSheetJS).
 * Otherwise return table (vector<map>).
 *
 * @tok {sql:json:hash}SELECT name, value FROM abc{:sql} -> return json encoded map
 * @tok {sql:json[:table]}SELECT * FROM somewhere{:sql} -> return json encoded table
 * @tok {sql:spreadsheet}SELECT * FROM somewhere{:sql} -> return json encoded csv table (table[0] = column names)
 * @tok {sql:spreadsheet_js}SELECT * FROM somewhere{:sql} -> @see spreadSheetJS
 * 
 * @throws
 * @param string $mode table=''|hash|spreadsheet_js
 * @param string $query
 * @return table|hash
 */
public function tok_sql_json($mode, $query) {
	require_once(__DIR__.'/../JSON.class.php');

	if ($mode == 'hash') {
		$dbres = $this->db->selectHash($query);
	}
	else if ($mode == 'spreadsheet' || $mode == 'spreadsheet_js') {
		$table = $this->db->select($query);
		$dbres = [];

		if (count($table) > 0) {
			$cols = array_keys($table[0]);
			array_push($dbres, $cols);
		}

		for ($i = 0; $i < count($table); $i++) {
			array_push($dbres, array_values($table[$i])); 
		}

		if ($mode == 'spreadsheet_js') {
			return $this->spreadSheetJS($dbres);
		}
	}
	else if (empty($mode) || $mode == 'table') {
		$dbres = $this->db->select($query);
	}

	return \rkphplib\JSON::encode($dbres);
}


/**
 * Return spreadsheet data. Example:
 *
 * @tok {var:=#sql:json:spreadsheet_js}
 *				readonly= id,...|#|
 * 				pagebreak=100|#|
 * 				required=name,...|#|
 *				columns= NAME[:ALIAS], id:ID, age, ...|#|
 *				check.id=...{:var}
 * @tok {sql:json:spreadsheet_js}SELECT id, age, ... FROM ... {:sql}
 *
 * var spreadsheet_cols = [ 'id', 'lchange', ... ];
 * var spreadsheet_col_info = [ { type: 'text', readOnly: true }, { type: 'text' }, ... ];
 * var spreadsheet_rows = [ [ 1, '2017-02-18 14:32:00' ], ... ];
 *
 * var xls = new Handsontable(container, {
 *   data: spreadsheet_rows,
 *   colHeaders: spreadsheet_cols,
 *   columns: spreadsheet_col_info,
 *   rowHeaders: false,
 *   ... });
 *
 * @param table
 * @return string
 */
private function spreadSheetJS($table) {

	$config = $this->tok->getVar('sql:json:spreadsheet_js!');

	$split_str = [ 'readonly', 'columns', 'required' ];
	foreach ($split_str as $name) {
		if (!empty($config[$name])) {
			$config[$name] = \rkphplib\lib\split_str(',', $config[$name]);
		}
	}

	$col_names = array_shift($table);
	$col_label = [];
	$col_info = [];

	for ($i = 0; $i < count($config['columns']); $i++) {
		$tmp = explode(':', $config['columns'][$i]);

		$cname = $tmp[0];
		$alias = empty($tmp[1]) ? $cname : $tmp[1];
		$col = [ 'col' => $cname, 'type' => 'text' ];

		if (isset($config['readonly']) && in_array($cname, $config['readonly'])) {
			$col['readOnly'] = true;
		}
		else if (isset($config['required']) && in_array($cname, $config['required'])) {
			$col['required'] = true;
		}

		array_push($col_label, $alias);
		array_push($col_info, $col);
	}

	$res  = 'var spreadsheet_col_label = '.\rkphplib\JSON::encode($col_label).";\n"; 	
	$res .= 'var spreadsheet_col_info = '.\rkphplib\JSON::encode($col_info).";\n";
	$res .= 'var spreadsheet_rows = '.\rkphplib\JSON::encode($table).";\n";

	return $res;
}


/**
 * Escape null value. Example:
 *
 * @tok {null:}abc{:null} = 'abc'
 * @tok {null:}null{:null} = {null:}Null{:null} = {null:}{:null} = NULL
 *
 * @param string $param
 * @param string $arg
 * @return string
 */
public function tok_null($arg) {

  if (strtolower(trim($arg)) == 'null') {
		$res = 'NULL';
  }
	else if (strlen(trim($arg)) > 0) {
		$res = "'".\rkphplib\ADatabase::escape($res)."'";
  }
	else {
  	$res = 'NULL';
	}

  return $res;
}


/**
 * SQL Escape trim($name).
 *
 * @tok {sql_name:}a b{:sql_name} -> `a b`
 * @see \rkphplib\ADatabase::escape_name
 * @param string $name
 * @return string
 */
public function tok_sql_name($name) {
  return \rkphplib\ADatabase::escape_name(trim($name));
}

	
}

