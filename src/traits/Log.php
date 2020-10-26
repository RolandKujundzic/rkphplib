<?php

namespace rkphplib\traits;


/**
 * Trait for this.log()
 * 
 * @code …
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
 * @EOL
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
 * - 2: set log_conf value
 * - 4: print in same line
 * - 8: add total time to prefix (e.g. PREFIX (28 h|min|s|ms)> ...) 
 * - 16: add time since last log
 *
 * Configuration values:
 * - to: default 'to:php://STDOUT'
 * - prefix: @example log('prefix:run', 2); // long_conf[prefix] = 'run> '
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

	if (!isset($this->log_conf['_since'])) {
		$this->log_conf['_since'] = microtime(true);
	}

	$prefix = empty($this->log_conf['prefix']) ? '' : $this->log_conf['prefix'];
	$elapsed = 0;

	if ($flag & 8) {
		if (empty($this->log_conf['_since'])) {
			throw new \Exception('log start was not initialized');
		}

		$elapsed = microtime(true) - $this->log_conf['_since'];
	}
	else if ($flag & 16) {
		$elapsed = microtime(true) - $this->log_conf['_lchange'];
	}

	if ($elapsed) {
		if ($elapsed > 3600) {
			$prefix .= ' ('.round($elapsed / 3600, 2).' h)';
		}
		else if ($elapsed > 60) {
			$prefix .= ' ('.round($elapsed / 60, 2).' min)';
		}
		else if ($elapsed > 1) {
			$prefix .= ' ('.round($elapsed, 2).' s)';
		}
		else {
			$prefix .= ' ('.round($elapsed * 1000, 2).' ms)';
		}
	}

	if (!empty($prefix)) {
		$msg = $prefix.'> '.$msg;
	}

	if ($flag & 4) {
		$msg .= "\033[0K\r";
	}
	else if (isset($this->log_conf['_last_flag']) && $this->log_conf['_last_flag'] & 4) {
		$msg = "\n".$msg;
	}

	if ($flag & 1) {
		$msg .= "\n";
	}

	error_log($msg, 3, $this->log_conf['to']);
	$this->log_conf['_last_flag'] = $flag;

	if (0 == ($flag & 4) && 0 == ($flag & 2)) {
		$this->log_conf['_lchange'] = microtime(true);
	}
}


}

