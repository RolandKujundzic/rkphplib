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
print "Simple example:\n$xml_str\n";
print "toMap: ".print_r(\rkphplib\XML::toMap($xml_str), true)."\n";

