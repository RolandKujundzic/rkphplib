<?php

require_once '../settings.php';


global $th;

$GLOBALS['SETTINGS']['LOG_DEBUG'] = __DIR__.'/out/t1.txt';
$th->run(1,1);

function a() {
	\rkphplib\lib\log_error('error in a()');
}

class B {
	public function __construct() {
		$this->_x();
	}

	private function _x() {
		rkphplib\lib\log_error('error in B::_x()');
	}
}

a();
$b = new B();

