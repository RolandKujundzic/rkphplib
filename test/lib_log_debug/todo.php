<?php

require_once(dirname(dirname(__DIR__)).'/src/lib/log_debug.php');

function a() {
	\rkphplib\lib\log_debug('debug 1');
}

class B {
	public function __construct() {
		$this->_x();
	}

	private function _x() {
		\rkphplib\lib\log_debug('debug 2');
	}
}

a();
$b = new B();

