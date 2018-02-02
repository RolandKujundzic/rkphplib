<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/Exception.class.php');
require_once($parent_dir.'/lib/kv2conf.php');

use \rkphplib\Exception;


/**
 * Access http environment.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class THttp implements TokPlugin {


/**
 *
 */
public function getPlugins($tok) {
  $plugin = [];
  $plugin['http:get'] = TokPlugin::ONE_PARAM;
  $plugin['http'] = 0;
  return $plugin;
}


/**
 * Return value of _SERVER[$name]. If name=* return string-map.
 * Use all uppercase name. View more keys with {http:get:custom}:
 *
 * - ip: return _SERVER[REMOTE_ADDR]
 * - is_msie: 1 (if _SERVER[HTTP_USER_AGENT] contains 'MSIE') or ''
 * - host: _SERVER[HTTP_HOST]
 * - script: /admin/index.php?dir=test
 * - port: 80 | 443 | custom
 * - protocoll: https:// | http://
 * - query: 
 * - url: get(host)[:get(port)]get(script)[?get(query)]
 * - abs_url: get(protocol).get(url)
 * - http_url: http://get(host)[:get(port)]get(script)
 * - https_url: https://get(host)get(script)
 * 
 * @tok <pre>{http:get:*}</pre>
 * @tok {http:get}SERVER_NAME{:get}
 * 
 * @throws if _SERVER[$name] is not set
 * @param string $name
 * @return string
 */
public function tok_http_get($name) {
  $res = '';

	if ($name == '*') {
		$res = \rkphplib\lib\kv2conf($_SERVER);
	}
	else if ($name == 'custom') {
		$custom = [ 'ip', 'is_msie', 'host', 'script', 'port', 'protocol', 'url', 'abs_url', 
			'http_url', 'https_url' ];	
		$res = [];

		foreach ($custom as $key) {
			$res[$key] = $this->tok_http_get($key);
		}

		$res = \rkphplib\lib\kv2conf($res);
	}
	else if ($name == strtoupper($name)) {
		if (!isset($_SERVER[$name])) {
			throw new Exception("no such key: _SERVER[$name]");
	  }
		else {
			$res = $_SERVER[$name];
		}
	}
	else {
		if ($name == 'ip') {
			$res = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		}
		else if ($name == 'is_msie') {
			$res = (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) ? '1' : '';
		}
		else if ($name == 'host') {
			$res = getenv('HTTP_HOST');
		}
		else if ($name == 'script') {
			$res = getenv('SCRIPT_NAME');
		}
		else if ($name == 'port') {
			$res = getenv('SERVER_PORT');
		}
		else if ($name == 'protocol') {
			$res = (getenv('SERVER_PORT') == 443) ? 'https://' : 'http://';
		}
		else if ($name == 'query') {
			$res = empty($_SERVER['QUERY_STRING']) ?  '' : $_SERVER['QUERY_STRING'];
		}
		else if ($name == 'url' || $name == 'abs_url') {
			if ($name == 'abs_url') {
				$port = getenv('SERVER_PORT');
				$host = getenv('HTTP_HOST');

				if ($port == 80) {
					$res = 'http://'.$host;
				}
				else if ($port == 443) {
					$res = 'https://'.$host;
				}
				else if ($port > 0) {
					$res = 'http://'.$host.':'.$port;
				}
			}
			else {
				$res = getenv('HTTP_HOST');
			}

			$res .= getenv('REQUEST_URI');
		}
		else if ($name == 'http_url') {
			$res = 'http://'.getenv('HTTP_HOST');

			if (getenv('SERVER_PORT') != 80) {
				$res .= ':'.getenv('SERVER_PORT');
			}

			$res .= getenv('SCRIPT_NAME');
		}
		else if ($name == 'https_url') {
			$res = 'https://'.getenv('HTTP_HOST').getenv('SCRIPT_NAME');
		}
		else {
			throw new Exception("no such alias [$name] - use: ip|is_msie|host|script|port|protocol|url|http_url|https_url|query");
		}
	}

	return $res;
}


}

