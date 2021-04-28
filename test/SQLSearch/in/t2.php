<?php

msearch('id,name,descr', 17);

$_REQUEST['s_name_op'] = 'LLIKE';
$_REQUEST['s_name2_op'] = 'RLIKE';
msearch('id,name,name2,descr:LIKE', 'John', '_AND_SEARCH');

