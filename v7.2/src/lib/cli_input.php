<?php

namespace rkphplib\lib;

require_once dirname(__DIR__).'/Exception.class.php';

use rkphplib\Exception;

/**
 * Parse _$SERVER[argv]. Detect --name[=value, | name], -n[ value] and name=value.
 * Use key @file=path/to/file to parse file into result (suffix .ser|.conf|.json).
 * Use @file to json file and @json to load json string.
 *
 * @example run.php --name value --k2 v2
 * {"":["run.php","value","v2"],"name":"@1","k2":"@2"}
 * @example run.php -k -n abc
 * {"":["run.php","abc"],"k":"","n":"@1"}
 * @example run.php -uvw xyz
 * {"":["run.php","xyz"],"u":"","v":"","w":""}
 * @example run.php --key v1 --key v2 -a -b x -b y
 * {"":["run.php","v1","v2","x","y"],"key":["@1","@2"],"a":"","b":["@3","@4"]}
 * @example run.php key=value -f -g arg
 * {"":["run.php","arg"],"key":"value","f":"","g":"@1"}
 * @example run.php '@file:test=test.json' # test.json: {"hash":{"a":"aa","b":"bbb"},"list":["a","b"]}
 * {"":"run.php","test":{"hash":{"a":"aa","b":"bbb"},"list":["a","b"]}}
 * @example run.php '@json:={"k1":"v1","k2":["a","b"]}'
 * {"":"run.php","k1":"v1","k2":["a","b"]}
 */
function cli_input() : ?array {
	if (php_sapi_name() !== 'cli') {
		return false;
	}

	$last_key = '';
	$res = [];

	for ($i = 0; $i < count($_SERVER['argv']); $i++) {
		$key = '';
		$value = $_SERVER['argv'][$i];
	
		if ($value[0] == '@' && ($pos = mb_strpos($value, '=')) !== false) {
			if (mb_substr($value, 0, 6) == '@file:') {
				$key = mb_substr($value, 6, $pos - 6);
				$value = json_decode(file_get_contents(mb_substr($value, $pos + 1)), true);
			}
			else if (mb_substr($value, 0, 6) == '@json:') {
				$key = mb_substr($value, 6, $pos - 6);
				$value = json_decode(mb_substr($value, $pos + 1), true);
			}

			if (empty($key)) {
				$res = array_merge($res, $value);
			}
			else {
				$res[$key] = $value;
			}

			$last_key = $key;
			continue;
		}
		else if ($value[0] == '-' && $value[1] == '-') {
			if (($pos = mb_strpos($value, '=')) !== false) {
				$key = mb_substr($value, 2, $pos - 2);
				$value = mb_substr($value, $pos + 1);
			}
			else {
				$key = mb_substr($value, 2);
				$value = (isset($res['']) && is_array($res[''])) ? '@'.count($res['']) : '@1'; 
			}
		}
		else if ($value[0] == '-') {
			$plen = strlen($value);

			if ($plen == 2) {
				$key = $value[1];
				$value = (isset($res['']) && is_array($res[''])) ? '@'.count($res['']) : '@1';
			}
			else {
				for ($k = 1; $k < $plen; $k++) {
					$key = $value[$k];
					if (ord($key) < 97 || ord($key) > 122) {
						throw new Exception('invalid flag '.$key, $value);
					}

					$res[$key] = '';
				}

				$last_key = $key;
				continue;
			}
		}
		else if (preg_match('/^([a-zA-Z0-9_\.\:\[\]]+)=(.+)$/', $value, $match)) {
			$key = $match[1];
			$value = $match[2];
		}

		if ($value != $_SERVER['argv'][$i] && !empty($last_key) && isset($res[$last_key]) && $res[$last_key][0] == '@') {
			$res[$last_key] = '';
		}

		$last_key = $key;
 
		if (isset($res[$key])) {
			if (!is_array($res[$key])) {
				$res[$key] = [ $res[$key] ];
			}

			array_push($res[$key], $value);
		}
		else {
			$res[$key] = $value;
		}
	}
 
	\rkphplib\lib\log_debug("cli_input:115> _SERVER[argv]: ".print_r($_SERVER['argv'], true)."\nresult: ".print_r($res, true));
	return $res;
}


/**
 * Run cli_input and export output into $_REQUESTE (prefix req:) and $_SERVER (prefix srv|server|http:).
 *
 * @example run2.php --req:name value --req:list=v1 req:list=v2
 * REQUEST={"name":"value","list":["v1","v2"]}
 * SERVER={}
 *
 * @example run2.php req:a=x --req:b http:host=domain.tld server:addr=1.2.3.4 --srv:request_method post
 * REQUEST={"a":"x","b":1}
 * SERVER={"HTTP_HOST":"domain.tld","SERVER_ADDR":"1.2.3.4","REQUEST_METHOD":"post"}
 */
function cli_http_input() : void {
	if (php_sapi_name() !== 'cli') {
		return;
	}

	$data = cli_input();

	foreach ($data as $key => $value) {
		if (empty($key)) {
			continue;
		}

		if (empty($value)) {
			$value = 1;
		}
		else if ($value[0] == '@') {
			$n = intval(substr($value, 1));
			$value = $data[''][$n];
		}

		$value_str = is_array($value) ? print_r($value, true) : $value;

		if (substr($key, 0, 4) == 'req:') {
			$key = substr($key, 4);
			\rkphplib\lib\log_debug("cli_http_input:155> set _REQUEST[$key]=[$value_str]");
			$_REQUEST[$key] = $value;
		}
		else {
			if (substr($key, 0, 4) == 'srv:') {
				$key = strtoupper(substr($key, 4));
			}
			else if (substr($key, 0, 5) == 'http:') {
				$key = 'HTTP_'.strtoupper(substr($key, 5));
			}
			else if (substr($key, 0, 7) == 'server:') {
				$key = 'SERVER_'.strtoupper(substr($key, 7));
			}

			if (!isset($_SERVER[$key])) {
				\rkphplib\lib\log_debug("cli_http_input:170> set _SERVER[$key]=[$value_str]");
				$_SERVER[$key] = $value;
			}
		}
	}
}

