<?php

namespace rkphplib\code;

require_once dirname(__DIR__).'/File.class.php';

use rkphplib\Exception;
use rkphplib\File;


/**
 * Code parser.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2019 Roland Kujundzic
 */
class Parser {

// @var array $syntax
protected $syntax = [
	'name' => '',
	'sl_comment' => '',
	'ml_comment' => [],
	'first_line' => '',
	'alt_first_line' => '',
	'file_suffix' => ''
	];

// @var array $curr
protected $curr = [ 
	'line' => -1,
	'rx' => -1,
	'sl_comment' => -1,
	'ml_comment' => [],
	'doc' => []
	];

// @var array $env
protected $env = [
	'file' => null,
	'first_line' => 0,
	'line' => [], 
	'variable' => [],
	'doc' => [],
	'function' => [],
	'class' => [], 
	'namespace' => []
	];



/**
 * Load predefined syntax. Use syntax = [ 'name' => 'php|bash' ] for predefined syntax.
 */
public function __construct(array $syntax = []) {
	$default = [];

  $php_rx = [];
	$php_rx['namespace'] = '/^namespace ([a-zA-Z0-9_\\\]+)(\;| {)\s$/';
  $php_rx['include'] = '/^\s*(require|require_once|include|include_once) ([a-zA-Z0-9_\/\.\'\(\)\[\]\$\-\>]+)\;\s$/';
  $php_rx['use'] = '/^\s*(use function|use trait|use) ([a-zA-Z0-9_\\\]+);\s$/';
  $php_rx['trait'] = '/^\s*trait ([a-zA-Z0-9_]+) {/';
  $php_rx['interface'] = '/^\s*interface ([a-zA-Z0-9_]+) {/';
  $php_rx['class'] = '/^\s*(abstract )?class ([a-zA-Z0-9_]+) (extends [a-zA-Z0-9_\\\]+ )?(implements [a-zA-Z0-9_\,\\\ ]+ )?\{/';
  $php_rx['function'] = '/^\s*(abstract )?(public |protected |private )?(static )?function ([a-zA-Z0-9_&]+)\(/';

	$bash_rx = [];
	$bash_rx['function'] = '/^\s*function ([a-zA-Z0-9_]+) \{/';

	$default['bash'] = [
		'sl_comment' => '#',
		'ml_comment' => [ '#--', '#--', '#' ],
		'first_line' => '/^#\!\/bin\/b?a?sh\\r?\\n?$/',
		'file_suffix' => 'sh',
		'rx' => $bash_rx
		];

	$default['sh'] = $default['bash'];

	$default['php'] = [
		'sl_comment' => '//',
		'ml_comment' => [ '/*', '*/', '*' ],
		'first_line' => '<?php',
		'alt_first_line' => '/^#\!\/usr\/bin\/php[57]?\\r?\\n?$/',
		'file_suffix' => 'php',
		'rx' => $php_rx
		];

	if (!empty($syntax['name'])) {
		$this->syntax = array_merge($default[$syntax['name']], $syntax);
	}
}


/**
 * Throw error message.
 */
public function error(string $message) : void {
	$lnum = $this->curr['line'];
	$prefix = "\nERROR in ".basename($this->env['file']).' line '.$lnum.': ';
	throw new Exception($prefix.$message, $this->env['line'][$lnum - 1]);
}


/**
 * Return status hash (file, namespace, class, lpos and line_num).
 */
public function getStatus() : array {
	$status = $this->env;

	foreach ($this->curr as $key => $value) {
		$status['current.'.$key] = $value;
	}

	$status['line_num'] = count($status['line']);

	unset($status['line']);

	$arr = [ 'function', 'variable', 'class', 'namespace', 'trait', 'interface', 'current.ml_comment', 'current.doc', 'doc' ];

	$doc = [];
	foreach ($status['doc'] as $start_len) {
		array_push($doc, $start_len[0].':'.$start_len[1]);
	}
	$status['doc'] = $doc;

	foreach ($arr as $name) {
		if (!isset($status[$name])) {
			// ignore
		}
		else if (count($status[$name]) == 0) {
			unset($status[$name]);
		}
		else {
			$status[$name] = join(' ', array_values($status[$name]));
		}
	}

	return $status;
}


/**
 * Load $file. Check this.syntax.suffix (e.g. php)
 */
public function load(string $file) : void {
	$this->reset();
	$this->env['file'] = $file;
	$this->env['line'] = File::loadLines($file);

	if (!empty($this->syntax['file_suffix'])) {
		$suffix = $this->syntax['file_suffix'];
		$slen = mb_strlen($suffix) + 1;

		if (mb_strtolower(mb_substr($file, -1 * $slen)) != '.'.$suffix) {
			throw new Exception("invalid file suffix (.$suffix expected)", $file);
		}
	}

	if (!empty($this->syntax['first_line'])) {
		$fl_curr = trim($this->env['line'][0]);
		$fl_ok = trim($this->syntax['first_line']);

		if (!empty($this->syntax['alt_first_line']) && !$this->match($fl_curr, $fl_ok)) {
			$fl_alt = trim($this->syntax['alt_first_line']);
			
			if (!$this->match($fl_curr, $fl_alt)) {
				throw new Exception("invalid first line ([$fl_ok] or [$fl_alt] expected)", $fl_curr);
			}

			$fl_curr = trim($this->env['line'][1]);
			$this->env['first_line'] = 2;
		}
		else if (!$this->match($fl_curr, $fl_ok)) {
			throw new Exception("invalid first line ([$fl_ok] expected)", $fl_curr);
		}
		else {
			$this->env['first_line'] = 1;
		}
	}
}


/**
 * If $rx is '/..../' applay preg_match otherwise use trim($txt) === trim($rx) comparision.
 */
public function match($txt, $rx) {
	$res = false;

	if ($rx[0] == '/' && $rx[-1] == '/') {
		$res = preg_match($rx, $txt);
	}
	else {
		$res = trim($txt) === trim($rx);
	}

	return $res;
}


/**
 * Reset this.curr and this.env.
 */
protected function reset() : void {
	$this->curr = [
		'line' => -1,
		'rx' => -1,
		'sl_comment' => -1,
		'ml_comment' => [],
		'doc' => []
	];

	$this->env = [
		'file' => null,
		'line' => [],
		'variable' => [],
		'doc' => [],
		'function' => [],
		'class' => [],
		'namespace' => []
	];
}


/**
 * Scan file.
 */
public function scan(string $file) : void {
	$this->load($file);

	$sl_comment = false;
	$ml_comment = [];  // [ syntax.ml_comment.start, first comment line, number of lines ]

	for ($k = $this->env['first_line']; $k < count($this->env['line']); $k++) {
		if ($sl_comment) {
			$this->curr['sl_comment'] = $k - 1;
		}

		$line = $this->env['line'][$k];
		$l3 = substr(trim($line), 0, 3); 
		$sl_comment = false;
		$this->curr['line'] = $k + 1;		// line counter is 1, 2, 3, ...

		// python has two variants of multiline comments: """ and '''
		if (!empty($this->syntax['ml_comment'][0]) && mb_strpos($l3, $this->syntax['ml_comment'][0]) === 0) {
			$ml_comment = [ 0, $k ];
		}
		else if (!empty($this->syntax['ml_comment'][3]) && mb_strpos($l3, $this->syntax['ml_comment'][3]) === 0) {
			$ml_comment = [ 3, $k ];
		}

		if (count($ml_comment) == 2) {
			$mlc_end = $this->syntax['ml_comment'][$ml_comment[0] + 1];

			if (mb_strpos($l3, $mlc_end) === 0) {
				if (trim($line) != $l3) {
					$this->error('invalid multiline comment end '.$mlc_end);
				}

				array_push($ml_comment, $k - $ml_comment[1] + 1);
				$this->curr['ml_comment'] = $ml_comment;
				$this->curr['doc'] = [];
				$ml_comment = [];
			}
			else if (mb_strpos($line, $mlc_end) !== false) {
				$this->error('unexpected '.$mlc_end);
			}
			else if (!empty($this->syntax['ml_comment'][$ml_comment[0] + 2]) && mb_strpos($l3, $this->syntax['ml_comment'][$ml_comment[0] + 2]) === 0) {
				if (count($this->curr['doc']) > 0 && $this->curr['doc'][0] < $ml_comment[1] + 1) {
					for ($j = 0; $j < count($this->curr['doc']); $j++) {
						if ($this->curr['doc'][0] + $j != $this->curr['doc'][$j]) {
							$this->error('unexpected doc comment');
						}					
					}

					array_push($this->env['doc'], [ $this->curr['doc'][0], count($this->curr['doc']) ]);
					$this->curr['doc'] = [];
				}

				array_push($this->curr['doc'], $k);
			}
		}
		else if (mb_strpos($l3, $this->syntax['sl_comment']) === 0) {
			$sl_comment = true;
		}

		foreach ($this->syntax['rx'] as $rx_name => $rx) {
			// ToDo ...
		}
	}

	if (count($this->curr['doc']) > 0) {
		array_push($this->env['doc'], [ $this->curr['doc'][0], count($this->curr['doc']) ]);
		$this->curr['doc'] = [];
	}
}


}

