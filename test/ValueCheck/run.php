<?php

require_once dirname(dirname(__DIR__)).'/src/ValueCheck.class.php';


/**
 * Execute comparsing, log and return result (0|1). Use check('result', 'message') for result output.
 * Compare result sequence (1,0, ...) with global $expect vector.
 */
function check(string $func, string $domain = '', int $lmin = 0, int $lmax = 0) : int {
	static $found = [];
	global $expect;

	$res = 0;

	if ('result' == $func) {
		$str_expect = join('|', $expect);
		$str_found = join('|', $found);

		if ($expect == $found) {
			print "\nRESULT $domain: ".count($found)." Tests OK\n\n";
			$found = [];
			return 1;
		}
		else {
			print "\nRESULT $domain: ERROR\n".join('|', $expect)."\n".join('|', $found)."\n\n";
			exit(1);
		}
	}

	if ('isDomain' == $func) {
		$res = (int) rkphplib\ValueCheck::isDomain($domain, $lmin, $lmax);
	}
	else {
		$res = (int) rkphplib\ValueCheck::$func($domain);
	}

	array_push($found, $res);

	return $res;
}


// @see http://newgtlds.icann.org/en/program-status/delegated-strings tld list
$list = [ 'täst.de' => 2, 'gülle.Müller.de' => 3, '-aaa.de' => 0, 'aaa-.de' => 0, '_x.de' => 0, 'aa.bb.cc.de' => 4, 
	'a.b.c.d.de' => 5, 'a.b.c.d.e.f.g.h.de' => 9, 'g.co' => 2, 'x.com' => 2, 'nic.谷歌' => 2, '♡.com' => 2, 
	'xn--stackoverflow.com' => 2, 'stackoverflow.xn--com' => 2, 'stackoverflow.co.uk' => 3,
	'oil.ارامكو' => 2, 'tckwe.コム' => 2, 'fhbei.كوم' => 2, 'c2br7g.नेट' => 2, '9dbq2a.קום' => 2, 
	'3pxu8k.点看' => 2, 't60b56a.닷넷' => 2, 'j1aef.ком' => 2, 'pssy2u.大拿' => 2, '42c2d9a.คอม' => 2, 
	'mk1bu44c.닷컴' => 2 ];

foreach ($list as $domain => $level) {
	if (($check = check('isDomain', $domain, $level, $level))) {
		if (!empty($_REQUEST['xn--'.$domain])) {
			print "$domain (".$_REQUEST['xn--'.$domain].") is valid\n";
		}
		else {
			print "$domain is valid\n";
		}
	}
	else {
		if ($level > 0) {
			print "ERROR: $domain check (level = $level)\n";
		}
		else {
			print "$domain is invalid\n";
		}
	}
}

$expect = [ 1, 1, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1 ];
check('result', 'isDomain');

$list = [ 'a.de' => 'isURL', 'sub.domain.de' => 'isURL', 'abc' => 'isURLPrefix', 
	'domain.tld/path/to/script.php' => 'isURLPath', 
	'domain.tld/path/to/script.php?a=5' => 'isURLPath' ];

foreach ($list as $domain => $func) {
	print "rkphplib\ValueCheck::$func($domain) = ".check($func, $domain)."\n";
}

$expect = [ 1, 1, 1, 1, 0 ];
check('result', 'isURL, isURLPrefix, isURLPath');
