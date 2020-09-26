<?php

namespace test\log_debug;

function a() {
	\rkphplib\lib\log_debug('in a()');
}

class B {
	public function __construct() {
		$this->_x();
	}

	private function _x() {
		\rkphplib\lib\log_debug('in B._x()');
	}
}

\rkphplib\lib\log_debug('in main()');
a();
$b = new B();
