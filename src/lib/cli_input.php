<?php

namespace rkphplib\lib;


/**
 * Execute only once (define SETTINGS_CLI_INPUT) in cli mode.
 * Export $_SERVER[argv] input into $_REQUEST and $_SERVER. 
 * Export --req:name=value or --req:list[]=value1 as $_REQUEST[name]=value (do not overwrite already set keys).
 * Export --http:name as $_SERVER[HTTP_NAME] (e.g. --http:host = $_SERVER[HTTP_HOST]).
 * Export --server:name as $_SERVER[SERVER_NAME] (e.g. --server:addr = $_SERVER[SERVER_ADDR]).
 * Export --srv:name= value as $_SERVER[NAME]=value (e.g. --srv:request_method = $_SERVER[REQUEST_METHOD]).
 */
function cli_input() : void {
	if (php_sapi_name() !== 'cli' || defined('SETTINGS_CLI_INPUT')) {
		return;
	}

	define('SETTINGS_CLI_INPUT', 1);

	$is_req = [];

	foreach ($_SERVER['argv'] as $parameter) {
		if (mb_substr($parameter, 0, 6) == '--req:' && ($pos = mb_strpos($parameter, '=')) > 0) {
			$name = mb_substr($parameter, 6, $pos - 6);
			$value = mb_substr($parameter, $pos + 1);
			$is_array = false;

			if (mb_substr($name, -2) == '[]') {
				$name = mb_substr($name, 0, -2);
				$is_array = true;
			}

			if (!isset($_REQUEST[$name]) || isset($is_req[$name])) {
				if ($is_array) {
					if (!isset($_REQUEST[$name])) {
						$_REQUEST[$name] = [];
					}

					array_push($_REQUEST[$name], $value);
				}
				else {
					$_REQUEST[$name] = $value;
				}

				$is_req[$name] = 1;
			}
		}
		else if (mb_substr($parameter, 0, 7) == '--http:' && ($pos = mb_strpos($parameter, '=')) > 0) {
			$name = 'HTTP_'.strtoupper(mb_substr($parameter, 6, $pos - 6));
			if (!isset($_SERVER[$name])) {
				$_SERVER[$name] = mb_substr($parameter, $pos + 1);
			}
		}
		else if (mb_substr($parameter, 0, 9) == '--server:' && ($pos = mb_strpos($parameter, '=')) > 0) {
			$name = 'SERVER_'.strtoupper(mb_substr($parameter, 9, $pos - 9));
			if (!isset($_SERVER[$name])) {
				$_SERVER[$name] = mb_substr($parameter, $pos + 1);
			}
		}
		else if (mb_substr($parameter, 0, 6) == '--srv:' && ($pos = mb_strpos($parameter, '=')) > 0) {
			$name = strtoupper(mb_substr($parameter, 6, $pos - 6));
			if (!isset($_SERVER[$name])) {
				$_SERVER[$name] = mb_substr($parameter, $pos + 1);
			}
		}
	}
}

