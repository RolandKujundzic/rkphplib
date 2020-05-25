<?php

$xml_str  = '<?xml version="1.0" encoding="UTF-8"?>';
$xml_str .= <<<END
<json2xml>
  <Liste>
    <Liste>Roland</Liste>
    <Liste>Peter</Liste>
    <Liste>Mario</Liste>
  </Liste>
  <Liste2>
    <Liste2>A</Liste2>
    <Liste2>B</Liste2>
    <Liste2>C</Liste2>
  </Liste2>
</json2xml>
END;

$doc = \rkphplib\XML::fromMap(\rkphplib\XML::toMap($xml_str));
print "doc:\n----\n$doc\n----\n";

$xml = new \rkphplib\XML($doc);
print "\nprint_r:\n----\n".print_r($xml->toArray(), true)."\n----\n\nxml:\n----\n".$xml."\n";

