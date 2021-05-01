<?php

$_REQUEST = [];
msearch('id,name,descr', 17);
msearch('', '');

$_REQUEST = [ 's_name_op' => 'LLIKE', 's_name2_op' => 'RLIKE' ];
msearch('id,name,name2,descr:LIKE', 'John', '_AND_SEARCH _SORT');

msearch('', '', 'SELECT WHERE _AND_SEARCH _SORT');
$_REQUEST = [ 'sort' => 'aprice' ];
msearch('', '', 'SELECT _SORT');

$_REQUEST = [ 's_id' => 'ID' ];
msearch('id:EQ,name:LLIKE');

$_REQUEST = [ 's_name' => 'NAME' ];
msearch('id:EQ,name:LLIKE,descr:LIKE');
