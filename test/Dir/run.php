<?php

require_once(dirname(__DIR__).'/testlib.php');
require_once(dirname(dirname(__DIR__)).'/src/Dir.class.php');


/**
 *
 */
function test_exists($path_list) {
	$res = array();

	foreach ($path_list as $path) {
		try {
			if (\rkphplib\Dir::exists($path, true)) {
				array_push($res, 1);
			}
		}
		catch (Exception $e) {
			array_push($res, 0);
		}
	}

	return $res;
}


call_test('test_exists', array('/etc', '/etc/apache2', '/var/lib/mysql'), array('Dir::exits()', 1, 1, 0));

