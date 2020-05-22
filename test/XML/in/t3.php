<?php

$xml_str  = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';
$xml_str .= <<<END
<json2xml>
	<names>a</names>
	<names>b</names>
	<names>c</names>
	<id>a</id>
	<id>b</id>
	<id>c</id>
</json2xml>
END;

print \rkphplib\XML::fromMap(\rkphplib\XML::toMap($xml_str))."\n";
$xml = new \rkphplib\XML($xml_str);

$xml_array = $xml->toArray();
print_r($xml_array); 

$xml->load($xml_array);
print $xml;

