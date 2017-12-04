<?php

namespace rkphplib\tok;


require_once(__DIR__.'/TokPlugin.iface.php');
require_once(dirname(__DIR__).'/lib/split_str.php');


/**
 * Form validator plugin.
 *
 * {fv:init}
 * template.output= {:=label}: {:=input} {fv:error_message}|#|
 * template.input= <input type="text" name="{:=name}" value="{:=value}" class="{fv:error:$name}"> {fv:error_message:$name}
 * {:fv}
 *
 * {fv:init:add}
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
 * {fv:in:login}type=input|#|label=Login{:fv} 
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
protected $conf = [];


/**
 * Register plugins. 
 *
 * @tok {fv:init:[|add|reset]}required=...|#|...{:fv}
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
	$plugin['fv:check'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY; 
	$plugin['fv:in'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['fv:error'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['fv:error_message'] = TokPlugin::REQUIRE_PARAM;

  return $plugin;
}


/**
 * Initialize form validator.
 * 
 * @param string $do add|reset
 * @param array $p
 * @return ''
 */
public function tok_fv_init($do, $p) {
	if ($do == 'reset') {
		$this->conf = [];
	}

	foreach ($p as $key => $value) {
		if (mb_substr($key, 0, 9) == 'template.') {
			$name = mb_substr($key, 9);

			if (!isset($conf['template'])) {
				$conf['template'] = [];
			}

			$conf['template'][$name] = $value;
		}
		else if (mb_substr($key, 0, 6) == 'check.') {
			list ($name, $ignore) = explode('.', mb_substr($key, 6));

			if (!isset($conf['check'])) {
				$conf['check'] = [];
			}

			if (!isset($conf['check'][$name])) {
				$conf['check'][$name] = [];
			}

			array_push($conf['check'][$name], $value);
		}
		else if (mb_substr($key, 0, 3) == 'in.') {
			$name = mb_substr($key, 3);

			if (!isset($conf['in'])) {
				$conf['in'] = [];
			}

			$conf['in'][$name] = $value;
		}
		else if ($key == 'required') {
			$this->conf['required'] = \rkphplib\lib\split_str(',', $value);
		}
		else {
			$this->conf[$key] = $value;
		}
	}
}


/**
 * Return validation result (yes|error|).
 *
 * @throws
 * @return string (yes|error|)
 */
public function tok_fv_check() {
	throw new Exception('ToDo ...');
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
	$required = [ 'type' ];

	if (empty($this->conf['template']['output'])) {
		throw new Exception('missing [fv:init]template.output=');
	}

	if (empty($this->conf['template'][$p['type']])) {
		throw new Exception('missing [fv:init]template.'.$p['type'].'=');
	}

	$r = [ 'input' => $this->conf['template'][$p['type']] ];
	$res = $this->tok->replaceTags($this->conf['template']['output'], $r);

	throw new Exception('ToDo ...');
}


}

