<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/TokHelper.trait.php');
require_once(__DIR__.'/Tokenizer.class.php');
require_once($parent_dir.'/ValueCheck.class.php');
require_once($parent_dir.'/lib/htmlescape.php');
require_once($parent_dir.'/lib/split_str.php');
require_once($parent_dir.'/lib/conf2kv.php');
require_once($parent_dir.'/lib/is_map.php');

use \rkphplib\Exception;
use \rkphplib\tok\Tokenizer;
use \rkphplib\ValueCheck;


/**
 * Form validator plugin.
 *
 * {fv:conf:NAME}
 * template.engine= default[|bootstrap|material]
 * default.in.text= <input type="text" name="{:=name}" value="{:=value}" class="{:=class}"> {fv:error_message:$name}
 * default.output.in= {:=label}: {:=input} {fv:error_message}|#|
 * default.header|footer= ...|#|
 * {:fv}
 *
 * {fv:init:[add]}
 * use= NAME|#| (default: use=default)
 * hidden_keep= dir, ...|#|
 * hidden.form_action=1|#|
 * hidden.id= 5|#|
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
 *  = Login: <input type="text" name="login" value="{get:login}" class="{:=class}"> {fv:error_message:login}
 * {fv:in:password}type=password|#|label=Password{:fv} 
 *  = Password: <input type="password" name="password" value="{get:password}" class="{:=class}"> {fv:error_message:password}
 * </form>
 * {:false}
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TFormValidator implements TokPlugin {
use TokHelper;


/** @var Tokenizer $tok */
private $tok;

/** @var hash $conf */
protected $conf = [ 'default' => [], 'current' => [] ];

/** @var hash $error */
protected $error = [];

/** @var hash $example */
protected $example = [];



/**
 * Register plugins. 
 *
 * @tok {fv:init:[|add]}required=...|#|...{:fv}
 * @tok {fv:in:name}label=...|#|...{:fv}
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
	$plugin['fv:conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY | TokPlugin::TEXT;
	$plugin['fv:check'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY; 
	$plugin['fv:in'] = TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY | TokPlugin::REDO;
	$plugin['fv:tpl'] = TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY | TokPlugin::REDO;
	$plugin['fv:hidden'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['fv:preset'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['fv:error'] = TokPlugin::REQUIRE_PARAM;
	$plugin['fv:error_message'] = TokPlugin::REQUIRE_PARAM;
	$plugin['fv:set_error_message'] = TokPlugin::REQUIRE_PARAM;

  return $plugin;
}


/**
 * Set default configuration. Configuration Parameter:
 *
 * template.engine= default|bootstrap|material
 * ENGINE.in.[const|input|textarea|select|file|multi_checkbox|fselect] = ...
 * 
 * Render ENGINE.in.TYPE, label and ERROR_MESSAGE into template.output.TEMPLATE.OUTPUT
 */
public function __construct() {
	$label = TAG_PREFIX.'label'.TAG_SUFFIX;
	$label2 = TAG_PREFIX.'label2'.TAG_SUFFIX;
	$input = TAG_PREFIX.'input'.TAG_SUFFIX;
	$error = TAG_PREFIX.'error'.TAG_SUFFIX;
	$error_message = TAG_PREFIX.'error_message'.TAG_SUFFIX;
	$example = TAG_PREFIX.'example'.TAG_SUFFIX;
	$id = TAG_PREFIX.'id'.TAG_SUFFIX;
	$type = TAG_PREFIX.'type'.TAG_SUFFIX;
	$dir = isset($_REQUEST[SETTINGS_REQ_DIR]) ? $_REQUEST[SETTINGS_REQ_DIR] : '';
	$col = TAG_PREFIX.'col'.TAG_SUFFIX;
	$form_group = TAG_PREFIX.'form_group'.TAG_SUFFIX; 
	$fadeout_confirm = TAG_PREFIX.'fadeout_confirm'.TAG_SUFFIX;
	$name = TAG_PREFIX.'name'.TAG_SUFFIX;
	$value = TAG_PREFIX.'value'.TAG_SUFFIX;
	$class = TAG_PREFIX.'class'.TAG_SUFFIX;
	$method = TAG_PREFIX.'method'.TAG_SUFFIX;
	$upload = TAG_PREFIX.'upload'.TAG_SUFFIX;
	$tags = TAG_PREFIX.'tags'.TAG_SUFFIX;
	$options = TAG_PREFIX.'options'.TAG_SUFFIX;
	$fselect_input = TAG_PREFIX.'fselect_input'.TAG_SUFFIX;

	$tok = is_null($this->tok) ? Tokenizer::$site : $this->tok;
	if (is_null($tok)) {
		$tok = new Tokenizer();
	}

	$this->conf['default'] = [
		'submit' 					=> 'form_action',
		'label_required'	=> $label.'<sup>*</sup>',

		'template.engine' => 'default',

		'option.label_empty'  => '',

		'default.in.const'    => '<span class="const">'.$value.'</span>',
		'default.in.input'    => '<input type="'.$type.'" name="'.$name.'" value="'.$value.'" class="'.$class.'" '.$tags.'>',
		'default.in.textarea' => '<textarea name="'.$name.'" class="'.$class.'" '.$tags.'>'.$value.'</textarea>',
		'default.in.select'   => '<select name="'.$name.'" class="'.$class.'" '.$tags.'>'.$options.'</select>',
		'default.in.file'			=> '<input type="file" name="'.$name.'" class="'.$class.'" data-value="'.$value.'" '.$tags.'>',
		'default.in.multi_checkbox'	=> '<div class="multi_checkbox_wrapper">'.$input.'</div>',
		'default.in.multi_checkbox.entry' => '<div class="multi_checkbox"><span>'.$input.'</span><span>'.$label.'</span></div>',
		'default.in.fselect'  => '<span id="fselect_list_'.$name.'"><select name="'.$name.'" class="'.$class.'" '.
			'onchange="rkphplib.fselectInput(this)" '.$tags.'>'.$options.'</select></span>'.
			'<span id="fselect_input_'.$name.'" style="display:none">'.$fselect_input.'</span>',

		'default.error.message'					=> '<span class="error_message">'.$error.'</span>',
		'default.error.message_concat'	=> ', ',
		'default.error.message_multi'		=> "<i>$name</i>: <tt>$error</tt><br>",
		'default.error.const'						=> 'error',

		'default.output.in'		=> '<span class="label">'.$label.'</span>'.$input.$example.$error_message,

		'default.example'			=> '<span class="example">'.$example.'</span>',

		'default.header' => '<form class="fv" action="'.$tok->getPluginTxt([ 'link', '' ], '_='.$dir).'" method="'.
			$tok->getPluginTxt([ 'if', '' ], $method.HASH_DELIMITER.$method.HASH_DELIMITER.'get').'" '.
			$tok->getPluginTxt([ 'if', '' ], $upload.HASH_DELIMITER.'enctype="multipart/form-data"').' data-key13="prevent" novalidate>'."\n".
			$tok->getPluginTxt([ 'fv', 'hidden' ], null),

		'default.footer' => '<button type="submit" name="form_action" value="1">'.$label.'</button>'.
			'<div class="label2">'.$tok->getPluginTxt([ 'if', '' ], $label2.HASH_DELIMITER.'<button type="submit" name="form_action" '.
			'value="2">'.$label2.'</button>')."\n".$tok->getPluginTxt([ 'if', 'cmp:yes' ], 
			$tok->getPluginTxt([ 'fv', 'check' ], '').HASH_DELIMITER.'<h4 style="color:#006600" data-effect="fadeout">'.
			$fadeout_confirm."</h4>\n<script>\n".'setTimeout(function() { $('."'h4[data-effect=\"fadeout\"]').fadeOut(); }, 2000);</script>").
			"\n</div>\n</form>",


		'bootstrap.in.input'	  => '<input type="'.$type.'" name="'.$name.'" value="'.$value.'" class="form-control '.$class.'" '.$tags.'>',
		'bootstrap.in.file'     => '<input class="form-control-file '.$class.'" name="'.$name.'" type="file" data-value="'.$value.'" '.$tags.'>',
		'bootstrap.in.textarea' => '<textarea name="'.$name.'" class="form-control '.$class.'" '.$tags.'>'.$value.'</textarea>',
		'bootstrap.in.select'   => '<select name="'.$name.'" class="form-control '.$class.'" '.$tags.'>'.$options.'</select>',
		'bootstrap.in.multi_checkbox' => '<div class="row">'.$input.'</div>',
		'bootstrap.in.multi_checkbox.entry'	=> '<div class="col-md-4"><label class="form-check-label">'.$input.' '.$label.'</label></div>',
		'bootstrap.in.fselect'  => '<span id="fselect_list_'.$name.'"><select name="'.$name.'"class="form-control '.$class.'" '.
			'onchange="rkphplib.fselectInput(this)" '.$tags.'>'.$options.'</select></span>'.
			'<span id="fselect_input_'.$name.'" style="display:none">'.$fselect_input.'</span>',

		'bootstrap.error.const'	=> 'is-invalid',

		'bootstrap.output.in'		=> '<div class="'.$form_group.'">'."\n".'<label for="'.$id.'">'.$label.'</label>'.
			"$example$error_message\n$input\n</div>",

		'bootstrap.header'	=> '<div class="container {:=class}">'."\n".'<div class="row">'."\n".'<div class="'.
			$tok->getPluginTxt([ 'if', '' ], '{:=col}'.HASH_DELIMITER.$col.HASH_DELIMITER.'col-md-12').'">'."\n".
			'<form class="fv form" method="'.
			$tok->getPluginTxt([ 'if', '' ], $method.HASH_DELIMITER.$method.HASH_DELIMITER.'get').'" action="'.
			$tok->getPluginTxt([ 'link', '' ], '_='.$dir).'" '.$tok->getPluginTxt([ 'if', '' ], $upload.HASH_DELIMITER.
			'enctype="multipart/form-data"').' data-key13="prevent" novalidate>'."\n".$tok->getPluginTxt([ 'fv', 'hidden' ], null),
	
		'bootstrap.footer'	=> '<div class="row">'."\n".'<div class="col-md-4">'."\n".
			'<button type="submit" class="btn" name="form_action" value="1">'.$label.'</button>'."\n".'</div>'."\n".
			'<div class="col-md-8">'.$tok->getPluginTxt([ 'if', '' ], $label2.HASH_DELIMITER.'<button type="submit" name="form_action" '.
			'value="2" class="btn">'.$label2.'</button>')."\n".$tok->getPluginTxt([ 'if', 'cmp:yes' ], 
			$tok->getPluginTxt([ 'fv', 'check' ], '').HASH_DELIMITER.'<h4 style="color:#006600" data-effect="fadeout">'.
			$fadeout_confirm."</h4>\n<script>\n".'setTimeout(function() { $('."'h4[data-effect=\"fadeout\"]').fadeOut(); }, 2000);</script>").
			"\n</div>\n</div>\n</form>\n</div>\n</div>\n</div>",


		'material.in.input'	   => '<input type="'.$type.'" name="'.$name.'" value="'.$value.'" class="mdl-textfield__input '.$class.'" '.$tags.'>',
		'material.in.file'     => '<input class="mdl-textfield__input '.$class.'" name="'.$name.'" type="file" data-value="'.$value.'" '.$tags.'>',
		'material.in.textarea' => '<textarea name="'.$name.'" class="mdl-textfield__input '.$class.'" '.$tags.'>'.$value.'</textarea>',
		'material.in.select'   => '<select name="'.$name.'" class="mdl-textfield__input '.$class.'" '.$tags.'>'.$options.'</select>',
		'material.in.fselect'  => '<span id="fselect_list_'.$name.'"><select name="'.$name.'" class="mdl-textfield__input '.$class.'" '.
			'onchange="rkphplib.fselectInput(this)" '.$tags.'>'.$options.'</select></span>'.
			'<span id="fselect_input_'.$name.'" style="display:none">'.$fselect_input.'</span>',
		'material.in.checkbox' => '<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="'.$id.'"><input type="checkbox" '.
			'id="'.$id.'" name="'.$name.'" value="'.$value.'" class="mdl-checkbox__input mdl-js-ripple-effect '.$class.'" '.$tags.
			'><span class="mdl-checkbox__label">'.$label.'</span></label>'
		];
}


/**
 * Tokenize output $arg if form is in preset mode. Don't preset if 
 * _REQUEST.form_action is set or _REQUEST.id = add.
 *
 * @tok_example[
 * {fv:preset}
 * {sql:query}SELECT * FROM table WHERE id={esc:id}{:sql}
 * {set_default:}password=|#|{sql:col:*}{:set_default}
 * {:fv}
 * @]
 *
 * @param string $arg
 * @return ''|string
 */
public function tok_fv_preset($arg) {
	$skey = $this->getConf('submit');

	if (isset($_REQUEST[$skey])) {
		return '';
	}

	if (!empty($_REQUEST['id']) && $_REQUEST['id'] == 'add') {
		return '';
	}

	return $arg;
}


/**
 * Should not be called. Always throw exception.
 *
 * @throws
 * @param string $param
 * @param string $arg
 */
public function tok_fv($param, $arg) {
	throw new Exception("no such plugin [fv:$param]...[:fv]", "param=[$param] arg=[$arg]");
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
 * Return hidden input for conf.keep_hidden and conf.hidden.key.
 *
 * @return string
 */
public function tok_fv_hidden() {
	$res = '';

	if (!empty($hidden_keep = $this->getConf('hidden_keep', '', false))) {
		$list = \rkphplib\lib\split_str(',', $hidden_keep);
		foreach ($list as $key) {
			if (isset($_REQUEST[$key])) {
				$res .= '<input type="hidden" name="'.$key.'" value="'.\rkphplib\lib\htmlescape($_REQUEST[$key]).'">'."\n";
			}
		}
	}

	foreach ($this->conf['current'] as $key => $value) {
		if (mb_substr($key, 0, 7) == 'hidden.') {
			$key = mb_substr($key, 7);
			$res .= '<input type="hidden" name="'.$key.'" value="'.\rkphplib\lib\htmlescape($value).'">'."\n";
		}
	}

	return $res;
}


/**
 * Set named configuration. Load configuration with use=name or use
 * name=default to overwrite default configuration.
 * 
 * @param string $name
 * @param hash $p
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
 * - option.label_empty: ...
 * 
 * @param string $do add|
 * @param array $p
 * @return ''
 */
public function tok_fv_init($do, $p) {

	if (!is_array($p)) {
		$p = [];
	}

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

	if (!isset($this->conf['current']['required'])) {
		$this->conf['current']['required'] = [];
	}

	if (!is_array($this->conf['current']['required'])) {
		$this->conf['current']['required'] = empty($this->conf['current']['required']) ? [] : 
			\rkphplib\lib\split_str(',', $this->conf['current']['required']);
	}
}


/**
 * Return validation result (yes|error|). Call get2NData() if multi_checkbox input exists.
 * If _REQUEST[conf[submit]] is empty do nothing. Apply all conf[check.*] value checks.
 *
 * @tok {fv_check:} -> [|yes|error]
 * 
 * @throws
 * @return string (yes|error|)
 */
public function tok_fv_check() {
	$submit = $this->getConf('submit');

	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_check> submit=$submit _REQUEST: ".print_r($_REQUEST, true));
	foreach ($this->conf['current'] as $key => $value) {
		if (substr($key, 0, 3) == 'in.' && substr($value, 0, 14) == 'multi_checkbox') {
			$this->conf['current'][$key] = $this->get2NData(substr($key, 3), $value);
		}
	}

	if (empty($_REQUEST[$submit])) {
		return '';
	}

	if (count($this->error) > 0) {
		return 'error';
	}

	if (!is_array($this->conf['current']['required'])) {
		$this->conf['current']['required'] = \rkphplib\lib\split_str(',', $this->conf['current']['required']);
	}

	foreach ($this->conf['current']['required'] as $key) {
		if (!isset($_REQUEST[$key]) || mb_strlen($_REQUEST[$key]) == 0) {
			if (!isset($this->error[$key])) {
				$this->error[$key] = [];
			}

			array_push($this->error[$key], 'required');
		}
	}

	foreach ($this->conf['current'] as $key => $check) {
		$path = explode('.', $key);

		if ($path[0] == 'check') {
			$name = $path[1];
			$req_value = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';

			if (!ValueCheck::run($name, $req_value, $check)) {
				if (!isset($this->error[$name])) {
					$this->error[$name] = [];
				}

				$this->setExample($name, $check);
				array_push($this->error[$name], $this->getErrorMessage($path));
				// \rkphplib\lib\log_debug("TFormValidator->tok_fv_check> path=$key name=$name error: ".print_r($this->error[$name], true));
			}
		}
	}

	return (count($this->error) == 0) ? 'yes' : 'error';
}


/**
 * Set example. Use conf.example.$name_$check= example if set.
 *
 * @param string $name
 * @param string $check
 */
private function setExample($name, $check) {
	$map = [
		'isMobile' => '+49176123456'
	];

	$example = '';

	if (!empty($this->conf['current']['example.'.$name.'_'.$check])) {
		$example = $this->conf['current']['example.'.$name.'_'.$check];
	}
	else if (!empty($map[$check])) {
		$example = $map[$check];
	}

	$this->example[$name] = $example;
}


/**
 * Return error message (error.path[1..2]). Overwrite default error message 'invalid'
 * with conf[error.CHECK_NAME]. Example:
 *
 * check.login.1= isUnique:{login:@table}:...|#|
 * error.login.1= {txt:}already taken{:txt}|#|
 *
 * @param array $path e.g. [ check, name, 1 ] (= check.name.1)
 * @return string
 */
protected function getErrorMessage($path) {
	$ignore = array_shift($path);
	$key = 'error.'.join('.', $path);
	return empty($this->conf['current'][$key]) ? 'invalid' : $this->conf['current'][$key];
}


/**
 * Return "error" (= ENGINE.error.const or $tpl if set) if $name check failed.
 *
 * @throws
 * @param string $name
 * @param string $tpl (optional)
 * @return string (error|)
 */
public function tok_fv_error($name, $tpl) {
	if (!isset($this->error[$name])) {
		return '';
	}

	return empty($tpl) ? $this->getConf('error.const', true) : $tpl;
}


/**
 * Return error message. Replace {:=name} and {:=error} in ENGINE.error.message[_multi] (overwrite with $tpl). If there are
 * multiple errors concatenate ENGINE.error.message with ENGINE.error.message_concat.
 *
 * Use name=* to return all error messages (concatenate ENGINE.error.message_multi).
 *
 * @throws
 * @return string 
 */
public function tok_fv_error_message($name, $tpl = '') {
	$res = '';

	if ($name == '*') {
		if (empty($tpl)) {
			$tpl = $this->getConf('error.message_multi', true);
		}

		foreach ($this->error as $key => $value) {
			$res .= $this->tok_fv_error_message($key, $tpl);
		}

		$res = join(',', array_keys($this->error));
	}
	else if (isset($this->error[$name])) {
		if (empty($tpl)) {
			$tpl = $this->getConf('error.message', true);
		}

		$error_list = $this->error[$name]; 
		if ($this->tok->hasPlugin('txt')) {
			for ($i = 0; $i < count($error_list); $i++) {
				// localize error message
				$error_list[$i] = $this->tok->getPluginTxt([ 'txt', 'fv_error_'.md5($error_list[$i]) ], $error_list[$i]);
			}
		}

		$r = [ 'name' => $name ];
		$r['error'] = join($this->getConf('error.message_concat', true), $error_list);

		$res = $this->tok->replaceTags($tpl, $r);
	}

	\rkphplib\lib\log_debug("tok_fv_error_message($name, ...)> name=[$name] res=[$res] - error: ".print_r($this->error, true));
	return $res;
}


/**
 * Return conf.template.$name. Replace and remove tags.
 *
 * @tok {fv:tpl:header}method=post|#|upload=1{:fv}
 * @tok {fv:tpl:footer}label=absenden{:fv}
 * 
 * @throws
 * @param string $name
 * @param array $p
 * @return string
 */
public function tok_fv_tpl($name, $p) {
	$res = $this->tok->replaceTags($this->getConf($name, true), $p);
	return $this->tok->removeTags($res);
}


/**
 * Transform 2^N data definition. Example:
 * 
 * (value) in.interior= multi_checkbox, Klima, AHK, Radio
 * (return) multi_checkbox, interior0=Klima, interior1=AHK, interior2=Radio 
 *
 * @param string $name
 * @param string $name_def
 * @return string
 */
private function get2NData($name, $name_def) {
	// [0] => multi_checkbox, [1] => value_2^0, [2] => value_2^1, ...
	$r = \rkphplib\lib\conf2kv($name_def, '=', ',');

	if ($r[0] != 'multi_checkbox') {
		throw new Exception('invalid value of conf.in.'.$name, "$name=[$name_def] r: ".print_r($r, true));
	}

	$r['type'] = 'checkbox';
	$r['multi'] = 1;
	unset($r[0]);

	// \rkphplib\lib\log_debug("TFormValidator.get2NData($name, ...)> r: ".print_r($r, true));
	$value = isset($_REQUEST[$name]) ? intval($_REQUEST[$name]) : null;
	$done = false;
	$n = 1;

	while (!empty($r[$n]) && $n < 33) {
		$var = $name.($n - 1);
		$v = pow(2, ($n - 1));

		$r[$var] = $r[$n];
		unset($r[$n]);

		if (!empty($_REQUEST[$var]) && $_REQUEST[$var] == $v) {
			$value += $v;
		}
		else if (!is_null($value) && !isset($_REQUEST[$var]) && ($value & $v) == $v) {
			$_REQUEST[$var] = $v;
		}

		$n++;
	}

	$r['n_max'] = $n - 1;
	$res = \rkphplib\lib\kv2conf($r, '=', ',');

	if (empty($_REQUEST[$name]) && !is_null($value)) {
		$_REQUEST[$name] = $value;
	}

	// \rkphplib\lib\log_debug("TFormValidator.get2NData($name, ...)> value=[$value] res=[$res] r: ".print_r($r, true));
	return $res;
}


/**
 * Return multi-checkbox html.
 *
 * conf.
 * @param string $name
 * @param hash $p
 * @return string
 */
private function multiCheckbox($name, $p) {
	\rkphplib\lib\log_debug("TFormValidator.multiCheckbox($name, ...)> name=$name p: ".print_r($p, true));
	$entry = $this->getConf('in.multi_checkbox.entry', true);
  $entries = $this->getConf('in.multi_checkbox', true);
	$entry_list = '';

	// \rkphplib\lib\log_debug("TFormValidator.multiCheckbox($name, ...)> entry=[$entry] entries=[$entries]");
	for ($n = 0; $n < $p['n_max']; $n++) {
		$var = $name.$n;

		$r = [];
		$r['type'] = $p['type'];
		$r['value'] = pow(2, $n);

		if (!empty($_REQUEST[$var]) && $_REQUEST[$var] == $r['value']) {
			$r['checked'] = 'checked';
		}

		$html = $this->getInput($var, $r);
		// \rkphplib\lib\log_debug("TFormValidator.multiCheckbox> ".join('|', $r).": $html");
		$entry_list .= $this->tok->replaceTags($entry, [ 'input' => $html, 'label' => $p[$var] ]);
	}

	if (!empty($this->conf['current']['label_required']) && !empty($p['label']) && in_array($name, $this->conf['current']['required'])) {
		$p['label'] = $this->tok->replaceTags($this->conf['current']['label_required'], [ 'label' => $p['label'] ]);
	}

	$p['input'] = $this->tok->replaceTags($entries, [ 'input' => $entry_list ]);
  $res = empty($p['output']) ? $this->getConf('output.in', true) : $this->getConf('output.in.'.$p['output']);
  $res = $this->tok->removeTags($this->tok->replaceTags($res, $p));	
	\rkphplib\lib\log_debug("TFormValidator.multiCheckbox($name, ...)> res=[$res]");
	return $res;
}


/**
 * Show input for $name. Default input template conf[ENGINE.in.$type].
 * Default output template is conf[ENGINE.output.in]. If $p[output] is not empty 
 * use conf[ENGINE.in.OUTPUT].
 *
 * @throws
 * @param string $name
 * @param array $p
 * @return string
 */
public function tok_fv_in($name, $p) {
	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_in($name, ...)> p: ".print_r($p, true));
	$conf = $this->conf['current'];
	$res = empty($p['output']) ? $this->getConf('output.in', true) : $this->getConf('output.in.'.$p['output']);

	if (!empty($conf['in.'.$name])) {
		$this->parseInName($name, $conf['in.'.$name], $p);
	}

	if (!empty($p['multi'])) {
		return $this->multiCheckbox($name, $p);
	}

	if (!isset($p['value'])) {
		$p['value'] = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
	}

 	if (empty($p['id'])) {
		$p['id'] = 'fvin_'.$name;
	}

	if (!isset($p['type'])) {
		throw new Exception('define [fv:init]in.'.$name.'= ...');
	}

	if ($p['type'] == 'const') {
		if (is_null($p['value']) || $p['value'] == 'NULL' || !empty($p['is_null'])) {
			return '';
		}
	}

	if (!empty($conf['label_required']) && !empty($p['label']) && in_array($name, $conf['required'])) {
		$p['label'] = $this->tok->replaceTags($conf['label_required'], [ 'label' => $p['label'] ]);
	}

	$tag_form_group = $this->tok->getTag('form_group');
	if (mb_strpos($res, $tag_form_group) !== false) {
		$tag_error = $this->tok->getTag('error');
		$res = str_replace($tag_form_group, $tag_error.' form-group', $res);
	}

	$r = $p;
	$r['input'] = $this->getInput($name, $p);
	$r['error_message'] = $this->tok_fv_error_message($name);
	$r['error'] = isset($this->error[$name]) ? $this->getConf('error.const', true) : '';
	$r['example'] = empty($this->example[$name]) ? '' : 
		$this->tok->replaceTags($this->getConf('example', true), [ 'example' => $this->example[$name] ]);

	$res = $this->tok->removeTags($this->tok->replaceTags($res, $r));
	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_in($name, ...)> res=[$res] r: ".print_r($r, true));
	return $res;
}


/**
 * Return configuration key. Use 1|true for template.engine or name engine.
 *
 * @throws
 * @param string $key
 * @param string $engine (default = '')
 * @param boolean $required (default = true)
 * @return string
 */
public function getConf($key, $engine = '', $required = true) {
	$conf = $this->conf['current'];

	if (!empty($engine)) {
		if (is_bool($engine)) {
			$engine = $conf['template.engine'].'.';
		}
		else {
			$engine .= '.'; 
		}
	}

	$ckey = $engine.$key;

	if (!isset($conf[$ckey])) {
		$res = '';

		if (!empty($engine) && $engine != 'default.') {
			// try fallback to default engine
			$ckey2 = 'default.'.$key;

			if (isset($conf[$ckey2])) {
				$res = $conf[$ckey2];
			}
			else if ($required) {
				throw new Exception("no such configuration key $ckey", "ckey2=$ckey2 engine=$engine conf: ".print_r($conf, true));
			}
		}
		else if ($required) {
			throw new Exception("no such configuration key $ckey", "no engine");
		}
	}
	else {
		$res = $conf[$ckey];
	}

	return $res;
}


/**
 * Parse value and add to input map p. Examples:
 *
 *  - in.name= checkbox[,VALUES] - default = single checkbox with value 1
 *  - in.name= radio,
 *  - in.name= area(=textarea),ROWS,COLS,WRAP
 *  - in.name= text(=input),SIZE|WIDTHcc,MAXLENGTH
 *  - in.name= pass(=input),
 *  - in.name= file,
 *  - in.name= select,
 *  - in.name= fselect,
 *  - in.name= set,
 *  - in.name= multi_select,
 *
 * @param string $name
 * @param string $value
 * @param map-reference &$p 
 * @return map
 */
protected function parseInName($name, $value, &$p) {

	$r = \rkphplib\lib\conf2kv($value, '=', ',');

	if (is_string($r)) {
		$p['type'] = $r;
		$r = [];
	}

	if (!empty($r['@_1'])) {
		$p['type'] = $r['@_1'];
		unset($r['@_1']);
	}
	else if (!empty($r[0])) {
		$p['type'] = $r[0];
		unset($r[0]);
	}

	if (!empty($r['multi'])) {
		$p = array_merge($p, $r);
		\rkphplib\lib\log_debug("TFormValidator.parseInName($name, $value, ...)> multi p: ".print_r($p, true));
		return;
	}

	// \rkphplib\lib\log_debug("TFormValidator.parseInName($name, $value, ...)> r: ".print_r($r, true)."\np: ".print_r($p, true));
	$type = $p['type'];

	if (in_array($type, [ 'text', 'pass', 'input', 'password' ])) {
		if (!empty($r[1])) {
			if (mb_substr($r[1], -2) == 'ch') {
				$p['width'] = $r[1];
			}
			else {
				$p['size'] = $r[1];
			}

			unset($r[1]);
		}

		if (!empty($r[2])) {
			$p['maxwidth'] = $r[2];
			unset($r[2]);
		}

		if ($type == 'input') {
			$p['type'] = 'text';
		}
		else if ($type == 'pass') {
			$p['type'] = 'password';
		}
	}
	else if ($type == 'area' || $type == 'textarea') {
		if (!empty($r[1]) && !empty($r[2])) {
			$p['rows'] = $r[1];
			unset($r[1]);

			$p['cols'] = $r[2];
			unset($r[2]);
		}

		if (!empty($r[3])) {
			$p['wrap'] = $r[3];
			unset($r[3]);

			if (!in_array($p['wrap'], [ 'soft', 'hard' ])) {
				throw new Exception('invalid wrap=['.$p['wrap'].'] use soft|hard');
			}
		}

		if ($type == 'area') {
			$p['type'] = 'textarea';
		}
	}
	else if ($type == 'select' || $type == 'fselect') {
		if (!isset($p['value'])) {
			$p['value'] = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
		}

		if ($type == 'fselect') {
			$r['fselect'] = $name;
		}

		$p['options'] = $this->getOptions($r, $p['value'], $value);
	}
	else if ($type == 'const') {
		// ok ...
	}
	else if ($type == 'checkbox') {
		if (count($r) == 0) {
			$p['value'] = 1;
		}
		else {
			$p['value'] = array_shift($r);
		}

		$p['checked'] = isset($_REQUEST[$name]) && $_REQUEST[$name] == $p['value'];
	}
	else if ($type == 'file') {
		if (!empty($r[1])) {
			$p['data-max'] = 8;
		}
	}
  else if ($type == 'set' || $type == 'multi_select') {
		// ToDo ...
  }
	else {
		throw new Exception('ToDo: name='.$name.' p: '.join('|', $p));
	}

	foreach ($r as $key => $value) {
		$p[$key] = $value;
	}

	// \rkphplib\lib\log_debug("TFormValidator.parseInName($name, $value, ...)> p: ".print_r($p, true));
}


/**
 * Return html input. Use [template.engine].in.input for type=[text|password|radio|checkbox|hidden|image|email|...].
 * Use textarea, select for type=[textarea|select]. Attribute keys: size, maxlength, placeholder, type, class, style, 
 * pattern, rows and cols (add value="{:=value}" if undefined). Boolean attributes: readonly, multiple and disabled 
 * (e.g. readonly=1). Other keys: prefix, suffix.
 *
 * @param string $name
 * @param map $ri
 * @return string
 */
protected function getInput($name, $ri) {
	$ri['name'] = isset($ri['multiple']) ? $name.'[]' : $name;
	$ri2 = $ri;

	$conf = $this->conf['current'];

	if (!empty($ri['width'])) {
		$ri['style'] = 'width: '.$ri['width'];
	}

	if (!isset($ri['class'])) {
		$ri['class'] = '';
	}

	if (isset($this->error[$name])) {
		$ri['class'] = empty($ri['class']) ? $this->getConf('error.const', true) : $ri['class'].' '.$this->getConf('error.const', true);
	}

	$tpl_in = $conf['template.engine'].'.in';

	if (empty($ri['type'])) {
		$use = join(', ', array_keys($this->getMapKeys($tpl_in, $conf)));
		throw new Exception("missing form validator type for $name (use $use)", print_r($ri, true));
	}

	if (!empty($conf[$tpl_in.'.'.$ri['type']])) {
		$input = $conf[$tpl_in.'.'.$ri['type']];
	}
	else {
		$input = $conf[$tpl_in.'.input'];
	}

	$tags = '';

	$attributes = [ 'id', 'size', 'maxlength', 'placeholder', 'pattern', 'rows', 'cols', 'style', 'class', 
		'accept', 'onchange', 'onblur' ];
	foreach ($attributes as $key) {
		if (isset($ri[$key]) && !mb_strpos($input, $this->tok->getTag($key))) {
			$tags .= ' '.$key.'="'.$this->tok->getTag($key).'"';
		}
	}

	// data- attributes
	foreach ($ri as $key => $value) {
		if (strpos($key, 'data-') === 0) {
			$tags .= ' '.$key.'="'.$this->tok->getTag($key).'"';
		}
	}

	$boolean_attributes = [ 'readonly', 'multiple', 'disabled', 'checked' ];
	foreach ($boolean_attributes as $key) {
		if (!empty($ri[$key]) && !mb_strpos($input, $this->tok->getTag($key))) {
			$tags .= ' '.$this->tok->getTag($key);
			$ri[$key] = $key;
		}
	}

	// add required attribute
	if (in_array($name, $conf['required'])) {
		$tags .= ' '.$this->tok->getTag('required');
		$ri['required'] = 'required';
	}

	foreach ($ri as $key => $value) {
		if (!in_array($key, [ 'options', 'label' ])) {
			$ri[$key] = \rkphplib\lib\htmlescape($value);
		}
	}

	$ri['tags'] = $tags;
	$input = $this->tok->replaceTags($input, [ 'tags' => $tags ]);

	if (!empty($ri['options']) && strpos($ri['options'], '</option>') === false) {
		$tmp = \rkphplib\lib\conf2kv($ri['options'], '=', ',');
		$ri['options'] = $this->getOptions($tmp, $ri['value'], $ri['options']);
	}

	if ($ri['type'] == 'fselect') {
		$ri2['id'] .= '_';
		$ri2['name'] .= '_';
		$ri2['type'] = 'text';
		$ri2['value'] = '';
		$ri2['onchange'] = "rkphplib.fselectList(this)";
		$ri2['onblur'] = "rkphplib.fselectList(this)";
		$ri['fselect_input'] = $this->getInput($ri2['name'], $ri2);
	}

	$input = $this->tok->replaceTags($input, $ri);

	// \rkphplib\lib\log_debug("TFormValidator.getInput($name, ...)> input=[$input] ri: ".print_r($ri, true));
	return $input;
}


/**
 * Return html options. Options map p is conf2kv result map (unsed keys).
 *
 * @throws
 * @param map-reference $p
 * @param string $opt_value
 * @param string $str_options
 * @return string
 */
private function getOptions(&$p, $opt_value, $str_options) {
	// options are conf2kv result map ...
	$html = '';
	$empty_label = null;

	\rkphplib\lib\log_debug("TFormValidator.getOptions|enter> opt_value=[$opt_value] str_options=[$str_options] p: ".print_r($p, true)); 

	if (!empty($p['@_1']) && substr($p['@_1'], 0, 1) == '=') {
		$empty_label = substr($p['@_1'], 1);
		unset($p['@_1']);
	}

	if (!empty($p['@_2']) && substr($p['@_2'], 0, 1) == '=') {
		$empty_label = substr($p['@_2'], 1);
		unset($p['@_2']);
	}

	if (!is_null($empty_label)) {
		$selected = ($opt_value == '') ? ' selected' : '';
		$empty_label = empty($empty_label) ? $this->conf['current']['option.label_empty'] : $empty_label;
		$html .= '<option value=""'.$selected.'>'.$empty_label."</option>\n";
	}

	if (!empty($p['fselect'])) {
		$name = $p['fselect'];
		unset($p['fselect']);

		if ((empty($_REQUEST[$name.'_']) && !empty($_REQUEST[$name])) ||
				(!empty($_REQUEST[$name.'_']) && $_REQUEST[$name] == $_REQUEST[$name.'_'])) {
			$value = $_REQUEST[$name];

			if (!isset($p[$value])) {
				$p[$value] = $value;
			}
		}
	}

	foreach ($p as $value => $label) {
		unset($p[$value]);

		if (substr($value, 0, 2) == '@_') {
			$value = $label;			
		}

		$selected = ($opt_value == $value) ? ' selected' : '';
		$html .= '<option value="'.$value.'"'.$selected.'>'.$label."</option>\n";
	}

	if (count($p) > 0) {
		throw new Exception('leftover keys', "html=[$html] p: ".print_r($p, true));
	}

	$html = preg_replace('/value\=\"@_[0-9]+\"/', '', $html);

	// \rkphplib\lib\log_debug("getOptions> $html");
	return $html;
}


}

