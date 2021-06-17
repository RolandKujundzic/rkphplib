<?php

namespace rkphplib\lib;

require_once __DIR__.'/../Exception.php';
require_once __DIR__.'/split_str.php';

use rkphplib\Exception;


/**
 * Split table string at delimiter. Ignore empty rows. Trim all cells. 
 * If $split_cell is set split into key value. Escape delimiter with 
 * leading backslash. Default delimiter: col = "|",  row = "\n"
 */
function split_table(string $table_str, string $col_delimiter = '|', string $row_delimiter = "\n", string $split_cell = '') : array {
  $table = [];
	$table_str = trim($table_str);

	if (strlen($table_str) == 0) {
		return $table;
	}

	$rows = split_str($row_delimiter, $table_str);

	for ($i = 0; $i < count($rows); $i++) {
		if (strlen($rows[$i]) > 0) {
			if ($split_cell) {
				$v = split_str($col_delimiter, $rows[$i]);
				$scl = mb_strlen($split_cell);
				$table_row = [];

				for ($j = 0; $j < count($v); $j++) {
					if (($pos = mb_strpos($v[$j], $split_cell)) > 0) {
						$key = trim(mb_substr($v[$j], 0, $pos));
						$value = trim(mb_substr($v[$j], $pos + $scl));
						$table_row[$key] = $value;
					}
					else {
						throw new Exception('split_cell failed in '.$v[$j], "i=$i j=$j row=".$rows[$i]);
					}
				}
			}
			else {
				$table_row = split_str($col_delimiter, $rows[$i]);
			}

			array_push($table, $table_row);
		}
	}

	return $table;
}

