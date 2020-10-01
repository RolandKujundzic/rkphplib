<?php

namespace rkphplib\traits;


/**
 * Trait for this.log()
 * 
 * @code:
 * require_once(PATH_RKPHPLIB.'traits/Log.php');
 *
 * class SomeClass {
 * use \rkphplib\traits\Log;
 *
 * public function __construct() {
 *		$this->log('prefix:SomeClass', 2)
 * }
 *
 * public function example() {
 *    $this->log($message);
 * }
 * @:
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 */
trait Log {

// @var array $log_conf [ prefix => '', 'to' => php://STDOUT ]
private $log_conf = [ 'prefix' => '', 'to' => 'php://STDOUT' ];


/**
 * Print log message. Flags:
 * - 1: append \n
 * - 2: set log_conf value @example log('prefix:run', 2); // long_conf[prefix] = 'run> '
 */
private function log(string $msg, int $flag = 1) : void {
	if ($flag & 2) {
		if (!preg_match('/^(prefix|to):.+/', $msg)) {
			throw new \Exception("invalid log('$msg') (use prefix:)");
		}

		list ($key, $value) = explode(':', $msg, 2);
		$this->log_conf[$key] = $value;
		return;
	}

	$prefix = empty($this->log_conf['prefix']) ? '' : $this->log_conf['prefix'].'> ';
	$msg = $prefix.$msg;

	if ($flag & 4) {
		$msg .= "\033[0K\r";
	}
	else if ($flag & 1) {
		$msg .= "\n";
	}

	error_log($msg, 3, $this->log_conf['to']);
}


}

