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
 * Return http plugin.
 */
public function getPlugins(object $tok) : array {
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
 * - abs_path: get(protocol).get(url).get(directory)
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
	return self::httpGet($name);
}


/**
 * Static version of tok_http_get().
 *
 * @see tok_http_get
 * @throws
 * @param string $name
 * @return string 
 */
public static function httpGet($name) {
	$custom = [ 'ip', 'is_msie', 'host', 'abs_host', 'script', 'query', 'script_query', 
		'port', 'protocol', 'url', 'abs_url', 'abs_path', 'http_url', 'https_url' ];	
  $res = '';

	if ($name == '*') {
		$res = \rkphplib\lib\kv2conf($_SERVER);
	}
	else if ($name == 'custom') {
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
		$port = getenv('SERVER_PORT');
		$host = getenv('HTTP_HOST');

		if ($port == 80) {
			$port_host = 'http://'.$host;
		}
		else if ($port == 443) {
			$port_host = 'https://'.$host;
		}
		else if ($port > 0) {
			$port_host = 'http://'.$host.':'.$port;
		}

		if ($name == 'ip') {
			$res = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
		}
		else if ($name == 'is_msie') {
			$res = (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) ? '1' : '';
		}
		else if ($name == 'abs_host') {
			$res = $port_host;
		}
		else if ($name == 'abs_path') {
			$path = getenv('REQUEST_URI');
			$dir = empty($_REQUEST[SETTINGS_REQ_DIR]) ? '' : $_REQUEST[SETTINGS_REQ_DIR];

			if (($pos = strpos($path, '/index.php?')) !== false || ($pos = strpos($path, '/?')) !== false) {
				$res = $port_host.substr($path, 0, $pos);
			}
 			else if (substr(getcwd().'/', -1 * strlen($path)) == $path) {
				$res = $port_host.$path;
			}
			else if ($path == '/index.php' && !empty($dir)) {
				$res = $port_host.'/'.$dir;
			}
			else {
				throw new Exception('failed to detect abs_path', "port_host=[$port_host] path=[$path] getcwd=[".getcwd()."]");
			}
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
		else if ($name == 'script_query') {
			$res = $_SERVER['REQUEST_URI'];
		}
		else if ($name == 'url' || $name == 'abs_url') {
			$res = ($name == 'abs_url') ? $port_host.getenv('REQUEST_URI') : getenv('HTTP_HOST').getenv('REQUEST_URI');
			if (substr($res, -1) == '/') {
				$res .= basename($_SERVER['SCRIPT_FILENAME']);
			}
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
			throw new Exception("no such alias [$name] - use: ".join('|', $custom));
		}
	}

	return $res;
}


}

