<?php

namespace rkphplib;

require_once __DIR__.'/ADatabase.class.php';
require_once __DIR__.'/Exception.class.php';



/**
 * Category table.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class Category {

// @var ADatabase $db (default = null)
protected $db = null;



/**
 * Set database connection string. Change table name and add extra cols if necessary.
 */
public function setDSN(string $dsn, string $table = 'category', array $extra_cols = []) : void {
	$db = new Database();
	$db->setDSN($dsn);

	$table = ADatabase::escape_name($table);

	$db->setQuery('drop', "DROP TABLE IF EXISTS $table");

	$query = <<<END
CREATE TABLE {$table} (
id int NOT NULL AUTO_INCREMENT,
pid int,
name varchar(255) NOT NULL,

PRIMARY KEY (id),
FOREIGN KEY (pid) REFERENCES {$table}(id) ON DELETE CASCADE ON UPDATE CASCADE
);
END;
	$db->setQuery('create', $query);

	$db->setQuery('insert', "INSERT INTO $table (pid, name) VALUES ({:=pid}, {:=name})");

	$db->setQuery('select_name_pid', "SELECT * FROM $table WHERE name={:=name} AND pid={:=pid}");

	$db->setQuery('select_id', "SELECT * FROM $table WHERE id={:=id}");
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


}
