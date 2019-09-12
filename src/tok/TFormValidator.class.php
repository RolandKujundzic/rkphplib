<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/TokHelper.trait.php';
require_once __DIR__.'/Tokenizer.class.php';
require_once $parent_dir.'/ValueCheck.class.php';
require_once $parent_dir.'/lib/htmlescape.php';
require_once $parent_dir.'/lib/split_str.php';
require_once $parent_dir.'/lib/conf2kv.php';
require_once $parent_dir.'/lib/kv2conf.php';

use rkphplib\Exception;
use rkphplib\tok\Tokenizer;
use rkphplib\ValueCheck;

use function rkphplib\lib\htmlescape;
use function rkphplib\lib\split_str;
use function rkphplib\lib\conf2kv;
use function rkphplib\lib\kv2conf;



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
 * hidden_keep= (default = empty, cs-list)|#|
 * hidden.form_action=1|#|
 * hidden.id= 5|#|
 * col_val= {get:column}:{get:value}|#| (ajax mode)
 * allow_column= login, password, ...|#| (ajax mode)
 * required= login, password|#|
 * check.login= minLength:2|#|
 * {sql_select:}SELECT count(*) AS num FROM {sql:name}{login:@table}{:sql}
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
 * Register plugins. Return {fv:init|conf|get|get_conf|check|in|tpl|hidden|preset|error|appendjs|error_message|set_error_message} 
 *
 * @tok {fv:init:[|add]}required=...|#|...{:fv}
 * @tok {fv:in:name}label=...|#|...{:fv}
 * @tok {fv:check}
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [];
	$plugin['fv'] = 0;
	$plugin['fv:init'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY; 
	$plugin['fv:conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY | TokPlugin::TEXT;
	$plugin['fv:get'] = TokPlugin::REQUIRE_PARAM;
	$plugin['fv:get_conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['fv:check'] = TokPlugin::NO_BODY; 
	$plugin['fv:in'] = TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY | TokPlugin::REDO;
	$plugin['fv:tpl'] = TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY | TokPlugin::REDO;
	$plugin['fv:hidden'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['fv:preset'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['fv:error'] = TokPlugin::REQUIRE_PARAM;
	$plugin['fv:appendjs'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::CSLIST_BODY;
	$plugin['fv:error_message'] = TokPlugin::REQUIRE_PARAM;
	$plugin['fv:emsg'] = TokPlugin::REQUIRE_PARAM;
	$plugin['fv:set_error_message'] = TokPlugin::REQUIRE_PARAM;

  return $plugin;
}


/**
 * Set default configuration. Configuration Parameter:
 *
 * template.engine= default|bootstrap|material
 * ENGINE.in.[const|input|textarea|select|file|multi_checkbox|multi_radio|fselect] = ...
 * 
 * Render ENGINE.in.TYPE, label and ERROR_MESSAGE into template.output.TEMPLATE.OUTPUT
 */
public function __construct() {
	// TAGS
	$label = TAG_PREFIX.'label'.TAG_SUFFIX;
	$label2 = TAG_PREFIX.'label2'.TAG_SUFFIX;
	$input = TAG_PREFIX.'input'.TAG_SUFFIX;
	$error = TAG_PREFIX.'error'.TAG_SUFFIX;
	$error_message = TAG_PREFIX.'error_message'.TAG_SUFFIX;
	$example = TAG_PREFIX.'example'.TAG_SUFFIX;
	$id = TAG_PREFIX.'id'.TAG_SUFFIX;
	$type = TAG_PREFIX.'type'.TAG_SUFFIX;
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
	$checked = TAG_PREFIX.'checked'.TAG_SUFFIX;
	$fselect_input = TAG_PREFIX.'fselect_input'.TAG_SUFFIX;

	$tok = is_null($this->tok) ? Tokenizer::$site : $this->tok;
	if (is_null($tok)) {
		$tok = new Tokenizer();
	}

	// PLUGINS with escaped HASH_DELIMITER
	$d = HASH_DELIMITER;
	$pl_get = $tok->getPluginTxt([ 'get', SETTINGS_REQ_DIR ]);
	$pl_link = $tok->getPluginTxt([ 'link', '' ], '_=');
	$pl_if_method = $tok->getPluginTxt([ 'if', '' ], $method.$d.$method.$d.'get');
	$pl_if_upload = $tok->getPluginTxt([ 'if', '' ], $upload.$d.'enctype="multipart/form-data"');
	$pl_fv_hidden = $tok->getPluginTxt([ 'fv', 'hidden' ], null);
	$pl_if_label2 = $tok->getPluginTxt([ 'if', '' ], $label2.$d.
		'<button type="submit" name="form_action" value="2">'.$label2.'</button>');
	$pl_if_label2_btn = $tok->getPluginTxt([ 'if', '' ], $label2.$d.
		'<button type="submit" name="form_action" value="2" class="btn">'.$label2.'</button>');
	$pl_if_yes_fv_check_fadeout = $tok->getPluginTxt([ 'if', 'cmp:yes' ], $tok->getPluginTxt([ 'fv', 'check' ], '').$d.
		'<h4 style="color:#006600" data-effect="fadeout">'.$fadeout_confirm."</h4><script>".
		'setTimeout(function() { $('."'h4[data-effect=\"fadeout\"]').fadeOut(); }, 2000);</script>");
	$pl_if_col = $tok->getPluginTxt([ 'if', '' ], '{:=col}'.$d.$col.$d.'col-md-12');

	$this->conf['default'] = [
		'submit' 					=> 'form_action',
		'id_prefix'				=> 'fvin_',
		'label_required'	=> '<div class="label_required">'.$label.'</div>',
		'template.engine' => 'default',
		'option.label_empty'  => '',

		'show_error_message' 	=> 1,
		'show_error' 					=> 1,
		'show_example' 				=> 1,

		'default.in.const'    => '<span class="const">'.$value.'</span>',
		'default.in.input'    => '<input type="'.$type.'" name="'.$name.'" value="'.$value.'" class="'.$class.'" '.$tags.'>',
		'default.in.textarea' => '<textarea name="'.$name.'" class="'.$class.'" '.$tags.'>'.$value.'</textarea>',
		'default.in.select'   => '<select name="'.$name.'" class="'.$class.'" '.$tags.'>'.$options.'</select>',
		'default.in.file_btn'	=> '<div class="file_btn_wrapper"><button class="file_btn" '.$tags.'>'.$label2.'</button>'.
															'<input type="file" name="'.$name.'" style="opacity:0;position:absolute;right:0;left:0;top:0;bottom:0;" '.
															'data-value="'.$value.'"></div>',
		'default.in.file'			=> '<input type="file" name="'.$name.'" class="'.$class.'" data-value="'.$value.'" '.$tags.'>',
		'default.in.multi_checkbox'	=> '<div class="multi_checkbox_wrapper">'.$input.'</div>',
		'default.in.multi_checkbox.entry' => '<div class="multi_checkbox"><span>'.$input.'</span><span>'.$label.'</span></div>',
		'default.in.fselect'  => '<span id="fselect_list_'.$name.'"><select name="'.$name.'" class="'.$class.'" '.
			'onchange="rkphplib.fselectInput(this)" '.$tags.'>'.$options.'</select></span>'.
			'<span id="fselect_input_'.$name.'" style="display:none">'.$fselect_input.'</span>',
		'default.in.check'		=> '<div class="check_wrapper">'.$options.'</div>',
		'default.in.check.option' => '<label for="'.$id.'"><input id="'.$id.'" type="'.$type.'" name="'.
			$name.'" class="'.$class.'" value="'.$value.'" '.$checked.'>'.$label.'</label>',

		'default.error.message'					=> '<span class="error_message">'.$error.'</span>',
		'default.error.message_concat'	=> ', ',
		'default.error.message_multi'		=> "<i>$name</i>: <tt>$error</tt><br class=\"fv\" />",
		'default.error.const'						=> 'error',

		'default.output.in'				=> '<span class="label '.$error.'">'.$label.'</span>'.$input.$example.$error_message.'<br class="fv" />',

		'default.output.in.cbox_query' => $input.'<span class="cbox_query '.$error.'">'.$label.'</span><br class="fv" />',		

		'default.output.in.multi'	=> '<span class="label '.$error.'">'.$label."</span>$input".
			'<div class="example_error_wrapper">'.$example.$error_message."</div>",

		'default.example'	=> '<span class="example">'.$example.'</span>',

		'default.header'	=> '<form class="fv" action="'.$pl_link.'" method="'.$pl_if_method.'" '.$pl_if_upload.
			' data-key13="prevent" novalidate>'.$pl_fv_hidden,

		'default.form'		=> '<form class="fv {:=class}" action="'.$pl_link.'" method="'.$pl_if_method.'" '.$pl_if_upload.
			' data-key13="prevent" novalidate>'.$pl_fv_hidden,

		'default.footer'	=> '<button type="submit" class="{:=class}">'.$label.'</button>'.
			'<div class="label2">'.$pl_if_label2.$pl_if_yes_fv_check_fadeout."</div></form>",

		'default.submit'	=> '<button type="submit" class="{:=class}">'.$label.'</button>',


		'bootstrap.in.input'	  => '<input type="'.$type.'" name="'.$name.'" value="'.$value.'" class="form-control '.$class.'" '.$tags.'>',
		'bootstrap.in.checkbox'	=> '<input type="checkbox" name="'.$name.'" value="'.$value.'" class="form-check-input '.$class.'" '.$tags.'>',
		'bootstrap.in.radio'	  => '<input type="radio" name="'.$name.'" value="'.$value.'" class="form-check-input '.$class.'" '.$tags.'>',
		'bootstrap.in.file'     => '<input class="form-control-file '.$class.'" name="'.$name.'" type="file" data-value="'.$value.'" '.$tags.'>',
		'bootstrap.in.textarea' => '<textarea name="'.$name.'" class="form-control '.$class.'" '.$tags.'>'.$value.'</textarea>',
		'bootstrap.in.select'   => '<select name="'.$name.'" class="form-control '.$class.'" '.$tags.'>'.$options.'</select>',
		'bootstrap.in.multi_checkbox' => '<div class="row">'.$input.'</div>',
		'bootstrap.in.multi_checkbox.entry'	=> '<div class='.$col.'><label class="form-check-label">'.$input.' '.$label.'</label></div>',
		'bootstrap.in.fselect'  => '<span id="fselect_list_'.$name.'"><select name="'.$name.'" class="form-control '.$class.'" '.
			'onchange="rkphplib.fselectInput(this)" '.$tags.'>'.$options.'</select></span>'.
			'<span id="fselect_input_'.$name.'" style="display:none">'.$fselect_input.'</span>',
		
		'bootstrap.in.check'		=> '<div class="form-check-inline">'.$options.'</div>',

		'bootstrap.in.check.option' => '<label class="form-check-label" for="'.$id.'"><input id="'.$id.'" type="'.$type.'" name="'.
			$name.'" class="form-check-input '.$class.'" value="'.$value.'" '.$checked.'>'.$label.'</label>',

		'bootstrap.error.const'	=> 'is-invalid',

		'bootstrap.output.in'		=> '<div class="form-group {:=class} '.$error.'"><label for="'.$id.'">'.$label.'</label>'.
			"$example$error_message$input</div>",

		'bootstrap.output.in.multi'		=> '<div class="row"><div class="col-md-3"><label>'.$label.
			"</label>$example$error_message</div>".'<div class="col-md-9">'.$input.'</div></div>',

		'bootstrap.output.in.multi.2'	=> '<div class="row"><div class="col-md-6"><label>'.$label.'</label></div>'.
			'<div class="col-md-6">'.$example.$error_message.'</div></div><div class="row"><div class="col-md-12">'.$input.'</div></div>',

		'bootstrap.header'	=> '<div class="container-fluid ml-0 pl-0 {:=class}"><div class="row"><div class="'.$pl_if_col.'">'.
			'<form class="fv form" method="'.$pl_if_method.'" action="'.$pl_link.'" '.$pl_if_upload.' data-key13="prevent" novalidate>'.
			$pl_fv_hidden,
	
		'bootstrap.footer'	=> '<div class="row"><div class="col-md-4"><button type="submit" class="btn">'.$label.'</button></div>'.
			'<div class="col-md-8">'.$pl_if_label2_btn.$pl_if_yes_fv_check_fadeout."</div></div></form></div></div></div>",

		'bootstrap.submit'	=> '<button type="submit" class="btn">'.$label.'</button>',

		'bootstrap.example'	=> '<span class="example">'.$example.'</span>',


		'material.in.input'	   => '<input type="'.$type.'" name="'.$name.'" value="'.$value.
			'" class="mdl-textfield__input '.$class.'" '.$tags.'>',

		'material.in.file'     => '<input class="mdl-textfield__input '.$class.'" name="'.$name.'" type="file" data-value="'.
			$value.'" '.$tags.'>',

		'material.in.textarea' => '<textarea name="'.$name.'" class="mdl-textfield__input '.$class.'" '.$tags.'>'.$value.'</textarea>',

		'material.in.select'   => '<select name="'.$name.'" class="mdl-textfield__input '.$class.'" '.$tags.'>'.$options.'</select>',

		'material.in.fselect'  => '<span id="fselect_list_'.$name.'"><select name="'.$name.'" class="mdl-textfield__input '.$class.'" '.
			'onchange="rkphplib.fselectInput(this)" '.$tags.'>'.$options.'</select></span>'.
			'<span id="fselect_input_'.$name.'" style="display:none">'.$fselect_input.'</span>',

		'material.in.checkbox' => '<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="'.$id.'"><input type="checkbox" '.
			'id="'.$id.'" name="'.$name.'" value="'.$value.'" class="mdl-checkbox__input mdl-js-ripple-effect '.$class.'" '.$tags.
			'><span class="mdl-checkbox__label">'.$label.'</span></label>'
		];

		if (isset($_REQUEST[SETTINGS_REQ_DIR])) {
			$this->conf['default']['hidden.dir'] = $_REQUEST[SETTINGS_REQ_DIR];
		}
}


/**
 * Return FormData append list.
 *
 * @tok {fv:appendjs:formData}firstname,...{:fv} = formData.append("firstname", document.getElementById("fvin_firstname").value); ...
 * @tok {fv:appendjs:formData} = autodetect arg e.g. id, dir, ...
 */
public function tok_fv_appendjs(string $name, array $id_list = []) : string {
	$conf = $this->conf['current'];
	if (count($conf) == 0) {
		$this->tok_fv_init('', []);
		$conf = $this->conf['current'];
	}

	$id_prefix = $conf['id_prefix'];
	$list = [];

	if (count($id_list) > 0) {
		// \rkphplib\lib\log_debug("TFormValidator.tok_fv_appendjs:308> name=$name id_list: ".print_r($id_list, true));
		foreach ($id_list as $ignore => $param) {
			array_push($list, $name.'.append("'.$param.'", document.getElementById("'.$id_prefix.$param.'").value);');
		}
	
		$res = join("\n", $list);
		// \rkphplib\lib\log_debug("TFormValidator.tok_fv_appendjs:314> return ".$res);
		return $res;
	}

	// use all parameter
	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_appendjs:319> name=$name conf: ".print_r($conf, true));

	foreach ($conf as $key => $ignore) {
		$param = '';

		// \rkphplib\lib\log_debug("TFormValidator.tok_fv_appendjs:324> $name: $key");
		if (substr($key, 0, 3) == 'in.') {
			$param = substr($key, 3);
		}
		else if (substr($key, 0, 7) == 'hidden.') {
			$param = substr($key, 7);
		}

		if ($param) {
			array_push($list, $name.'.append("'.$param.'", document.getElementById("'.$id_prefix.$param.'").value);');
		}
	}

	if (!empty($conf['hidden_keep'])) {
		$hidden_keys = split_str(',', $conf['hidden_keep']);
		foreach ($hidden_keys as $key) {
			array_push($list, $name.'.append("'.$key.'", document.getElementById("'.$id_prefix.$key.'").value);');
		}
	}

	$res = join("\n", $list);
	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_appendjs:345> return ".$res);
	return $res;
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
 */
public function tok_fv_preset(string $arg) : string {
	$skey = $this->getConf('submit');

	if (!empty($_REQUEST[$skey])) {
		return '';
	}

	if (!empty($_REQUEST['id']) && $_REQUEST['id'] == 'add') {
		return '';
	}

	return $arg;
}


/**
 * Should not be called. Always throw exception.
 */
public function tok_fv(string $param, string $arg) : string {
	throw new Exception("no such plugin [fv:$param]...[:fv]", "param=[$param] arg=[$arg]");
}


/**
 * Set error message (if $msg is not empty).
 */
public function tok_fv_set_error_message(string $name, string $msg) : void {
	if (empty(trim($msg))) {
		return;
	}

	if (!isset($this->error[$name])) {
		$this->error[$name] = [];
	}

	array_push($this->error[$name], $msg);
}


/**
 * Return hidden input for conf.hidden_keep and conf.hidden.key.
 */
public function tok_fv_hidden() : string {
	$res = '';

	$id_prefix = $this->getConf('id_prefix', '', true);

	if (!empty($hidden_keep = $this->getConf('hidden_keep', '', false))) {
		$list = split_str(',', $hidden_keep);
		foreach ($list as $key) {
			if (isset($_REQUEST[$key])) {
				$res .= '<input type="hidden" id="'.$id_prefix.$key.'" name="'.$key.'" value="'.htmlescape($_REQUEST[$key]).'">'."\n";
			}
		}
	}

	foreach ($this->conf['current'] as $key => $value) {
		if (mb_substr($key, 0, 7) == 'hidden.') {
			$key = mb_substr($key, 7);
			$res .= '<input type="hidden" id="'.$id_prefix.$key.'" name="'.$key.'" value="'.htmlescape($value).'">'."\n";
		}
	}

	return $res;
}


/**
 * Return configuration key value.
 *
 * @tok {fv:get:submit} = form_action
 * @tok {fv:get:KEY@ENGINE}
 * @tok {fv:get:REQUIRED_KEY!} throws if not found
 */
public function tok_fv_get(string $name) : string {
	$required = substr($name, -1) == '!';
	if ($required) {
		$name = substr($name, 0, -1);
	}

	$engine = '';
	if (strpos($name, '@') > 0) {
		list ($name, $engine) = explode('@', $name, 2);
	}

	return $this->getConf($name, $engine, $required);
}


/**
 * Return configuration as string hash.
 *
 * @tok {fv:get_conf:default} 
 */
public function tok_fv_get_conf(string $engine) : string {
	$conf = (isset($this->conf['current']) && count($this->conf['current']) > 0) ? $this->conf['current'] : $this->conf['default'];
	$name_keys = $this->getMapKeys($engine, $conf);
	$res = [];

	foreach ($conf as $key => $value) {
		if (strpos($key, '.') === false) {
			$res[$key] = $value;
		}
		else if (strpos($key, 'template.') === 0) {
			$res[$key] = $value;
		}
		else if (strpos($key, 'option.') === 0) {
			$res[$key] = $value;
		}
	}

	if (!is_array($name_keys) || count($name_keys) == 0) {
		throw new Exception("no $engine.* keys");
	}

	foreach ($name_keys as $key => $value) {
		$res[$engine.'.'.$key] = $value;
	}

	return kv2conf($res);
}


/**
 * Set named configuration. Load configuration with use=name or use
 * name=default to overwrite default configuration. Use submit=NEW_KEY
 * to reset form.
 */
public function tok_fv_conf(string $name, array $p) : void {
	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_conf:511> this.conf[$name] = ".print_r($p, true));
	$this->conf[$name] = $p;
}


/**
 * Initialize form validator. Keys:
 *
 * - use: default (list of names configurations)
 * - submit: form_action (use NEW_KEY to reset form)
 * - option.label_empty: ...
 */
public function tok_fv_init(string $do, array $p) : void {

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
		// \rkphplib\lib\log_debug("TFormValidator.tok_fv_init()> reset, do=$do p: ".print_r($p, true));
		$this->conf['current'] = [];
		$this->error = [];
		$this->example = [];
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

	$submit_name = $this->conf['current']['submit'];
	$this->conf['current']['hidden.'.$submit_name] = 1;
	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_init($do, ...)> exit, use_conf=(".join(',', $use_conf)."), current: ".print_r($this->conf['current'], true));
}


/**
 * Return validation result (yes|error|). Call get2NData() if multi_checkbox|radio input exists.
 * If _REQUEST[conf[submit]] is empty do nothing. Apply all conf[check.*] value checks.
 *
 * @tok {fv_check:} -> [|yes|error]
 */
public function tok_fv_check(string $ajax = '') : string {
	$submit = $this->getConf('submit');

	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_check> submit=$submit _REQUEST: ".print_r($_REQUEST, true));
	foreach ($this->conf['current'] as $key => $value) {
		if (substr($key, 0, 3) == 'in.' && (substr($value, 0, 14) == 'multi_checkbox' || substr($value, 0, 11) == 'multi_radio')) {
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

	if (empty($this->conf['current']['col_val'])) {
		foreach ($this->conf['current']['required'] as $key) {
			if (!isset($_REQUEST[$key]) || mb_strlen($_REQUEST[$key]) == 0) {
				if (!isset($this->error[$key])) {
					$this->error[$key] = [];
				}

				array_push($this->error[$key], 'required');
			}
		}
	}
	else {
		$col_val = trim($this->conf['current']['col_val']);

		if (substr($col_val, 0, 1) == ':') {
			$this->error['parameter'] = [ 'empty column name' ];
		}

		list ($column, $value) = explode(':', $col_val, 2);

		if (mb_strlen($value) == 0 && in_array($column, $this->conf['current']['required'])) {
			if (!isset($this->error[$column])) {
				$this->error[$column] = [];
			}

			array_push($this->error[$column], 'required');
		}

		if (!empty($this->conf['current']['allow_column'])) {
			$allow_col = \rkphplib\lib\split_str(',', $this->conf['current']['allow_column']);

			// \rkphplib\lib\log_debug("TFormValidator->tok_fv_check> column=$column allow_col: ".print_r($allow_col, true));
			if (!in_array($column, $allow_col)) {
				$this->error['parameter'] = [ $column.' is immutable' ];
			}
		}
	}

	foreach ($this->conf['current'] as $key => $check) {
		$path = explode('.', $key);

		if ($path[0] == 'check') {
			$name = $path[1];
			$req_value = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';

			$is_ok = ValueCheck::run($name, $req_value, $check);

			if (!$is_ok && !empty($req_value) && $req_value != trim($_REQUEST[$name])) {
				if (ValueCheck::run($name, trim($req_value), $check)) {
					// \rkphplib\lib\log_debug("TFormValidator->tok_fv_check> auto-trim $name value [$req_value]");
					$_REQUEST[$name] = trim($_REQUEST[$name]);
					$is_ok = true;
				}
			}

			if (!$is_ok) {
				if (!isset($this->error[$name])) {
					$this->error[$name] = [];
				}

				$this->setExample($name, $check);
				array_push($this->error[$name], $this->getErrorMessage($path));
				// \rkphplib\lib\log_debug("TFormValidator->tok_fv_check> path=$key name=$name error: ".print_r($this->error[$name], true));
			}
		}
	}

	if (count($this->error) > 0 && !empty($this->conf['current']['col_val'])) {
		print $this->tok->callPlugin('tpl', 'fv_error');
		exit(0);
	}

	$res = (count($this->error) == 0) ? 'yes' : 'error';

	if ($res == 'yes' && $ajax) {
		$this->ajaxOutput($ajax);
	}

	return $res;
}


/**
 * Execute tpl:$ajax and exit.
 */
private function ajaxOutput(string $ajax) : string {
	try {
		$output = $this->tok->callPlugin('tpl', $ajax);
		// \rkphplib\lib\log_debug("TFormValidator.ajaxOutput($ajax)> $output");
		http_response_code(200);
		print $output;
		exit(0);
	}
	catch (\Exception $e) {
		Exception::httpError(400, "@ajax catch Exception in TFormValidator.ajaxOutput(): ".$e->getMessage());
	}
}


/**
 * Set example. Use conf.example.$name_$check= example if set.
 */
private function setExample(string $name, string $check) : void {
	$map = [ 'isMobile' => '+49176123456' ];

	$example = '';

	if (!empty($this->conf['current']['example.'.$name.'_'.$check])) {
		$example = $this->conf['current']['example.'.$name.'_'.$check];
	}
	else if (!empty($map[$check])) {
		$example = $map[$check];
	}

	if (empty($this->example[$name])) {
		// do not overwrite existing example with empty one
		$this->example[$name] = $example;
	}
}


/**
 * Return error message (error.path[1..2]) for $path ([ check, name, 1 ] = check.name.1). 
 * Overwrite default error message 'invalid' with conf[error.CHECK_NAME]. Example:
 *
 * check.login.1= isUnique:{login:@table}:...|#|
 * error.login.1= {txt:}already taken{:txt}|#|
 */
protected function getErrorMessage(array $path) : string {
	$ignore = array_shift($path);
	$key = 'error.'.join('.', $path);
	return empty($this->conf['current'][$key]) ? 'invalid' : $this->conf['current'][$key];
}


/**
 * Return "error" (= ENGINE.error.const or $tpl if set) if $name check failed.
 */
public function tok_fv_error(string $name, string $tpl) : string {
	if (!isset($this->error[$name])) {
		return '';
	}

	return empty($tpl) ? $this->getConf('error.const', true) : $tpl;
}


/**
 * Alias for {fv:error_message}.
 * @see tok_fv_error_message
 */
public function tok_fv_emsg(string $name, string $tpl) : string {
	return $this->tok_fv_error_message($name, $tpl);
}


/**
 * Return error message. Replace {:=name} and {:=error} in ENGINE.error.message[_multi] (overwrite with $tpl). If there are
 * multiple errors concatenate ENGINE.error.message with ENGINE.error.message_concat.
 * Use name=* to return all error messages (concatenate ENGINE.error.message_multi).
 * Define default.error.message_none (with {:=msg} tag) to show no-errors and use $tpl as error message if error. 
 */
public function tok_fv_error_message(string $name, string $tpl) : string {
	$res = '';

	if ($name == '*') {
		if (empty($tpl)) {
			$tpl = $this->getConf('error.message_multi', true);
		}

		foreach ($this->error as $key => $value) {
			$res .= $this->tok_fv_error_message($key, $tpl);
		}

		return $res;
	}

	// no error mode - only if $tpl is not empty
	$no_error_tpl = empty($tpl) ? '' : $this->getConf('error.message_none', true, false);

	if (isset($this->error[$name])) {
		if (!empty($no_error_tpl)) {
			$error_list = [ $tpl ];
			$tpl = $this->getConf('error.message', true);
		}
		else {
			if (empty($tpl)) {
				$tpl = $this->getConf('error.message', true);
			}

			$error_list = $this->error[$name];
		}

		if (!is_array($error_list)) {
			throw new Exception('invalid error list', print_r($error_list, true));
		}

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
	else if (!empty($no_error_tpl)) {
		$res = $this->tok->replaceTags($no_error_tpl, [ 'msg' => $tpl, 'name' => $name ]);
	}

	// \rkphplib\lib\log_debug("tok_fv_error_message($name, ...)> name=[$name] res=[$res] - error: ".print_r($this->error, true));
	return $res;
}


/**
 * Return conf.template.$name. Replace and remove tags.
 * If template contains [<form class="] add submit key to class.
 *
 * @tok {fv:tpl:header}method=post|#|upload=1{:fv}
 * @tok {fv:tpl:footer}label=absenden{:fv}
 */
public function tok_fv_tpl(string $name, array $replace) : string {
	$res = $this->getConf($name, true);

	if (stripos($res, '<form class="') !== false) {
		if (!empty($replace['class'])) {
			$res = str_replace('class="fv"', 'class="'.$replace['class'].'"', $res);
			unset($replace['class']);
		}

		$res = str_replace('<form class="', '<form class="'.$this->getConf('submit', '', true).' ', $res);
	}

	$res = $this->tok->removeTags($this->tok->replaceTags($res, $replace));
	// \rkphplib\lib\log_debug("tok_fv_tpl($name, ...)> $res");
	return $res;
}


/**
 * Transform 2^N data definition. Example:
 * 
 * (name_def) in.interior= multi_checkbox, Klima, AHK, Radio
 * (return) multi_checkbox, interior0=Klima, interior1=AHK, interior2=Radio 
 *
 * (name_def) in.interior= multi_radio, Klima, AHK, Radio
 * (return) multi_radio, interior0=Klima, interior1=AHK, interior2=Radio 
 */
private function get2NData(string $name, string $name_def) : string {
	// [0] => multi_checkbox|radio, [1] => value_2^0, [2] => value_2^1, ...
	$r = \rkphplib\lib\conf2kv($name_def, '=', ',');

	if (isset($r['@_1'])) {
		$r[0] = $r['@_1'];
		unset($r['@_1']);
	}

	if (!isset($r[0]) || ($r[0] != 'multi_checkbox' && $r[0] != 'multi_radio')) {
		throw new Exception('invalid value of conf.in.'.$name, "$name=[$name_def] r: ".print_r($r, true));
	}

	$r['type'] = substr($r[0], 6);
	$r['multi'] = 1;
	unset($r[0]);

	// \rkphplib\lib\log_debug("TFormValidator.get2NData($name, ...)> r: ".print_r($r, true));
	$value = isset($_REQUEST[$name]) ? intval($_REQUEST[$name]) : null;
	$done = false;
	$n = 1;

	$is_checkbox = $r['type'] == 'checkbox';

	while (!empty($r[$n]) && $n < 33) {
		$var = $name.($n - 1);
		$v = pow(2, ($n - 1));

		$r[$var] = $r[$n];
		unset($r[$n]);
       
		if (!empty($_REQUEST[$var]) && $_REQUEST[$var] == $v) {
			$value += $v;
		}
		else if (empty($_REQUEST[$var]) && ($value & $v) == $v) {
			$_REQUEST[$var] = $v;
		}

		$n++;
	}

	$r['n_max'] = $n - 1;
	$res = \rkphplib\lib\kv2conf($r, '=', ',');

	if (!is_null($value)) {
		$_REQUEST[$name] = $value;
	}

	// \rkphplib\lib\log_debug("TFormValidator.get2NData($name, ...)> value=[$value] res=[$res] r: ".print_r($r, true));
	return $res;
}


/**
 * Return multi-checkbox|radio html.
 */
private function multiCheckbox(string $name, array $p) : string {
	// \rkphplib\lib\log_debug("TFormValidator.multiCheckbox($name, ...)> name=$name p: ".print_r($p, true));
	$col = empty($p['col']) ? 'col-md-4' : $p['col'];
	$entry = $this->tok->replaceTags($this->getConf('in.multi_checkbox.entry', true), [ 'col' => $col ] );
	$entries = $this->getConf('in.multi_checkbox', true);
	$entry_list = '';

	$is_checkbox = $p['type'] == 'checkbox';
	$value = 0;

	// \rkphplib\lib\log_debug("TFormValidator.multiCheckbox($name, ...)> entry=[$entry] entries=[$entries]");
	for ($n = 0; $n < $p['n_max']; $n++) {
		$var = $name.$n;

		$r = [];
		$r['type'] = $p['type'];
		$r['value'] = pow(2, $n);
		$input_name = $is_checkbox ? $var : $name;

		if (!empty($_REQUEST[$input_name]) && $_REQUEST[$input_name] == $r['value']) {
			$value += $r['value'];
			$r['checked'] = 'checked';
		}

		$html = $this->getInput($input_name, $r);
		// \rkphplib\lib\log_debug("TFormValidator.multiCheckbox> var=$var input_name=$input_name r: ".join('|', $r).": $html");
		$entry_list .= $this->tok->replaceTags($entry, [ 'input' => $html, 'label' => $p[$var] ]);
	}

	if ($value > 0) {
		$_REQUEST[$name] = $value;
	}

	$p['input'] = $this->tok->replaceTags($entries, [ 'input' => $entry_list ]);

	return $this->_fv_in_html($name, $p, '.multi');
}


/**
 * Return {fv:in:$name}label=....{:} html.
 */
private function _fv_in_html(string $name, array $r, string $output_in = '') : string {
	$conf = $this->conf['current'];

	$output_tpl = $this->getConf('output.in'.$output_in, true) ? $this->getConf('output.in'.$output_in, true) : 
		$this->getConf('output.in', true);

	$res = empty($r['output']) ? $output_tpl : $this->getConf('output.in.'.$r['output'], true);

	if (!empty($conf['label_required']) && !empty($r['label']) && in_array($name, $conf['required'])) {
		$r['label'] = $this->tok->replaceTags($conf['label_required'], [ 'label' => $r['label'] ]);
	}

	if (!empty($conf['show_error_message'])) {
		$r['error_message'] = $this->tok_fv_error_message($name, '');
	}

	if (!empty($conf['show_error'])) {
		$r['error'] = isset($this->error[$name]) ? $this->getConf('error.const', true) : '';
	}

	if (!empty($conf['show_example'])) {
		$r['example'] = empty($this->example[$name]) ? '' :
		$this->tok->replaceTags($this->getConf('example', true), [ 'example' => $this->example[$name] ]);
	}

	$res = $this->tok->removeTags($this->tok->replaceTags($res, $r));
	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_in($name, ...)> res=[$res] r: ".print_r($r, true));
	return $res;
}


/**
 * Show input for $name. Default input template conf[ENGINE.in.$type].
 * Default output template is conf[ENGINE.output.in]. If $p[output] is not empty 
 * use conf[ENGINE.in.OUTPUT]. If there are multiple forms on same page,
 * set _REQUEST[use_FORM_ACTION]=1 to enable specific form.
 */
public function tok_fv_in(string $name, array $p) : string {
	$conf = $this->conf['current'];

	$skey = $conf['submit'];
	$is_action = !empty($_REQUEST[$skey]);

	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_in($name, ...)> key=$skey is_action=$is_action p: ".print_r($p, true));
	if (!$is_action && (isset($p['value']) || isset($_REQUEST[$name])) && $skey != 'form_action' && !isset($_REQUEST['use_'.$skey])) {
		$p['value'] = '';
	}

	if (!empty($conf['in.'.$name])) {
		$this->parseInName($name, $conf['in.'.$name], $p);
	}
	else if (!empty($p['in'])) {
		$this->parseInName($name, $p['in'], $p);
	}

	if (!empty($p['multi'])) {
		return $this->multiCheckbox($name, $p);
	}

	if (!isset($p['value'])) {
		$p['value'] = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
	}

	if (empty($p['id'])) {
		$p['id'] = $conf['id_prefix'].$name;
	}

	if (!isset($p['type'])) {
		throw new Exception('define [fv:init]in.'.$name.'= ...');
	}

	if ($p['type'] == 'const') {
		if (is_null($p['value']) || $p['value'] == 'NULL' || !empty($p['is_null'])) {
			return '';
		}
	}

	$p['input'] = $this->getInput($name, $p);

	return $this->_fv_in_html($name, $p);
}


/**
 * Return configuration key. Use 1|true for template.engine or name engine.
 */
public function getConf(string $key, string $engine = '', bool $required = true) : string {
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

	// \rkphplib\lib\log_debug("TFormValidator.getConf($key, $engine, $required)> ckey = $ckey");
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
			$msg = (count($conf) == 0) ? 'empty configuration' : 'no template.engine';
			throw new Exception("no such configuration key $ckey", $msg);
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
 *      instead of text you can use: date, email, datetime-local, url, color, 
 *      with date, datetime-local, number you can use min, max too
 *  - in.name= pass(=input),
 *  - in.name= file,
 *  - in.name= select,
 *  - in.name= fselect,
 *  - in.name= set,
 *  - in.name= multi_select,
 *  - in.name= checkbox_hash|radio_hash, key=value, key2=value2, ...
 */
protected function parseInName(string $name, string $value, array &$p) : void {
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
		// \rkphplib\lib\log_debug("TFormValidator.tok_fv_init:554> name=$name, value=$value, multi p: ".print_r($p, true));
		return;
	}

	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_init:558> name=$name, value=$value, r: ".print_r($r, true)."\np: ".print_r($p, true));
	$html5_input = [ 'text', 'password', 'email', 'date', 'datetime-local', 'color', 'number', 'month', 'range', 'tel', 'time', 'url', 'week' ];
	$type = $p['type'];

	if (in_array($type, $html5_input) || $type == 'pass' || $type == 'input') {
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
	else if ($type == 'checkbox_hash' || $type == 'radio_hash') {
		$options = $this->getCheckOptions($r, $name, $value);
		$p['tpl_in'] = $this->tok->replaceTags($this->getConf('in.check', true, true), [ 'options' => $options ]);
	}
	else if ($type == 'file' || $type == 'file_btn') {
		if (!empty($r[1])) {
			$p['data-max'] = 8;
		}
	}
  else if ($type == 'set' || $type == 'multi_select') {
		// ToDo ...
  }
	else if ($type == 'checkbox') {
		$p['output'] = 'cbox_query';
		$p['type'] = 'checkbox';
		$p['value'] = 1;
		$p['checked'] = !empty($_REQUEST[$name]) && $_REQUEST[$name] == 1;
	}
	else {
		throw new Exception("ToDo: name=$name type=$type p: ".join('|', $p));
	}

	foreach ($r as $key => $value) {
		$p[$key] = $value;
	}

	// \rkphplib\lib\log_debug("TFormValidator.tok_fv_init:648> name=$name, value=$value, p: ".print_r($p, true));
}


/**
 * Return html input. Use [template.engine].in.input for type=[text|password|radio|checkbox|hidden|image|email|...].
 * Use textarea, select for type=[textarea|select]. Attribute keys: size, maxlength, placeholder, type, class, style, 
 * pattern, rows and cols (add value="{:=value}" if undefined). Boolean attributes: readonly, multiple and disabled 
 * (e.g. readonly=1). Other keys: prefix, suffix.
 */
protected function getInput(string $name, array $ri) : string {
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

	// \rkphplib\lib\log_debug("TFormValidator.getInput:682> name=$name, tpl_in=[$tpl_in] ri: ".print_r($ri, true));
	if (empty($ri['type'])) {
		$use = join(', ', array_keys($this->getMapKeys($tpl_in, $conf)));
		throw new Exception("missing form validator type for $name (use $use)", print_r($ri, true));
	}

	if (!empty($ri['tpl_in'])) {
		$input = $ri['tpl_in'];
		// \rkphplib\lib\log_debug("TFormValidator.getInput:690> name=$name, tpl_in=$tpl_in: $input");
		unset($ri['tpl_in']);
	}
	else if (!empty($conf[$tpl_in.'.'.$ri['type']])) {
		$input = $conf[$tpl_in.'.'.$ri['type']];
	}
	else {
		$input = $this->getConf('in.input', true);
	}

	$tags = '';

	foreach ($conf as $key => $value) {
		if (substr($key, 0, 4) == 'add.') {
			$key = substr($key, 4);
			$ri[$key] = empty($ri[$key]) ? $value : $ri[$key].'; '.$value;
		}
	}

	$attributes = [ 'id', 'size', 'maxlength', 'placeholder', 'pattern', 'rows', 'cols', 'style', 'class', 
		'accept', 'onchange', 'onblur', 'autocomplete' ];
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
			$ri[$key] = htmlescape($value);
		}
	}

	$ri['tags'] = $tags;
	$input = $this->tok->replaceTags($input, [ 'tags' => $tags ]);

	if (!empty($ri['options']) && strpos($ri['options'], '</option>') === false) {
		$tmp = conf2kv($ri['options'], '=', ',');
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

	// \rkphplib\lib\log_debug("TFormValidator.getInput:764> name=$name, input=[$input] ri: ".print_r($ri, true));
	return $input;
}


/**
 * Return radio|check box html options.
 */
private function getCheckOptions(array &$p, string $name, string $str_options) : array {
	$html = '';

	$conf = $this->conf['current'];
	$type = (strpos($str_options, 'checkbox') === 0) ? 'checkbox' : 'radio';

	$tpl = $this->getConf('in.check.option', true, true);

	// \rkphplib\lib\log_debug("TFormValidator.getCheckOptions:786> name=[$name] str_options=[$str_options] tpl=[$tpl] p: ".print_r($p, true));
	foreach ($p as $value => $label) {
		unset($p[$value]);
		$r = [ 'name' => $name, 'type' => $type ];
		$r['id'] = $conf['id_prefix'].$name.'_'.$value;
		$r['value'] = $value;
		$r['label'] = $label;
		$r['checked'] = (!empty($_REQUEST[$name]) && $_REQUEST[$name] == $value) ? 'checked' : '';
		$html .= $this->tok->replaceTags($tpl, $r);
	}

	if (count($p) > 0) {
		throw new Exception('leftover keys', "html=[$html] p: ".print_r($p, true));
	}

	$p['class'] = 'check_group';
	$p['id'] = $conf['id_prefix'].$name;

	// \rkphplib\lib\log_debug("TFormValidator.getCheckOptions:804> return $html");
	return $html;
}


/**
 * Return html options. Options map p is conf2kv result map (unsed keys).
 */
private function getOptions(array &$p, string $opt_value, string $str_options) : string {
	// options are conf2kv result map ...
	$html = '';
	$empty_label = null;

	// \rkphplib\lib\log_debug("TFormValidator.getOptions:823> opt_value=[$opt_value] str_options=[$str_options] p: ".print_r($p, true)); 
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

	// \rkphplib\lib\log_debug("TFormValidator.getOptions:871> return $html");
	return $html;
}


}

