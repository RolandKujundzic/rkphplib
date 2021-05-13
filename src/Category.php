<?php

namespace rkphplib;

require_once __DIR__.'/Database.class.php';
require_once __DIR__.'/Exception.class.php';

use rkphplib\Database;
use rkphplib\Exception;


/**
 * Category table.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Category {

// @var hash $conf
private $conf = [];

// @var ADatabase $db (default = null)
private $db = null;


/**
 * @hash $conf …
 * dsn: SETTINGS_DSN
 * log: 0 (1 = print log message)
 * import:
 * prefix:
 * category: $prefix.'category'
 * cat_item: $prefix.'cat_item'
 * item: $prefix.'item'
 * @eol
 */
public function __construct(array $conf = []) {
	$this->conf = array_merge([
		'log' => 0,
		'dsn' => SETTINGS_DSN,
		'prefix' => '',
	], $conf);

	$category = empty($this->conf['category']) ? $this->conf['prefix'].'category' : $this->conf['category'];
	$cat_item = empty($this->conf['cat_item']) ? $this->conf['prefix'].'cat_item' : $this->conf['cat_item'];
	$item = empty($this->conf['item']) ? $this->conf['prefix'].'item' : $this->conf['item'];

	$a_import = '';
	$where_import = '';
	if (!empty($this->conf['import']) && preg_match('/^[a-z0-9_]+$/', $this->conf['import'])) {
		$a_import = "AND a.import='".$this->conf['import']."'";
		$where_import = "WHERE import='".$this->conf['import']."'";
	}

	$qmap = [
		'drop' => "DROP TABLE IF EXISTS $category",

		'insert' => "INSERT INTO $category (pid, name) VALUES ({:=pid}, {:=name})",

		'select_name_pid' => "SELECT * FROM $category WHERE name={:=name} AND pid={:=pid}",

		'select_id' => "SELECT * FROM $category WHERE id={:=id}",

		'reset_cat_count' => "UPDATE $category SET dc=0, tc=0, di=0, ti=0 $where_import",

		'update_cat_dc' => "UPDATE $category AS a INNER JOIN ".
			"(SELECT pid, count(*) AS dc FROM $category $where_import GROUP BY pid) AS b ".
			"ON a.id=b.pid $a_import SET a.dc=b.dc",

		'update_cat_tc' => "UPDATE $category AS a INNER JOIN ".
			"(SELECT sid, sum(dc) AS tc FROM $category $where_import GROUP BY sid) AS b ".
			"ON b.sid LIKE CONCAT(a.sid, '%') $a_import SET a.tc=b.tc",

		'update_cat_di' => "UPDATE $category AS a INNER JOIN ".
			"(SELECT ci.category, count(*) AS di FROM $cat_item ci, $item i ".
			"WHERE ci.item=i.id AND i.status=1 GROUP BY ci.category) AS b ".
			"ON a.id=b.category $a_import SET a.di=b.di",

		'update_cat_ti' => "SELECT 'ToDo'",

		'update_cat_tc' => "UPDATE $category AS a INNER JOIN ".
			"(SELECT pid, count(*) AS tc FROM $category $where_import GROUP BY pid) AS b ".
			"ON b.id LIKE CONCAT(a.id, '%') $a_import SET a.tc=b.tc",
	];

	$qmap['create'] = <<<END
CREATE TABLE {$category} (
id int NOT NULL AUTO_INCREMENT,
pid int,
name varchar(255) NOT NULL,

PRIMARY KEY (id),
FOREIGN KEY (pid) REFERENCES {$category}(id) ON DELETE CASCADE ON UPDATE CASCADE
);
END;

	$this->db = Database::getInstance($this->conf['dsn'], $qmap);
}


/**
 * Update dc, tc, di, ti counter
 */
public function updateCounter() : void {
	$this->runQuery([ 'reset_cat_count', 'update_cat_dc', 'update_cat_di' ]); // , 'update_cat_tc', 'update_cat_ti' ]); 
}


/**
 * Run queries from $qkey_list.
 */
private function runQuery(array $qkey_list) : void {
	for ($i = 0; $i < count($qkey_list); $i++) {
		if ($this->conf['log']) {
			print $this->db->getQuery($qkey_list[$i]).";\n";
		}

		$this->db->exec($qkey_list[$i]);
	}
}


/**
 * Create category table. Default:
 *
 * - @table=category
 * - @id=1
 * - pid= int:::32
 * - name= varchar:255::1
 *
 * Don't change "@id" and "pid" in conf. For multilanguage use "@language" => 'de, en, ...' and "@multilang" => 'name'.
 */
public function createTable(string $table = 'category', array $custom_cols = []) : void {

	$conf = [ '@table' => $table, '@id' => 1, 'pid' => 'int:::32', 'name' => 'varchar:255::1' ];

	foreach ($custom_cols as $key => $value) {
		$conf[$key] = $value;
	}

	$this->db->createTable($conf);
}


/**
 * @ToDo
 */
public function add(string $name, int $pid = NULL) : void {

  $check_category_query = "select * from category WHERE cat_name='$add_categoryname' AND parent_id='$cat_pid'";
  $result = mysqli_query($db_conn, $check_category_query);

  if (mysqli_num_rows($result) > 0) {
    $error_flag = true;
    echo "categorynamerror";
  }

	throw new Exception('ToDo ...');
}


/**
 * Return [ sid, level]. Call category_sid_level(null) to reset counter.
 * Max 57 entries per level.
 */
public static function sid_level(?string $id, ?string $pid = null) : array {
	static $last_pid = null;
	static $last_level = 1;
	static $id_pid = [];
	static $id_sid = [];
	static $ln = [ 1 ];

	static $az = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
		'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K',
		'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V',
		'W', 'X', 'Y', 'Z',
		'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k',
		'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v',
		'w', 'x', 'y', 'z' ];

	if (is_null($id)) {
		$last_pid = null;
		$last_level = 1;
		$id_pid = [];
		$id_sid = [];
		$ln = [ 1 ];

		return [];
	}

	if ($pid === '') {
		$pid = null;
	}

	$level = 1;
	$sid = '';

	if (array_key_exists($pid, $id_pid)) {
		$sid = $id_sid[$pid];
		$level = strlen($sid) + 1;
	}

	if ($last_level < $level) {
		array_push($ln, 1);
	}

	$n = $ln[$level - 1];

	if ($n > 61) {
		throw new \Exception('too many categories');
	}

	$sid .= $az[$n];

	$ln[$level - 1]++;

	if ($last_level > $level) {
		$ln = array_slice($ln, 0, $level);
	}

	$id_pid[$id] = $pid;
	$id_sid[$id] = $sid;
	$last_level = $level;
	$last_pid = $pid;

	return [ $sid, $level ];
}

}
