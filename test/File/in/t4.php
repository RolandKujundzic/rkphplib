<?php

class Parser {

public function row(array $row) : void {
	print 'Parser.row: ['.join('|', $row)."]\n";
}

}

function parseRow(array $row) : void {
	print 'parseRow: ['.join('|', $row)."]\n";
}

$csv = \rkphplib\File::loadCSV('in/t4.csv', '\\t');
print_r($csv);

\rkphplib\File::loadCSV('in/t4.csv', "\t", [ 'callback' => 'parseRow' ]);

$parser = new Parser();
\rkphplib\File::loadCSV('in/t4.csv', '\\t', [ 'quote' => '"', 'callback' => array($parser, 'row') ]);

