<?php

require_once(dirname(dirname(__DIR__)).'/src/XML.php');
  
use rkphplib\XML;

$xml1 = <<<XML
<?xml version="1.0" standalone="yes"?>
<people xmlns:p="http://example.org/ns" xmlns:t="http://example.org/test">
    <p:person id="1">John Doe</p:person>
    <p:person id="2">Susie Q. Public</p:person>
</people>
XML;

$xml2 = <<<XML
<?xml version="1.0" standalone="yes"?>
<people xmlns__p="http://example.org/ns" xmlns__t="http://example.org/test">
    <p__person id="1">John Doe</p__person>
    <p__person id="2">Susie Q. Public</p__person>
</people>
XML;



$xo = simplexml_load_string($xml1);
$namespaces = $xo->getNamespaces(true);



print_r($namespaces);
print_r(XML::toArray($xo));

print_r(XML::toMap($xml1));

