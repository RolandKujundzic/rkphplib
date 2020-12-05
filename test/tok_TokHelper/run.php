<?php

global $th;

if (!isset($th)) {
  require_once(dirname(dirname(__DIR__)).'/src/TestHelper.php');
  $th = new rkphplib\TestHelper();
}

$th->load('src/tok/TokHelper.trait.php');
$th->load('src/File.class.php');


/**
 *
 */
class MapTest {
	use \rkphplib\tok\TokHelper;

	private $map = null;
	private $result = null;

	public function useMap($map) {
		$this->result = [];
		$this->map = $map;
		return $this;
	}

	public function getResult() {
		return $this->result;
	}

	public function call($key) {
		$value = $this->getMapKeys($key, $this->map);
		array_push($this->result, [ $key, $value ]);
		return $this;
	}

	public function cmp($key, $result) {
		$value = $this->getMapKeys($key, $this->map);
	
		$value_out = print_r($value, true);
		$result_out = print_r($result, true);

		if ($value_out != $result_out) {
			throw new Exception("ERROR: $key: $value_out != $result_out");
		}
		else {
			print " . ";
		}

		return $this;
	}

	public function ok() {
		print "ok\n";
	}
}


/*
 * M A I N
 */

$a = [
	'm' => 7,
	'v' => 8,
	'A' => [
		'X' => [
			'm' => 17,
			'n' => 18,
			],
		'Y' => 9,
		'Z' => [
			'm' => 27
			]
		]
	];

$b = [
	'm' => 7,
	'v' => 8,
	'A.X.m' => 17,
	'A.X.n' => 18,
	'A.Y' => 9,
	'A.Z.m' => 27,
	];


$test = new MapTest();

// doc example
/*
$x = [ 'a' => 7, 'a.b.0' => 18, 'a.b.1' => 19, 'c' => [ '1' => 5, '2' => 6 ], 'd' => [ 'x' => 3, 'y' => 4 ] ];
$test->useMap($x)->cmp('a', 7)->cmp('a.b', [ 18, 19 ])->cmp('c', [ 1 => 5, 2 => 6 ])->cmp('c.1', 5)->cmp('d.x', 3)
	->cmp('d', [ 'x' => 3, 'y' => 4 ])->ok();
*/

$a_out = $test->useMap($a)->call('v')->call('A.Y')->call('A.X.m')->call('A.X.n')->call('A.Z.m')->getResult();
$b_out = $test->useMap($b)->call('v')->call('A.Y')->call('A.X.m')->call('A.X.n')->call('A.Z.m')->getResult();
$th->compare('string result: ', [ $a_out ], [ $b_out ]);

$a_out = $test->useMap($a)->call('h')->call('A.t')->getResult();
$b_out = $test->useMap($b)->call('h')->call('A.t')->getResult();
$th->compare('no result: ', [ $a_out ], [ $b_out ]);

$a_out = $test->useMap($a, 'A')->call('A')->call('A.X')->call('A.Y')->call('A.Z')->getResult();
// \rkphplib\File::serialize('t3.ok', $a_out);
$a_ok = \rkphplib\File::unserialize('t3.ok');
$th->compare('submap a result: ', [ $a_out ], [ $a_ok ]);

$b_out = $test->useMap($b, 'A')->call('A')->call('A.X')->call('A.Y')->call('A.Z')->getResult();
// \rkphplib\File::serialize('t4.ok', $b_out);
$b_ok = \rkphplib\File::unserialize('t4.ok');
$th->compare('submap b result: ', [ $b_out ], [ $b_ok ]);

