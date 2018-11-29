<?php

if (!empty($_REQUEST['do'])) {
	if ($_REQUEST['do'] == 'request') {
		print print_r($_REQUEST, true);
	}
	else if ($_REQUEST['do'] == 'header_request') {
		print print_r(getallheaders(), true);
		print print_r($_REQUEST, true);
	}
	else if ($_REQUEST['do'] == 'input') {
		print file_get_contents('php://input');
	}
	else if ($_REQUEST['do'] == 'header_input') {
		print print_r(getallheaders(), true);
		print file_get_contents('php://input');
	}
	else if ($_REQUEST['do'] == 'header_json') {
		print print_r(getallheaders(), true);
		print json_encode(json_decode(file_get_contents('php://input')));
	}

	exit(0);
}
