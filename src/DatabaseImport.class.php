<?php

namespace rkphplib;

require_once __DIR__.'/Database.class.php';
require_once __DIR__.'/File.class.php';


/**
 * Import csv|xml|xls[x] file into table.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 */
class DatabaseImport {

// @var ADatabase $db
private $db = null;

// @var int $rownum
private $rownum = 0;


/**
 *
 */
public function __construct(string $dsn = '') {
	$this->db = Database::getInstance($dsn);
}


/**
 * Import csv file
 */
public function csv(string $file, string $delimiter = "'", string $quote = '"') : void {
	$fh = File::open($file, 'rb');

	while (($row = File::readCSV($fh, $delimiter, $quote))) {
		if (count($row) == 0 || (count($row) == 1 && strlen(trim($row[0])) == 0)) {
			continue;
		}

		if ($this->rownum == 0) {
			$this->createTable($row);
		}

		$this->rownum++;
	}

	File::close($fh);
}


/**
 *
 */
private function createTable(array $row) : void {
	print_r($row);
}


}

