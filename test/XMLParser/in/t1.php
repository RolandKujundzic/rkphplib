<?php

$xml_str = <<<XML
<doc
	language="en">
	<t>t1</t>
	<br/>
	<t>t2</t>
	<br/>
	<s>
		<sa>sa</sa>
		<sb x="xx" t="true" />
		sval
	</s>
</doc>
XML;

$xml = new \rkphplib\XMLParser();
$xml->parse($xml_str);

print "Input:\n$xml_str\n";
print "Output:\n".$xml->toString()."\n";
print "Callback s:\n";
$xml->setCallback(null, [ 'doc/t' => 'xml_tag', 'doc/s' => 'xml_tag' ]);
$xml->parse($xml_str);

