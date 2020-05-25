<?php 

$xml = <<<XML
<a lang="de">
	<b id="foo">
		<c id="bar"/>
		text
	</b>
	<ns:v xmlns:ns="http://www.w3.org/TR/html4/">
		val
	</ns:v>
</a>
XML;

print_r(\rkphplib\XML::string2array($xml, true));

