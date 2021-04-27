<?php

$_REQUEST['sort'] = 'aname';

$test = [
  'rkphplib\\SQLSearch.sort',
  [ 'aid', 'ORDER BY id' ],
  [ 'dsince', 'ORDER BY since DESC' ],
  [ null, 'ORDER BY name' ]
];

