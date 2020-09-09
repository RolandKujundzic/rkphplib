<?php

$xml_str = <<<XML
<people xmlns:xsi="a" xsi:schemaLocation="http://..." xmlns:l="b" xmlns="c"> 
	<person>
		<name>Marcus</name>
		<language>
			<l:primary>XML</l:primary>
			<xsi:secondary>HTML</xsi:secondary>
		</language>
	</person>
</people>
XML;

$xml = new \rkphplib\XMLParser();
$xml->parse($xml_str);

print "\nInput:\n$xml_str\n";
print "Output:\n".$xml->toString()."\n";
print "Callback s:\n";
$xml->setCallback(null, [ 'people/person' => 'xml_tag' ]);
$xml->parse($xml_str);

