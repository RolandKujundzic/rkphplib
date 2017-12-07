<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);

require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/ValueCheck.class.php');
require_once($parent_dir.'/lib/split_str.php');

use \rkphplib\Exception;
use \rkphplib\ValueCheck;


/**
 * Form validator plugin.
 *
 * {fv:conf:NAME}
 * template.output= {:=label}: {:=input} {fv:error_message}|#|
 * template.header|footer= ...|#|
 * template.input= <input type="text" name="{:=name}" value="{:=value}" class="{fv:error:$name}"> {fv:error_message:$name}
 * {:fv}
 *
 * {fv:init:[add]}
 * use= NAME|#| (default: use=default)
 * required= login, password|#|
 * check.login= minLength:2|#|
 * {sql_select:}SELECT count(*) AS num FROM {esc_name:}{login:@table}{:esc_name}
 *   WHERE login={esc:}{login:login}{:esc} AND id!={esc:}{login:id}{:esc}{:sql_select}
 * check.login.2= compare:0:eq:{sql_col:num}:error:{txt:}Login name already exists{:txt}|#|
 * check.password= minLength:4|#|
 * {:fv}
 * 
 * {tf:cmp:yes}{fv:check}{:tf} 
 * 
 * {true:} ... {:true}
 *
 * {false:}
 * <form>
 * {fv:in:login}type=text|#|label=Login{:fv} 
 *  = Login: <input type="text" name="login" value="{get:login}" class="{fv:error:login}"> {fv:error_message:login}
 * {fv:in:password}type=password|#|label=Password{:fv} 
 *  = Password: <input type="password" name="password" value="{get:password}" class="{fv:error:password}"> {fv:error_message:password}
 * </form>
 * {:false}
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TFormValidator implements TokPlugin {

/** @var array $conf */
protected $conf = [ 'default' => [], 'current' => [] ];

/** @var array $error */
protected $error = [];



/**
 * Register plugins. 
 *
 * @tok {fv:init:[|add]}required=...|#|...{:fv}
 * @tok {fv:input|password|select|text:name}label=...|#|...{:fv}
 * @tok {fv:check}
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
	$this->tok = $tok;

	$plugin = [];
	$plugin['fv'] = 0;
	$plugin['fv:init'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY; 
	$plugin['fv:conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['fv:check'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY; 
	$plugin['fv:in'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['fv:error'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['fv:error_message'] = TokPlugin::REQUIRE_PARAM;
	$plugin['fv:set_error_message'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY;

  return $plugin;
}


/**
 * Set default configuration.
 */
public function __construct() {
	$this->conf['default'] = [
		'template.header' => '',
		'template.output' => '{:=label}{:=input}{:=error_message}',
		'template.footer' => '',
		'template.input'    => '<input type="{:=type}" name="{:=name}" value="{:=value}"$tags>',
		'template.textarea' => '<textarea name="{:=name}"$tags>{:=value}</textarea>',
		'template.select'   => '<select name="{:=name}"$tags>{:=options}</select>',
		'submit' => 'form_action'
		];
}


/**
 * Set error message (if $msg is not empty).
 *
 * @param string $name
 * @param string $msg
 * @return ''
 */
public function tok_fv_set_error_message($name, $msg) {
	if (empty(trim($msg))) {
		return;
	}

	if (!isset($this->error[$name])) {
		$this->error[$name] = [];
	}

	array_push($this->error[$name], $msg);
}


/**
 * Set named configuration. Load configuration with use=name or use
 * name=default to overwrite default configuration.
 * 
 * @param string $name
 * @param array $p
 * @return ''
 */
public function tok_fv_conf($name, $p) {
	$this->conf[$name] = $p;
	return '';
}


/**
 * Initialize form validator. Keys:
 *
 * - use: default (list of names configurations)
 * - submit: form_action
 * 
 * @param string $do add|
 * @param array $p
 * @return ''
 */
public function tok_fv_init($do, $p) {

	if (empty($p['use'])) {
		$p['use'] = 'default';
	}

	if (mb_strpos($p['use'], 'default') === false) {
		$p['use'] = $p['use'] ? 'default,'.$p['use'] : 'default';
	}

	if ($do == 'add') {
		// do nothing ...
	}
	else {
		$this->conf['current'] = [];
	}

	$conf = $this->conf['current'];
	$use_conf = \rkphplib\lib\split_str(',', $p['use']);
	unset($p['use']);

	foreach ($use_conf as $name) {
		$conf = array_merge($conf, $this->conf[$name]);
	}

	$this->conf['current'] = array_merge($conf, $p);
}


/**
 * Return validation result (yes|error|).
 *
 * @tok {fv_check:} -> [|yes|error]
 * 
 * @throws
 * @return string (yes|error|)
 */
public function tok_fv_check() {
	$submit = $this->conf['current']['submit'];

	if (empty($_REQUEST[$submit])) {
		return '';
	}

	$error = [];

	$required = \rkphplib\lib\split_str(',', $this->conf['current']['required']);
	foreach ($required as $key) {
		if (!isset($_REQUEST[$key]) || mb_strlen($_REQUEST[$key]) == 0) {
			if (!isset($error[$key])) {
				$error[$key] = [];
			}

			array_push($error[$key], 'required');
		}
	}

	foreach ($this->conf['current'] as $key => $key_value) {
		$path = explode('.', $key);

		if ($path[0] == 'check') {
			$req_value = isset($_REQUEST[$path[1]]) ? $_REQUEST[$path[1]] : '';
			if (ValueCheck::run($path[1], $req_value, $key_value)) {
				if (!isset($error[$path[1]])) {
					$error[$path[1]] = [];
				}

				array_push($error[$path[1]], $this->getErrorMessage($path));
			}
		}
	}

	return (count($error) == 0) ? 'yes' : 'error';
}


/**
 * Return error message (error.path[1..2]). Example:
 *
 * check.login.1= isUnique:{login:@table}:...|#|
 * error.login.1= {txt:}already taken{:txt}|#|
 *
 * @param array $path
 * @return string
 */
protected function getErrorMessage($path) {
	$ignore = array_shift($path);
	$key = 'error.'.join('.', $path);
	return empty($this->conf['current'][$key]) ? 'invalid' : $this->conf['current'][$key];
}


/**
 * Return error.
 *
 * @throws
 * @return string (error|)
 */
public function tok_fv_error($name) {
	throw new Exception('ToDo ...');
}


/**
 * Return error message. Replace {:=name} and {:=error} in template.error_message (overwrite with $tpl).
 *
 * @throws
 * @return string 
 */
public function tok_fv_error_message($name, $tpl = '') {
	throw new Exception('ToDo ...');
}


/**
 * Show input for $name.
 *
 * @throws
 * @param string $name
 * @param array $p
 * @return string
 */
public function tok_fv_in($name, $p) {
	$conf = $this->conf['current'];
	$res = '';

	if (!empty($p['header'])) {
		$res .= $conf['template.header'];
	}

	$res .= $conf['template.output'];

	if (!empty($p['footer'])) {
		$res .= $conf['template.footer'];
	}

	$r['label'] = empty($p['label']) ? '' : $p['label'];
	$r['input'] = $this->getInput($name, $p);
	$r['error_message'] = isset($this->error[$name]) ? join('|', $this->error[$name]) : '';

	$res = $this->tok->replaceTags($res, $r);

	return $res;
}


/**
 * Return html input. Use template.input for type=[text|password|radio|checkbox|hidden|image|email|...].
 * Use template.textarea, template.select for type=[textarea|select].
 * Attribute keys: size, maxlength, placeholder, type, class, style, pattern, 
 * rows and cols (add value="{:=value}" if undefined).
 * Boolean attributes: readonly, multiple and disabled (e.g. readonly=1).
 * Other keys: prefix, suffix.
 *
 * @param string $name
 * @param array $p
 * @return string
 */
protected function getInput($name, $p) {
	$ri = $p;
	$ri['name'] = $name;
	$ri['value'] = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
	$conf = $this->conf['current'];

	if (!empty($p['width'])) {
		$ri['style'] = 'width: '.$p['width'];
	}

	if (isset($this->error[$name])) {
		$ri['class'] = 'error';
	}

	if (!empty($conf['template.'.$p['type']])) {
		$input = $conf['template.'.$p['type']];
	}
	else {
		$input = $conf['template.input'];
	}

	$attributes = [ 'size', 'maxlength', 'placeholder', 'type', 'class', 'style', 'pattern', 'rows', 'cols' ];
	$tags = '';

	foreach ($attributes as $key) {
		if (isset($p[$key])) {
			$tags .= mb_strpos($input, $key.'="') ? '' : ' '.$key.'="{:='.$key.'}"';
			$delimiter = ($key == 'style') ? ';' : ' ';
			$ri[$key] = empty($ri[$key]) ? $p[$key] : $ri[$key].$delimiter.$p[$key];
		}
	}

	$boolean_attributes = [ 'readonly', 'multiple', 'disabled' ];
	foreach ($boolean_attributes as $key) {
		if (!empty($p[$key])) {
			$tags .= ' {:='.$key.'}';
			$ri[$key] = $key;
		}
	}

	// selected, checked ???
	\rkphplib\lib\log_debug("getInput> $tags - $input");

	$input = str_replace('$tags', $tags, $input);
	$input = $this->tok->replaceTags($input, $ri);

	\rkphplib\lib\log_debug("getInput($name, ".print_r($p, true).") = $input");
	return $input;
}


}

