<?php

$xml = <<<XML
<?xml version="1.0" standalone="yes"?>
<people xmlns:p="http://example.org/ns" xmlns:t="http://example.org/test">
    <p:person id="1">John Doe</p:person>
    <p:person id="2">Susie Q. Public</p:person>
</people>
XML;

$sxe = new SimpleXMLElement($xml);

$xml_obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
$xml_map = json_decode(json_encode((array)$xml_obj, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), true);

$namespaces = $sxe->getNamespaces(true);
print_r($namespaces);
print_r($xml_map);

