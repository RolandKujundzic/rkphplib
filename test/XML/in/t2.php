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
print "\nNamespace example:\n$xml_str\n";
print "toMap (keep root): ".print_r(\rkphplib\XML::toMap($xml_str, true), true)."\n";

