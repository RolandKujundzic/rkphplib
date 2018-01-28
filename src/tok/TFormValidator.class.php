<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);

require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/ValueCheck.class.php');
require_once($parent_dir.'/lib/htmlescape.php');
require_once($parent_dir.'/lib/split_str.php');

use \rkphplib\Exception;
use \rkphplib\ValueCheck;


/**
 * Form validator plugin.
 *
 * {fv:conf:NAME}
 * template.output= {:=label}: {:=input} {fv:error_message}|#|
 * template.header|footer= ...|#|
 * template.input= <input type="text" name="{:=name}" value="{:=value}" class="{:=class}"> {fv:error_message:$name}
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
	$type = TAG_PREFIX.'type'.TAG_SUFFIX;
	$name = TAG_PREFIX.'name'.TAG_SUFFIX;
	$value = TAG_PREFIX.'value'.TAG_SUFFIX;
	$class = TAG_PREFIX.'class'.TAG_SUFFIX;
	$options = TAG_PREFIX.'options'.TAG_SUFFIX;

	$this->conf['default'] = [
		'template.header' => '',
		'template.output' => "$label$input$error_message",
		'template.footer' => '',
		'template.const'    => $value,
		'template.input'    => '<input type="'.$type.'" name="'.$name.'" value="'.$value.'" class="'.$class.'" $tags>',
		'template.textarea' => '<textarea name="'.$name.'" class="'.$class.'" $tags>'.$value.'</textarea>',
		'template.select'   => '<select name="'.$name.'" class="'.$class.'" $tags>'.$options.'</select>',
		'template.error_message' 			  => $error,
		'template.error_message_concat' => ', ',
		'template.error_message_multi'  => "<i>$name</i>: <tt>$error</tt><br>",
		'template.error' 		=> 'error',
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

	$required = \rkphplib\lib\split_str(',', $this->conf['current']['required']);
	foreach ($required as $key) {
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
 * Return "error" (= template.error or $tpl if set) if $name check failed.
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

	return empty($tpl) ? $this->conf['template.error'] : $tpl;
}


/**
 * Return error message. Replace {:=name} and {:=error} in template.error_message[_multi] (overwrite with $tpl). If there are
 * multiple errors concatenate template.error_message with template.error_message_concat.
 *
 * Use name=* to return all error messages (concatenate template.error_message_multi).
 *
 * @throws
 * @return string 
 */
public function tok_fv_error_message($name, $tpl = '') {
	$res = '';
	$conf = $this->conf['current'];

	if ($name == '*') {
		if (empty($tpl)) {
			$tpl = $conf['template.error_message_multi'];
		}

		foreach ($this->error as $key => $value) {
			$res .= $this->tok_fv_error_message($key, $tpl);
		}

		return $res;
	}

	if (empty($tpl)) {
		$tpl = $conf['template.error_message'];
	}

	if (!isset($this->error[$name])) {
		return $res;
	}

	$r['name'] = $name;
	$r['error'] = join($conf['template.error_message_concat'], $this->error[$name]);

	$res = $this->tok->replaceTags($tpl, $r);
}


/**
 * Show input for $name. If $p is empty use conf.[in.name].
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

	$r = [];

	if (!empty($conf['in.'.$name])) {
		$p = array_merge($this->getInputMap($name, \rkphplib\lib\split_str(',', $conf['in.'.$name])), $p);
	}

	$r['label'] = empty($p['label']) ? '' : $p['label'];
	$r['input'] = $this->getInput($name, $p);

	$r['error_message'] = isset($this->error[$name]) ? join('|', $this->error[$name]) : '';
	$r['error'] = isset($this->error[$name]) ? 'error' : '';

	$res = $this->tok->replaceTags($res, $r);
	// \rkphplib\lib\log_debug("TFormValidator->tok_fv_in> res=[$res] r: ".print_r($r, true));
	return $res;
}


/**
 * Return input map. Examples:
 *
 * checkbox,
 * radio,
 * area,ROWS,COLS,WRAP
 * text,SIZE|WIDTHcc,MAXLENGTH
 * pass,
 * file,
 * select,
 * fselect,
 * set,
 * multi_select,
 *
 * @param string $name
 * @param vector $p 
 * @return map
 */
protected function getInputMap($name, $p) {
	$type = array_shift($p);
	$r = [];

	if ($type == 'text') {
		if (mb_substr($p[0], -2) == 'ch') {
			$r['width'] = $p[0];
		}
		else {
			$r['size'] = $p[0];
		}

		$r['type'] = 'text';
		$r['maxwidth'] = $p[1];
	}
	else if ($type == 'area') {
		$r['rows'] = $p[0];
		$r['cols'] = $p[1];
		$r['wrap'] = $p[2];

		if (!in_array($r['wrap'], [ 'soft', 'hard' ])) {
			throw new Exception('invalid wrap=['.$p[2].'] use soft|hard');
		}

		$r['type'] = 'textarea';
	}
	else {
		throw new Exception('ToDo: name='.$name.' p: '.join('|', $p));
	}

	return $r;
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

	if (!isset($ri['class'])) {
		$ri['class'] = '';
	}

	if (isset($this->error[$name])) {
		$ri['class'] = empty($ri['class']) ? 'error' : $ri['class'].' error';
	}

	if (!isset($p['type'])) {
		$type = empty($p['type']) ? '' : $p['type'];
		throw new Exception('invalid form validator type '.$name.'.type=['.$type.'] (use '.join(', ', array_keys($conf['template.*'])).')');
	}

	if (!empty($conf['template.'.$p['type']])) {
		$input = $conf['template.'.$p['type']];
	}
	else {
		$input = $conf['template.input'];
	}

	$tags = '';

	$attributes = [ 'size', 'maxlength', 'placeholder', 'type', 'pattern', 'rows', 'cols', 'style', 'class' ];
	foreach ($attributes as $key) {
		if (isset($p[$key]) && !mb_strpos($input, $this->tok->getTag($key))) {
			$tags .= $key.'="'.$this->tok->getTag($key).'"';
		}
	}

	$boolean_attributes = [ 'readonly', 'multiple', 'disabled' ];
	foreach ($boolean_attributes as $key) {
		if (!empty($p[$key]) && !mb_strpos($input, $this->tok->getTag($key))) {
			$tags .= ' '.$this->tok->getTag($key);
			$ri[$key] = $key;
		}
	}

	// selected, checked ???
	// \rkphplib\lib\log_debug("getInput> $tags - $input");

	foreach ($ri as $key => $value) {
		$ri[$key] = \rkphplib\lib\htmlescape($value);
	}

	$input = str_replace('$tags', $tags, $input);
	$input = $this->tok->replaceTags($input, $ri);

	// \rkphplib\lib\log_debug("getInput($name, ".print_r($p, true).") = $input");
	return $input;
}


}

