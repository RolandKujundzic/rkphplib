<?php

$xml_str = <<<XML
<doc>
	<person firstname="John" middlename="Peter" lastname="Smith">John Peter Smith</person>
	<attrib-only k1="v1" k2="v2" />
</doc>
XML;

$xml = new \rkphplib\XML($xml_str);
print "toMap: ".print_r(\rkphplib\XML::toMap($xml_str), true)."\n";
print "toMap(keep_root): ".print_r(\rkphplib\XML::toMap($xml_str, true), true)."\n";
print "toArray: ".print_r($xml->toArray(), true)."\n";

