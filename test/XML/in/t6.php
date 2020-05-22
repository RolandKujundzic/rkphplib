<?php

function showDoc(\DomDocument $doc) : void {
	print "doc:\n----\n".print_r($doc, true)."\n----\n\nxml:\n----\n".$doc."\n----\n\n";
}

showDoc(\rkphplib\XML::fromMap('Hallo'));
showDoc(\rkphplib\XML::fromMap(['Hallo' => '', 'Welt' => '']));
showDoc(\rkphplib\XML::fromMap(['Liste' => ['Roland', 'Peter', 'Mario'], 'Liste2' => ['A', 'B', 'C']]));

