<?php

$xml = <<< XML
<?xml version='1.0' standalone='yes'?>
<movies>
 <movie>
  <titles>Invalid end tag</title>
 </movie>
</movies>
XML;

$xml = new \rkphplib\XML($xml);
