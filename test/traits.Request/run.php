<?php

require_once '../settings.php';
require_once PATH_RKPHPLIB.'traits/Request.php';

class RTest {
use \rkphplib\traits\Request;

public function __construct(array $p) {
	$this->setPConf($p);
}

public function get(string $name) {
	return $this->getPConf($name);
}

}

global $th;

$th->run(1, 1);

