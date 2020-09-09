<?php

$xml_str = <<<XML
<doc>
	<person firstname="John" middlename="Peter" lastname="Smith">John Peter Smith</person>
	<attrib-only k1="v1" k2="v2" />
	<age data-born="17.05.1990">30</age>
	<address data-test="test">
		<street>Some Street</street>
	</address>
	<phone>001</phone>
	<phone>002</phone>
	<utf8>&amp; äüöß</utf8>
	<cdata><![CDATA[... cdata example ...]]></cdata>
</doc>
XML;

$xml = new \rkphplib\XMLParser();
$xml->parse($xml_str);

print "\nInput:\n$xml_str\n";
print "Output:\n".$xml->toString()."\n";
print "Callback s:\n";
$xml->setCallback(null, [ 'doc/address' => 'xml_tag', 'doc/phone' => 'xml_tag' ]);
$xml->parse($xml_str);

