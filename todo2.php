<?php

require_once 'src/XMLParser.php';

class XMLCallback {
	function addCategory($tag, $text, $attrib, $path) {
		print "addCategory: $tag = [$text] ($path)\n";
	}
}


$xml = '
<BMECAT>
  <T_NEW_CATALOG>
    <CATALOG_GROUP_SYSTEM>
      <CATALOG_STRUCTURE type="node">
        <GROUP_ID>13</GROUP_ID>
        <GROUP_NAME>Hand-/Armschutz</GROUP_NAME>
        <PARENT_ID>1</PARENT_ID>
        <GROUP_ORDER>999999</GROUP_ORDER>
      </CATALOG_STRUCTURE>
      <CATALOG_STRUCTURE type="node">
        <GROUP_ID>14</GROUP_ID>
        <GROUP_NAME>Fußschutz</GROUP_NAME>
        <PARENT_ID>1</PARENT_ID>
        <GROUP_ORDER>999999</GROUP_ORDER>
      </CATALOG_STRUCTURE>
    </CATALOG_GROUP_SYSTEM>
  </T_NEW_CATALOG>
</BMECAT>
';

// Fix free data and _data_pos in XMLCallback if callback mode

$xmlCallback = new XMLCallback();

$parser = new \rkphplib\XMLParser();
$parser->setCallback($xmlCallback, [ 'bmecat/t_new_catalog/catalog_group_system/catalog_structure' => 'addCategory' ]);
$parser->parse($xml);
print "toString: ".$parser->toString()."\n";
