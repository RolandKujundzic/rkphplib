<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.class.php');
  $th = new rkphplib\TestHelper();
}


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


$th->load('src/Dir.class.php');
$th->callTest('test_exists', array('/etc', '/etc/apache2', '/var/lib/mysql'), array('Dir::exits()', 1, 1, 0));

