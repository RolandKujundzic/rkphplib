<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Exception.class.php';
require_once __DIR__.'/../lib/kv2conf.php';
require_once __DIR__.'/../lib/cookie.php';

use rkphplib\Exception;

use function rkphplib\lib\kv2conf;
use function rkphplib\lib\cookie;



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
public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];
	$plugin['http:get'] = TokPlugin::ONE_PARAM;
	$plugin['url'] = TokPlugin::NO_PARAM || TokPlugin::NO_BODY;
	$plugin['http'] = 0;
	$plugin['domain:idn'] = TokPlugin::NO_PARAM;
	$plugin['domain:utf8'] = TokPlugin::NO_PARAM;
	$plugin['cookie'] = TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY;
	$plugin['domain'] = 0;
  return $plugin;
}


/**
 * Get|Set cookie. Default expires is 12 month.
 * Use strtotime for expires e.g. +1 day|week|hour or 1 month 2 hour.
 *
 * @see lib/cookie
 * @tok {cookie:name}John{:cookie} - set cookie name=John, 3 month valid
 * @tok {cookie:abc}value=1|#|expires=+1 week{:cookie} - set cookie abc=1, 1 week valid
 * @tok {cookie:abc} == 1
 * @tok {cookie:abc}expires=-1 hour{:cookie} - remove cookie abc
 * 
 */
public static function tok_cookie(string $name, array $p) : ?string {
	if (count($p) == 0) {
		$res = isset($_COOKIE[$name]) ? $_COOKIE[$name] : '';
		// \rkphplib\lib\log_debug("THttp::tok_cookie:56> return $name = '$res'");
		return $res;
	}

	$value = empty($p['value']) ? '' : $p['value'];
	if (!$value && isset($p[0])) {
		$value = $p[0];
	}

	if (!isset($p['expires'])) {
		$p['expires'] = '3 month';
	}

	cookie($name, $value, $p);
	// \rkphplib\lib\log_debug([ "THttp::tok_cookie:70> set $name = '$value' - <1>", $p ]);
  return null;
}


/**
 * Return internationalized domain name (IDN). Domain part with utf8 characters is converted into xn--NNN code.
 */
public function tok_domain_idn(string $domain) : string {
	if (empty($domain)) {
		return '';
	}

	$idn = idn_to_ascii($domain);
	if ($idn === false) {
		throw new Exception('invalid domain '.$domain);
	}

	return $idn;
}


/**
 * Return utf8 domain name.
 */
public function tok_domain_utf8(string $domain) : string {
	if (empty($domain)) {
		return '';
	}

	$idn = idn_to_utf8($domain);
	if ($idn === false) {
		throw new Exception('invalid domain '.$domain);
	}

	return $idn;
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
 * - protocol: https:// | http://
 * - query: 
 * - url: get(host)[:get(port)]get(script)[?get(query)]
 * - abs_url: get(protocol).get(url)
 * - abs_path: get(protocol).get(url).get(directory)
 * - http_url: http://get(host)[:get(port)]get(script)
 * - https_url: https://get(host)get(script)
 * 
 * @tok <pre>{http:get:*}</pre>
 * @tok {http:get}SERVER_NAME{:get}
 */
public function tok_http_get(string $name) : string {
	return self::httpGet($name);
}


/**
 * @tok {url:} = {http:get:abs_path}/
 */
public function tok_url() : string {
	return self::httpGet($name).'/';
}


/**
 * Static version of tok_http_get().
 *
 * @see tok_http_get
 */
public static function httpGet(string $name) : string {
	$custom = [ 'ip', 'is_msie', 'host', 'abs_host', 'script', 'query', 'script_query', 
		'port', 'protocol', 'url', 'abs_url', 'abs_path', 'http_url', 'https_url' ];	
  $res = '';

	if ($name == '*') {
		$res = kv2conf($_SERVER);
	}
	else if ($name == 'custom') {
		$res = [];

		foreach ($custom as $key) {
			$res[$key] = $this->tok_http_get($key);
		}

		$res = kv2conf($res);
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

			if (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'http' && getenv('SERVER_PORT') != 80) {
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

