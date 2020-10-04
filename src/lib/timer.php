<?php

namespace rkphplib\lib;

/**
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 *
 * @example print '10000 * md5(random) = '.timer_ssp('md5')."s\n";
 */


/**
 * Check performace of single string parameter function $func.
 * Call $loop (10000) times. Return elapsed time in seconds.
 */
function timer_ssp(string $func, int $loop = 10000) : int {
	$start = microtime(true);

	for ($i = 0; $i < $loop; $i++) {
		$func(random_bytes(mt_rand(5, 80)));
	}

	return microtime(true) - $start;
}

