<?php

$xml = <<<XML
<ARTICLE>
        <FEATURE>
          <FNAME>Pflegehinweise 5</FNAME>
          <FVALUE>Reinigen mit Perchlorethylen</FVALUE>
          <FUNIT></FUNIT>
          <FORDER>30</FORDER>
          <FDESCR>Pflegehinweise 5</FDESCR>
          <FVALUE_DETAILS>Reinigen mit Perchlorethylen</FVALUE_DETAILS>
        </FEATURE>
        <FEATURE>
          <FNAME>VE</FNAME>
          <FVALUE>10</FVALUE>
          <FUNIT></FUNIT>
          <FORDER>900</FORDER>
          <FDESCR></FDESCR>
          <FVALUE_DETAILS></FVALUE_DETAILS>
        </FEATURE>
</ARTICLE>
XML;

print "\nInput:\n$xml\n";
$parser = new \rkphplib\XMLParser();
$parser->setCallback($parser, [ 'article/feature' => 'printTags' ]);
print "\nprintTags:\n";
$parser->parse($xml);
print "\nscan:\n";
print_r($parser->scan($xml));

