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

$xml = new \rkphplib\XML($xml_str);
print_r($xml->get('doc'));

try {
	print $xml->get('doc.required', true);
}
catch (\Exception $e) {
	print "ignore missing doc.required\n";
}


