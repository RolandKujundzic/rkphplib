<?php

require_once '../settings.php';
require_once PATH_SRC.'traits/Number.php';

class TraitTest {
use \rkphplib\traits\Number;

public function r(string $number, int $dp = 0) : void {
	print $this->round($number, $dp)."\n";
}

public function pf(string $number, int $dp = 0) : void {
	print $this->parseFloat($number, $dp)."\n";
}

public function fn(string $number) : void {
	print $this->fixNumber($number)."\n";
}

}


global $th;

$th->run(1, 1);

