<?php declare(strict_types=1);

require_once(dirname(dirname(__DIR__)).'/src/XML.class.php');
  
use rkphplib\XML;

$xml = <<< XML
<?xml version='1.0' standalone='yes'?>
<movies>
 <movie>
  <titles>Invalid end tag</title>
 </movie>
</movies>
XML;

$xml = new \rkphplib\XML($xml);

