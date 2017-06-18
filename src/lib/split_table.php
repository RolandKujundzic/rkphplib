<?php

namespace rkphplib\lib;

require_once(__DIR__.'/split_str.php');


/**
 * Split table string at delimiter. Ignore empty rows. Trim all cells. Escape with leading backslash.
 * Default delimiter: col = "|",  row = "\n"
 *
 * @param string $table_str
 * @param string $col_delimiter
 * @param string $row_delimiter
 * @param boolean $trim_cell
 * @return array
 */
function split_table($table_str, $col_delimiter = '|', $row_delimiter = "\n", $trim_cell = true) {
  $table = [];

	if ($trim_cell) {
		$table_str = trim($table_str);
	}

	if (strlen($table_str) == 0) {
		return $table;
	}

	$rows = split_str($row_delimiter, $table_str);

	for ($i = 0; $i < count($rows); $i++) {
		if (strlen($rows[$i]) > 0) {
			array_push($table, split_str($col_delimiter, $rows[$i]));
		}
	}

	return $table;
}

