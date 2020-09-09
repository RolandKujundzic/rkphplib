<?php

$xml_str = <<<XML
<doc>
	<person>John Smith</person>
	<person age="14">Frank Miller</person>
	<person>Anna Baum</person>
</doc>
XML;

$xml = new \rkphplib\XMLParser();
$xml->parse($xml_str);

print "\nInput:\n$xml_str\n";
print "Output:\n".$xml->toString()."\n";
print "Callback s:\n";
$xml->setCallback(null, [ 'doc/person' => 'xml_tag' ]);
$xml->parse($xml_str);

