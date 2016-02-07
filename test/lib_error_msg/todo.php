<?php

require_once(dirname(dirname(__DIR__)).'/src/lib/error_msg.php');

function a() { rkphplib\lib\error_msg('error_a'); }

class B {
	public function __construct() { $this->_x(); }
	private function _x() { rkphplib\lib\error_msg('error_B::_x()'); }
}

a();
$b = new B();

