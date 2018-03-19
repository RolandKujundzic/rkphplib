<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/TokHelper.trait.php');
require_once($parent_dir.'/ValueCheck.class.php');
require_once($parent_dir.'/lib/htmlescape.php');
require_once($parent_dir.'/lib/split_str.php');
require_once($parent_dir.'/lib/conf2kv.php');
require_once($parent_dir.'/lib/is_map.php');

use \rkphplib\Exception;
use \rkphplib\ValueCheck;


/**
 * Form validator plugin.
 *
 * {fv:conf:NAME}
 * template.output= default|#|
 * template.output.default= {:=label}: {:=input} {fv:error_message}|#|
 * template.header|footer= ...|#|
 * template.input= <input type="text" name="{:=name}" value="{:=value}" class="{:=class}"> {fv:error_message:$name}
 * template.engine= default[|bootstrap]
 * {:fv}
 *
 * {fv:init:[add]}
 * use= NAME|#| (default: use=default)
 * hidden_keep= dir, ...|#|
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


/** @var array $conf */
protected $conf = [ 'default' => [], 'current' => [] ];

/** @var array $error */
protected $error = [];



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
	$plugin['fv:conf'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['fv:check'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY; 
	$plugin['fv:in'] = TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY;
	$plugin['fv:hidden'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['fv:preset'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::TEXT | TokPlugin::REDO;
	$plugin['fv:error'] = TokPlugin::REQUIRE_PARAM;
	$plugin['fv:error_message'] = TokPlugin::REQUIRE_PARAM;
	$plugin['fv:set_error_message'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY;

  return $plugin;
}


/**
 * Set default configuration.
 */
public function __construct() {
	$label = TAG_PREFIX.'label'.TAG_SUFFIX;
	$input = TAG_PREFIX.'input'.TAG_SUFFIX;
	$error = TAG_PREFIX.'error'.TAG_SUFFIX;
	$error_message = TAG_PREFIX.'error_message'.TAG_SUFFIX;
	$id = TAG_PREFIX.'id'.TAG_SUFFIX;
	$type = TAG_PREFIX.'type'.TAG_SUFFIX;
	$name = TAG_PREFIX.'name'.TAG_SUFFIX;
	$value = TAG_PREFIX.'value'.TAG_SUFFIX;
	$class = TAG_PREFIX.'class'.TAG_SUFFIX;
	$tags = TAG_PREFIX.'tags'.TAG_SUFFIX;
	$options = TAG_PREFIX.'options'.TAG_SUFFIX;
	$fselect_input = TAG_PREFIX.'fselect_input'.TAG_SUFFIX;

	$this->conf['default'] = [
		'submit' 					=> 'form_action',
		'label_required'	=> $label.'<sup>*</sup>',

		'template.engine' => 'default',
		'template.output' => 'default',
		'template.output.default' => "$label$input$error_message",
		'template.header' => '',
		'template.footer' => '',

		'option.label_empty'  => '...',

		'default.in.const'    => '<span class="const">'.$value.'</span>',
		'default.in.input'    => '<input type="'.$type.'" name="'.$name.'" value="'.$value.'" class="'.$class.'" '.$tags.'>',
		'default.in.textarea' => '<textarea name="'.$name.'" class="'.$class.'" '.$tags.'>'.$value.'</textarea>',
		'default.in.select'   => '<select name="'.$name.'" class="'.$class.'" '.$tags.'>'.$options.'</select>',
		'default.in.fselect'  => '<span id="fselect_list_'.$id.'"><select name="'.$name.'" class="'.$class.'" '.
			'onchange="rkphplib.fselectInput(this)" '.$tags.'>'.$options.'</select></span>'.
			'<span id="fselect_input_'.$id.'" style="display:none">'.$fselect_input.'</span>',

		'bootstrap.in.input'	  => '<input type="'.$type.'" name="'.$name.'" value="'.$value.'" class="form-control '.$class.'" '.$tags.'>',
		'bootstrap.in.textarea' => '<textarea name="'.$name.'" class="form-control '.$class.'" '.$tags.'>'.$value.'</textarea>',
		'bootstrap.in.select'   => '<select name="'.$name.'" class="form-control '.$class.'" '.$tags.'>'.$options.'</select>',
		'bootstrap.in.fselect'  => '<span id="fselect_list_'.$id.'"><select name="'.$name.'" class="form-control '.$class.'" '.
			'onchange="rkphplib.fselectInput(this)" '.$tags.'>'.$options.'</select></span>'.
			'<span id="fselect_input_'.$id.'" style="display:none">'.$fselect_input.'</span>',

		'template.error.message' 			  => $error,
		'template.error.message_concat' => ', ',
		'template.error.message_multi'  => "<i>$name</i>: <tt>$error</tt><br>",
		'template.error.const' 					=> 'error'
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
	$skey = $this->conf['current']['submit'];

	if (isset($_REQUEST[$skey])) {
		return '';
	}

	if (!empty($_REQUEST['id']) && $_REQUEST['id'] == 'add') {
		return '';
	}

	return $arg;
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
	$conf = $this->conf['current'];

	if (!empty($conf['hidden_keep'])) {
		$list = \rkphplib\lib\split_str(',', $conf['hidden_keep']);
		foreach ($list as $key) {
			if (isset($_REQUEST[$key])) {
				$res .= '<input type="hidden" name="'.$key.'" value="'.\rkphplib\lib\htmlescape($_REQUEST[$key]).'">'."\n";
			}
		}
	}

	foreach ($conf as $key => $value) {
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

				array_push($this->error[$name], $this->getErrorMessage($path));
				// \rkphplib\lib\log_debug("TFormValidator->tok_fv_check> path=$key name=$name error: ".print_r($this->error[$name], true));
			}
		}
	}

	return (count($this->error) == 0) ? 'yes' : 'error';
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
 * Return "error" (= template.error.const or $tpl if set) if $name check failed.
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

	return empty($tpl) ? $this->conf['template.error.const'] : $tpl;
}


/**
 * Return error message. Replace {:=name} and {:=error} in template.error.message[_multi] (overwrite with $tpl). If there are
 * multiple errors concatenate template.error.message with template.error.message_concat.
 *
 * Use name=* to return all error messages (concatenate template.error.message_multi).
 *
 * @throws
 * @return string 
 */
public function tok_fv_error_message($name, $tpl = '') {
	$res = '';
	$conf = $this->conf['current'];

	if ($name == '*') {
		if (empty($tpl)) {
			$tpl = $conf['template.error.message_multi'];
		}

		foreach ($this->error as $key => $value) {
			$res .= $this->tok_fv_error_message($key, $tpl);
		}

		// \rkphplib\lib\log_debug("tok_fv_error_message($name, ...)> name=[$name] res=[$res] - error: ".print_r($this->error, true));
		return join(',', array_keys($this->error));
		// return $res;
	}

	if (empty($tpl)) {
		$tpl = $conf['template.error.message'];
	}

	if (!isset($this->error[$name])) {
		return $res;
	}

	$r['name'] = $name;
	$r['error'] = join($conf['template.error.message_concat'], $this->error[$name]);

	$res = $this->tok->replaceTags($tpl, $r);
}


/**
 * Show input for $name. If $p is empty use conf.[in.name].
 * If name is header|footer return template.header|footer.
 *
 * @throws
 * @param string $name
 * @param array $p
 * @return string
 */
public function tok_fv_in($name, $p) {
	if (!is_array($this->conf['current']['required'])) {
		$this->conf['current']['required'] = \rkphplib\lib\split_str(',', $this->conf['current']['required']);
	}

	$conf = $this->conf['current'];
	$res = '';

	if ($name == 'header' || $name == 'footer') {
		return $this->tok->replaceTags($conf['template.'.$name], $p);
	}

	$out = empty($p['output']) ? $conf['template.output'] : $p['output'];
	$res .= $conf['template.output.'.$out];

	if (!empty($conf['in.'.$name])) {
		$this->parseInName($name, $conf['in.'.$name], $p);
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

	$r = [];
	$r['id'] = $p['id'];
	$r['label'] = empty($p['label']) ? '' : $p['label'];
	$r['input'] = $this->getInput($name, $p);

	$r['error_message'] = isset($this->error[$name]) ? join('|', $this->error[$name]) : '';
	$r['error'] = isset($this->error[$name]) ? 'error' : '';

	$res = $this->tok->replaceTags($res, $r);
	// \rkphplib\lib\log_debug("TFormValidator->tok_fv_in> res=[$res] r: ".print_r($r, true));
	return $res;
}


/**
 * Parse value and add to input map p. Examples:
 *
 *  - in.name= checkbox,
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
		return;
	}

	if (!empty($r['@_1'])) {
		$p['type'] = $r['@_1'];
		unset($r['@_1']);
	}
	else if (!empty($r[0])) {
		$p['type'] = $r[0];
		unset($r[0]);
	}

	// \rkphplib\lib\log_debug("parseInName($name, $value, ...)> r: ".print_r($r, true));
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

		$p['options'] = $this->getOptions($r, $p['value'], $value);
	}
	else if ($type == 'const') {
		// ok ...
	}
	else {
		throw new Exception('ToDo: name='.$name.' p: '.join('|', $p));
	}

	foreach ($r as $key => $value) {
		$p[$key] = $value;
	}

	// \rkphplib\lib\log_debug("parseInName($name, $value, ...)> p: ".print_r($p, true));
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
	$ri['name'] = $name;

	$conf = $this->conf['current'];

	if (!empty($ri['width'])) {
		$ri['style'] = 'width: '.$ri['width'];
	}

	if (!isset($ri['class'])) {
		$ri['class'] = '';
	}

	if (isset($this->error[$name])) {
		$ri['class'] = empty($ri['class']) ? 'error' : $ri['class'].' error';
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

	$attributes = [ 'id', 'size', 'maxlength', 'placeholder', 'pattern', 'rows', 'cols', 'style', 'class' ];
	foreach ($attributes as $key) {
		if (isset($ri[$key]) && !mb_strpos($input, $this->tok->getTag($key))) {
			$tags .= $key.'="'.$this->tok->getTag($key).'"';
		}
	}

	$boolean_attributes = [ 'readonly', 'multiple', 'disabled' ];
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
		if ($key != 'options') {
			$ri[$key] = \rkphplib\lib\htmlescape($value);
		}
	}

	$ri['tags'] = $tags;
	$input = $this->tok->replaceTags($input, [ 'tags' => $tags ]);

	if (!empty($ri['options']) && strpos($ri['options'], '</option>') === false) {
		$tmp = \rkphplib\lib\conf2kv($ri['options'], '=', ',');
		$ri['options'] = $this->getOptions($tmp, $ri['value'], $ri['options']);
	}

	$input = $this->tok->replaceTags($input, $ri);

	// \rkphplib\lib\log_debug("getInput($name): ".print_r($ri, true)."\n$input");
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

	// \rkphplib\lib\log_debug("TFormValidator.getOptions|enter> opt_value=[$opt_value] str_options=[$str_options] p: ".print_r($p, true)); 

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

	$value_equal_comma = preg_replace('/[^\=,]/', '', $str_options);
	$map_check = (strlen($value_equal_comma) >= 2 * count($p)) ? 0 : 2;

	if (!\rkphplib\lib\is_map($p, $map_check)) {
		foreach ($p as $key => $value) {
			if (strlen($value) > 0) {
				$selected = ($opt_value == $value) ? ' selected' : '';
				$html .= '<option value="'.$value.'"'.$selected.'>'.$value."</option>\n";
			}

			unset($p[$key]);
		}
	}
	else {
		foreach ($p as $value => $label) {
			$selected = ($opt_value == $value) ? ' selected' : '';
			$html .= '<option value="'.$value.'"'.$selected.'>'.$label."</option>\n";
			unset($p[$value]);
		}
	}

	if (count($p) > 0) {
		throw new Exception('leftover keys', "html=[$html] p: ".print_r($p, true));
	}

	$html = preg_replace('/value\=\"@_[0-9]+\"/', '', $html);

	// \rkphplib\lib\log_debug("getOptions> $html");
	return $html;
}


}

