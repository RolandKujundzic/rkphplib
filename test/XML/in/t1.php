<?php

$xml_str = <<<XML
<doc>
	<person firstname="John" middlename="Peter" lastname="Smith">John Peter Smith</person>
	<attrib-only k1="v1" k2="v2" />
	<age data-born="17.05.1990">30</age>
	<address data-test="test">
		<street>Some Street</street>
	</address>
	<utf8>&amp; äüöß</utf8>
	<cdata><![CDATA[... cdata example ...]]></cdata>
</doc>
XML;

$xml = new \rkphplib\XML($xml_str);
print "toMap: ".print_r(\rkphplib\XML::toMap($xml_str), true)."\n";
print $xml->get('doc.person').$xml->get('doc.person@no-such-attribute')."\n";
print $xml->get('doc.person@firstname').' '.$xml->get('doc.person@middlename').' '.$xml->get('doc.person@lastname')."\n";
print $xml->get('doc.age').' years old, born '.$xml->get('doc.age@data-born')."\n";
print $xml->get('doc.address.street').' in '.$xml->get('doc.address.city')."\n";

print_r($xml->get('doc'));

try {
	print $xml->get('doc.required', true);
}
catch (\Exception $e) {
	print "ignore missing doc.required\n";
}

