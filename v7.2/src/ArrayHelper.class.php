<?php

namespace rkphplib;


/**
 * Array manipulation.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class ArrayHelper {

/**
 * Normalize nested array into array.
 *
 * @example self::normalize([ [ [ a, b ], c ], d ]) = [ a, b, c, d ]
 */
public static function normalize(array &$arr) : void {
	for ($i = 0; $i < count($arr); $i++) {
		if (is_array($arr[$i])) {
			$tmp = $arr[$i];
			$arr[$i] = array_shift($tmp);
			array_splice($arr, $i + 1, 0, $tmp);
			$i--;
		}
	}
}


/**
 * Return all permutations of $x.
 * @see Example 4-7 from O'Reilly PHP Cookbook
 */
public static function permutations(array $x) : array {
	$size = count($x) - 1; 

	if ($size == -1) {
		return $x;
	}

	$perm = range(0, $size); // permutate [0 ... $size]
	$perms = [];
	$j = 0;

	do {
		// map permutations [0 ... $size ] to $x
		foreach ($perm as $i) {
			$perms[$j][] = $x[$i]; 
		}

		$j++;
	} while (null !== ($perm = self::nextPermutation($perm, $size))); 

	return $perms;
}


/**
 * Return next permutation or null if finished. Paramter $size = count($p) - 1.
 * @see Example 4-7 from O'Reilly PHP Cookbook
 */
private static function nextPermutation(array $p, int $size) : ?array {
	// slide down the array looking for where we're smaller than the next guy
	for ($i = $size - 1; $i >= 0 && $p[$i] >= $p[$i+1]; --$i) {
	} 

	// if not found we have reversed the array and are done: (1, 2 ... n) => (n, ... 2, 1)
	if ($i == -1) {
		return null;
	}

	// slide down the array looking for number > p[i]
	for ($j = $size; $p[$j] <= $p[$i]; --$j) {
	} 

	// swap them
	$tmp = $p[$i]; $p[$i] = $p[$j]; $p[$j] = $tmp; 

	// now reverse the elements in between by swapping the ends 
	for (++$i, $j = $size; $i < $j; ++$i, --$j) {
		$tmp = $p[$i]; $p[$i] = $p[$j]; $p[$j] = $tmp; 
	}

	return $p; 
}

 
}

