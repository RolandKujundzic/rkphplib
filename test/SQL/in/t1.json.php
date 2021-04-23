<?php

$_REQUEST['sort'] = 'aname';

$test = [
  'rkphplib\SQL::sort',
  [ 'aid', 'ORDER BY id' ],
  [ 'dsince', 'ORDER BY since DESC' ],
  [ '', 'sort', 'ORDER BY name' ]
];

