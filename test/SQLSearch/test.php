<?php

require_once '../../src/SQLSearch.php';

$_REQUEST['s_name_op'] = 'LLIKE';
$_REQUEST['s_name2_op'] = 'RLIKE';

$search = new \rkphplib\SQLSearch([
	'search' => 'id,name,name2,descr:LIKE',
	'search.value' => 17
]);

print $search->query()."\n";
print $search->query('_AND_SEARCH');
