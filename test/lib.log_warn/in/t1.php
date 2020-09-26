<?php

namespace test\log_warn;

require_once PATH_RKPHPLIB.'lib/log_warn.php';

function a() {
	\rkphplib\lib\log_warn('error in a()');
}

class B {
	public function __construct() {
		$this->_x();
	}

	private function _x() {
		\rkphplib\lib\log_warn('error in B::_x()');
	}
}

$GLOBALS['SETTINGS']['LOG_WARN'] = dirname(__DIR__).'/out/t1.txt';

a();
$b = new B();

