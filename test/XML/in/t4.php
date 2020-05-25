<?php


// for some reason global $xml is null ???? - use $get instead
// function get(string $path) : void {
//	 global $xml;
//   ... 
// }

$xml = new \rkphplib\XML();
$get = function (string $path) use ($xml) : void {
	$res = $xml->get($path);
	print "$path: [";
	if (is_array($res)) {
		print join(', ', $res)."]\n";
	}
	else {
		print $res."]\n";
	}
};


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

print "$xml_str\n";
$xml->load($xml_str);

$get('doc.person');
$get('doc.person@no-such-attribute');
$get('doc.person@firstname');
$get('doc.person@middlename');
$get('doc.person@lastname');
$get('doc.age');
$get('doc.age@data-born');
$get('doc.address.street');
$get('doc.address.city');
$get('doc.phone');
$get('doc.utf8');
$get('doc.cdata');

