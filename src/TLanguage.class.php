<?php

namespace rkphplib;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;


/**
 * Multilanguage plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class TLanguage implements TokPlugin {

private $_db;
private $_sess;
private $_cache = array();
private $_conf = array();


/**
 * Return Tokenizer plugin list:
 * 
 *  language:init|get, txt, ptxt, dtxt
 * 
 * @param Tokenizer $tok
 * @return map <string:int>
 */
public function getPlugins($tok) {
	$plugin = [
		'language:init' => TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY, 
		'language:get' => TokPlugin::NO_BODY,
		'txt' => 0,
		'ptxt' => 0,
		'dtxt' => 0,
	];

	return $plugin;
}


/**
 * Set database connection string
 * 
 * @param string $dsn
 */
public function setDSN($dsn) {
  $this->_db = new Database();
  $this->_db->setDSN($dsn);

  $this->_db->setQuery('select', "SELECT * FROM {:=table} WHERE id='{:=id}' {:=_or_dir_path}");
  $this->_db->setQuery('insert', "INSERT INTO {:=table} (last, id, dir, txt, {:=lang}) VALUES (now(), '{:=id}', '{:=dir}', '{:=txt}', '{:=txt}')");
  $this->_db->setQuery('update', "UPDATE {:=table} SET last=now(), dir='{:=dir}', {:=lang}='{:=txt}' WHERE id='{:=id}'");
  $this->_db->setQuery('update_dir', "UPDATE {:=table} SET dir='' WHERE id='{:=id}'");
}


/**
 * Add [language|locale|txt|ptxt|dtxt:] plugins. 
 */
public function addTo(&$tok) {
  $tok->setPlugin('language', $this);
  $tok->setPlugin('locale', $this);
  $tok->setPlugin('txt', $this);
  $tok->setPlugin('ptxt', $this);
  $tok->setPlugin('dtxt', $this);
}


/**
 * Return session language.
 *
 * @return string
 */
public function tok_language_get() {

  if (!is_object($this->_sess)) {
    throw new Exception('use language:init first');
  }

  $res = $this->_sess->ndGet();

  if (strlen($res) != 2) {
    lib_abort('use [language:] first');
  }

  return $res;
}


/**
 * Initialize language plugin. Parameter:
 * 
 *  session.timeout_url:
 *  session.ttl: 36000
 *  table: language
 *  keep_txt: yes (=default) | no
 *  use: nl (switch to nl)
 *  mark: colorize all tagged texts ('no' or 'rrggbb' value)
 *  default: de (default language - use domain_suffix for auto detection)
 *  txt: de (no translation use text inside txt filter)
 *  
 * @param hash $p
 */
public function tok_language_init() {

  $this->_sess = new Session();
  $p['session.timeout_url'] = '';
  $p['session.ttl'] = 36000;
  $this->_sess->ndInit($p, 'language');

  $this->_conf['table'] = empty($p['table']) ? 'language' : $p['table'];
  $this->_conf['keep_txt'] = (empty($p['keep_txt']) || $p['keep_txt'] != 'no') ? true : false;
  $this->_conf['txt'] = empty($p['txt']) ? '' : $p['txt'];

  if (is_object($this->_db)) {
    $this->_db->setQueryHash($p);
  }

  $curr = $this->_sess->ndGet();

  if (!empty($p['use'])) {
    if ($curr != $p['use']) {
      $this->_sess->ndSet($p['use']);
      $curr = $p['use'];
    }
  }

  if (!empty($p['marker'])) {
  	if ($p['marker'] == 'no') {
  		$this->_sess->ndDel('marker');
  	}
  	else if (preg_match('/^[0-9abcdef]{6}$/i', $p['marker'])) {
  		$this->_sess->ndSet($p['marker'], 'marker');
  	}
  }
  
  if (empty($curr)) {
    if (!empty($p['default'])) {

      if ($p['default'] == 'domain_suffix') {
        $domain = getenv('HTTP_HOST');
        $s3 = substr($domain, -3);
        $s4 = substr($domain, -4);

        if ($s4 == '.com' || $s3 == '.uk') {
          $p['default'] = 'en';
        }
        else if ($s4 == '.xxx' || $s3 == '.xx' || $s4 == '.org' || $s4 == '.net') {
        	$p['default'] = 'de';
        }
        else {
          $lang2 = strtolower(substr($domain, -2));
					if ($lang2 == '0' || intval($lang2) > 0) {
						// DOMAIN = IP
						$lang2 = 'de';
					}

					$p['default'] = $lang2;
        }
      }

      $this->_sess->ndSet($p['default']);
      $curr = $p['default'];
    }
    else {
      lib_abort('empty use and default key');
    }
  }

  Language::set($curr);
}


/**
 * 
 */
public function tokCall($action, $param, $arg) {
  $res = '';

  if ($action == 'language') {
    if ($param == 'get') {
      $res = $this->_get_language();
    }
    else if ($param == 'map') {
      $res = $this->_language_map(trim($arg));
    }
    else {
      $this->_language(lib_arg2hash($arg));
    }
  }
  else if (!is_object($this->_sess)) {
    lib_abort("use [language:] plugin before [$action:]");
  }

  if ($action == 'txt') {
    $res = $this->_txt($param, $arg);
  }
  else if ($action == 'locale') {
    $res = $this->_locale($param);
  }
  else if ($action == 'dtxt') {
    $res = $this->_dtxt($param, $arg);
  }
  else if ($action == 'ptxt') {
    // {ptxt:}xxx $p1x yyy $p2x zzz|#|p1|#|p2{:ptxt}
    $p = lib_arg2array($arg);
    $res = $this->_txt($param, array_shift($p));

    for ($i = 0; $i < count($p); $i++) {
      $res = str_replace('$p'.($i + 1).'x', $p[$i], $res);
    }
  }

  return $res;
}


/**
 * Return [locale:currency|vat] value. 
 * 
 * @param string $param currency|vat
 * @return string
 */
private function _locale($param) {
  $res = '';

  if ($param == 'currency') {
    $res = Language::currency();
  }
  else if ($param == 'vat') {
    $res = Language::vat();
  }

  return $res;
}


/**
 * Return translation. Translation id is either $param or md5(stripped($arg)) (if $arg is =XXX use XXX as id).
 * 
 * @param string $param
 * @param string $arg
 * @return string
 */
private function _txt($param, $arg) {

  $dir = empty($_REQUEST['dir']) ? '' : $_REQUEST['dir'];

  if (!empty($param)) {
  	$id = $param;
  }
  else if (substr($arg, 0, 1) == '=') {
  	$id = substr($arg, 1);
  	$arg = '';
  }
  else {
  	$id = md5(preg_replace("/[\s]+/", '', $arg));
  }

  $lang = $this->_sess->ndGet();
  $ckey = $id.'_'.$lang;
  $res = '';

  if (!empty($this->_conf['txt']) && $this->_conf['txt'] == $lang) {
  	if (($marker = $this->_sess->ndGet('marker'))) {
  		$arg = '<font style="background-color:#'.$marker.'">'.$arg.'</font>';
  	}
		
  	return $arg;
  }

  if (count($this->_cache) == 0) {
    $this->_fill_cache($id, $lang);
  }

  if (!empty($this->_cache[$ckey])) {
    $res = $this->_cache[$ckey];
  }
  else {
    // try to update dir
    $replace = array('table' => $this->_conf['table'], 'id' => $id);
    $this->_db->execute($this->_db->getQuery('update_dir', $replace));

    if ($this->_conf['keep_txt']) {
    	if (!$param && !$arg) {
    		$res = '';
    	}
    	else {
    		$res = TokMarker::getPluginTxt('txt', $param, $arg);
      	lib_warn("($dir:$id) not found - res=[$res] param=[$param] arg=[$arg]");
    	}
    }
    else {
      $res = $arg;
    }
  }

  if (($marker = $this->_sess->ndGet('marker'))) {
  	$res = '<font style="background-color:#'.$marker.'">'.$res.'</font>';
  }    
  
  return $res;
}


//-----------------------------------------------------------------------------
private function _dtxt($param, $arg) {

  if (strlen($param) == 0 && strlen($arg) == 0) {
    return '';
  }

  $dir = empty($_REQUEST['dir']) ? '' : $_REQUEST['dir'];
  $id = empty($param) ? '_'.md5(preg_replace("/[\s]+/", '', $arg)) : '_'.$param;  
  $lang = $this->_sess->ndGet();
  $ckey = $id.'_'.$lang;

  if (count($this->_cache) == 0) {
    $this->_fill_cache($id, $lang);
  }
  
  if (!isset($this->_cache[$ckey]) && !empty($arg)) {
		$replace = array('table' => $this->_conf['table'], 'id' => $id, 
			'dir' => $dir, 'lang' => $lang, 'txt' => $arg, '_or_dir_path' => '');

		// is might still be defined ... for other directory ...
		$dbres = $this->_db->select($this->_db->getQuery('select', $replace));
		if (count($dbres) > 0) {
			$this->_cache[$ckey] = $dbres[0][$lang];

			if ($dbres[0]['dir'] != '' && $dbres[0]['dir'] != $replace['dir']) {
        $replace['txt'] = $dbres[0][$lang];
				$replace['dir'] = '';
        $this->_db->execute($this->_db->getQuery('update', $replace));
     	}
		}
    else {
    	$this->_db->execute($this->_db->getQuery('insert', $replace));
      $this->_cache[$ckey] = $arg;
    }
  }

  $res = '';

  if (!isset($this->_cache[$ckey])) {
    lib_warn("_dtxt: unkown ckey [$ckey] = [$param][$arg]");    
  }
  else {
    $res = $this->_cache[$ckey];
  }
  
  return $res;
}


//-----------------------------------------------------------------------------
private function _fill_cache($id, $lang) {

  $dir = empty($_REQUEST['dir']) ? '' : $_REQUEST['dir'];
  $dir_path = $dir;
  $where_dir = '';

  while ($dir_path && ($pos = strrpos($dir_path, '/')) > 0) {
    $where_dir .= " OR dir='".$dir_path."'";
    $dir_path = substr($dir_path, 0, $pos);
  }

  $where_dir .= " OR dir='".$dir_path."' OR dir=''";

  $replace = array('table' => $this->_conf['table'], 'id' => $id, '_or_dir_path' => $where_dir);
  $db_res = $this->_db->select($this->_db->getQuery('select', $replace));

  // see: Language::get() too
  $alias = array('ba' => 'hr', 'rs' => 'hr', 'us' => 'en', 'uk' => 'en');

  for ($i = 0; $i < count($db_res); $i++) {
    $ckey = $db_res[$i]['id'].'_'.$lang;

    if (!empty($db_res[$i][$lang])) {
      $this->_cache[$ckey] = $db_res[$i][$lang];
    }
    else if (isset($alias[$lang])) {
      $al = $alias[$lang];

      if (!empty($db_res[$i][$al])) {
        $this->_cache[$ckey] = $db_res[$i][$al];
      }
    }
    
    if (!isset($this->_cache[$ckey])) {
      $this->_cache[$ckey] = $db_res[$i]['txt'];
    }
  }
}


}

?>
