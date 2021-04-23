<?php

namespace rkphplib;

require_once __DIR__.'/ADatabase.class.php';


/**
 * SQL Helper
 * @author Roland Kujundzic <roland@inkoeln.com>
 * @copyright 2018-2021 Roland Kujundzic
 */
class SQL {

/**
 * @example sort('aid') = 'ORDER BY id'
 * @example sort('dsince') = 'ORDER BY since DESC'
 * @example $_REQUEST['sort'] = 'aname'; sort('', 'sort') = 'ORDER BY name'
 */
public static function sort(string $sort = '', string $rkey = '') : string {
	if (isset($_REQUEST[$rkey])) {
		$sort = $_REQUEST[$rkey];
	}

	if (empty($sort)) {
		return '';
	}

	$direction = mb_substr($sort, 0, 1);
	$column = ADatabase::escape_name(mb_substr($sort, 1));
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

}

