<?php

$xml_str = <<<XML
<doc>
	before_Sub1
	<sub>Sub1
		before Sub2
		<sub>Sub2 äüö Sub2 ßÖÄÜ Sub2</sub>
		after Sub2
	</sub>
	after Sub1
</doc>
XML;

$xml = new \rkphplib\XMLParser();
$xml->parse($xml_str);

print "\nInput:\n$xml_str\n";
print "Output:\n".$xml->toString()."\n";
print "Callback s:\n";
$xml->setCallback(null, [ 'doc/sub/sub' => 'xml_tag' ]);
$xml->parse($xml_str);

