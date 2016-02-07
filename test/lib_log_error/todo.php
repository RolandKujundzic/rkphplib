<?php

require_once(dirname(dirname(__DIR__)).'/src/lib/log_error.php');

function a() { rkphplib\lib\log_error('error in a()'); }

class B {
	public function __construct() { $this->_x(); }
	private function _x() { rkphplib\lib\log_error('error in B::_x()'); }
}

a();
$b = new B();

