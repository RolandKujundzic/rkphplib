<?php

require_once 'settings.php';
require_once 'php/rkphplib/src/Database.php';

$conf = [
	'@table' => 'csv_nitras2',
	'Artikelnummer' => "enum:'G115B','G115BP','G130B','G130BP','G145B','G145BP':::",
  'Farbe' => "enum:'','schwarz','marineblau','grau','orange','gelb','rot','khaki':::",
  'VPEAN1' => "varbinary:20::"
];

// Fix createTable enum column ...
$db = \rkphplib\Database::getInstance();
$db->createTable($conf);

