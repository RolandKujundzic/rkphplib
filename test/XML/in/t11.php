<?php

$xml_str  = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';
$xml_str .= <<<END
<json2xml a="1">
	<names n="2">a</names>
	<names>b</names>
	<names>c</names>
	<id x="x">a</id>
	<id>b</id>
	<id>c</id>
</json2xml>
END;

print \rkphplib\XML::fromMap(\rkphplib\XML::toMap($xml_str, true))."\n";
$xml = new \rkphplib\XML($xml_str);

$xml_array = $xml->toArray();
print_r($xml_array); 

print $xml->fromMap($xml_array, 'json2xml');

