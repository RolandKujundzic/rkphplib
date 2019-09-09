<?php

require_once(dirname(dirname(__DIR__)).'/src/lib/translate.php');

function a() {
	\rkphplib\lib\translate('error_a');
}

class B {
	public function __construct() {
		$this->_x();
	}

	private function _x() {
		rkphplib\lib\translate('error_B::_x()');
	}
}

a();
$b = new B();

