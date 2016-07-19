<?php

namespace rkphplib;

require_once(__DIR__.'/ADatabase.class.php');
require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;



/**
 * Category table.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class Category {


/** @var ADatabase $db (default = null) */
protected $db = null;



/**
 * Set database connection string. Change table name and add extra cols if necessary.
 * 
 * @param string $dsn
 * @param string $table (default = category)
 * @param map $extra_cols
 */
public function setDSN($dsn, $table = 'category', $extra_cols = []) {
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

	$db->setQuery('insert', "INSERT INTO $table (pid, name) VALUES ('{:=pid}', '{:=name}')");

	$db->setQuery('select_name_pid', "SELECT * FROM $table WHERE name='{:=name}' AND pid='{:=pid}'");

	$db->setQuery('select_id', "SELECT * FROM $table WHERE id='{:=id}'");
}


/**
 * Create category table. Default conf:
 *
 * - @table=category
 * - @id=1
 * - pid= int:::32
 * - name= varchar:255::1
 *
 * Don't change "@id" and "pid" in conf. For multilanguage use "@language" => 'de, en, ...' and "@multilang" => 'name'.
 *
 * @param map $conf
 * @see ADatabase::createTable()
 */
public function createTable($conf = [ '@table' => 'category', '@id' => 1, 'pid' => 'int:::32', 'name' => 'varchar:255::1' ]) {
	$this->db->createTable($conf);
}


/**
 *
 */
public function add($name, $pid = NULL) {

  $check_category_query = "select * from category WHERE cat_name='$add_categoryname' AND parent_id='$cat_pid'";
  $result = mysqli_query($db_conn, $check_category_query);

  if (mysqli_num_rows($result) > 0) {
    $error_flag = true;
    echo "categorynamerror";
  }

}


}
