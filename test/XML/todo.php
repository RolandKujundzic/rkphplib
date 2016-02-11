<?php

require_once(dirname(dirname(__DIR__)).'/src/XML.class.php');

use rkphplib\XML;

$xml = <<<END
<?xml version="1.0" encoding="UTF-8"?>
<json2xml>
	<names>a</names>
	<names>b</names>
	<names>c</names>
	<id>a</id>
	<id>b</id>
	<id>c</id>
</json2xml>
END;

$xml2 = <<<END
<?xml version="1.0" encoding="UTF-8"?>
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

print XML::fromJSON(XML::toJSON($xml))."\n\n";
print XML::fromJSON(XML::toJSON($xml2))."\n\n";

print XML::fromJSON('Hallo')."\n";
print XML::fromJSON(['Hallo' => '', 'Welt' => ''])."\n";
print XML::fromJSON(['Liste' => ['Roland', 'Peter', 'Mario'], 'Liste2' => ['A', 'B', 'C']])."\n";

