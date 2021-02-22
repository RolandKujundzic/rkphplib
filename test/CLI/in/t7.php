<?php

function confirm(string $default) : void {
	if (\rkphplib\CLI::confirm('Confirm '.$default, $default)) {
		print "Confirm $default = true\n";
	}
	else {
		print "Confirm $default = false\n";
	}
}


\rkphplib\CLI::$autoconfirm = true;
confirm('y');
confirm('n');

