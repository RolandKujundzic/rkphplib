<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/Tokenizer.class.php';
require_once __DIR__.'/../ValueCheck.php';
require_once __DIR__.'/../File.class.php';
require_once __DIR__.'/../lib/htmlescape.php';
require_once __DIR__.'/../lib/split_str.php';
require_once __DIR__.'/../lib/conf2kv.php';
require_once __DIR__.'/../lib/kv2conf.php';
require_once __DIR__.'/../lib/http_code.php';

use rkphplib\Exception;
use rkphplib\tok\Tokenizer;
use rkphplib\ValueCheck;
use rkphplib\File;

use function rkphplib\lib\htmlescape;
use function rkphplib\lib\http_code;
use function rkphplib\lib\split_str;
use function rkphplib\lib\conf2kv;
use function rkphplib\lib\kv2conf;


/**
 * Form validator plugin.
 *
 * @tok …
 * {fv:init}
 * table=shop_item|#|
 * {:fv}
 * 
 * {tf:cmp:yes}{fv:check}{:tf}
 * {true:}…{:true}
 * {false:}…{:false}
 * @eol
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class FormValidator implements TokPlugin {

// @var string $conf_file
public static $conf_file = '';

// @var Tokenizer $tok
protected $tok = null;

// @var hash $conf
protected $conf = [];

// @var hash $error
protected $error = [];

// @var hash $example
protected $example = [];



/**
 *
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [];
	$plugin['fv2'] = 0;
	$plugin['fv2:check'] = TokPlugin::NO_BODY; 
	$plugin['fv2:hidden'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['fv2:in'] = TokPlugin::REQUIRE_PARAM | TokPlugin::KV_BODY | TokPlugin::REDO;
	$plugin['fv2:init'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY; 
	$plugin['fv2:preset'] = TokPlugin::ASK;

  return $plugin;
}


/**
 * Set TFormValidator::$conf_file = path/to/config.[json|conf] or $conf for
 * custom configuration.
 */
public function __construct(array $conf = []) {
	$tok = is_null($this->tok) ? Tokenizer::$site : $this->tok;
	if (is_null($tok)) {
		$tok = new Tokenizer();
	}

	if (!empty(self::$conf_file) && File::exists(self::$conf_file)) {
		if (substr(self::$conf_file, -5) == '.json') {
			$this->conf = File::loadJSON(self::$conf_file);
		}
		else {
			$this->conf = File::loadConf(self::$conf_file);
		}
	}
	else {
		$this->conf = array_merge(self::defaultConfig(), $conf);

		if (!empty(self::$conf_file)) {
			if (substr(self::$conf_file, -5) == '.json') {
				File::saveJSON(self::$conf_file, $this->conf);
			}
			else if (substr(self::$conf_file, -5) == '.conf') {
				File::saveConf(self::$conf_file, $this->conf, "\n\n");
			}
		}
	}
}


/**
 * Return default configuration
 */
private static function defaultConfig() : array {
	$conf = [
		'submit' 							=> 'form_action',
		'id_prefix'						=> 'fvin_',
		'option.label_empty'  => '',

		'show_error_message' 	=> 1,
		'show_error' 					=> 1,
		'show_example' 				=> 1,
	];

	$conf['in'] = [
		'const'		=> '<span class="const">«value»</span>',

		'input'		=> '<input type="«type»" name="«name»" value="«value»"«tags»>',

		'select'	=> '<select name="«name»"«tags»>«options»</select>',

		'check'		=> '<div class="check_wrapper">«options»</div>',

		'fselect'	=> '<span id="fselect_list_«name»"><select name="«name»" '.
			'onchange="rkphplib.fselectInput(this)"«tags»>«options»</select></span>'.
			'<span id="fselect_input_«name»" style="display:none">«fselect_input»</span>',

		'textarea'	=> '<textarea name="«name»" class="«class»"«tags»>«value»</textarea>',

		'file'			=> '<input type="file" name="«name»" class="«class»" data-value="«value»"«tags»>',

		'file_btn'	=> '<div class="file_btn_wrapper"><button class="file_btn"«tags»>«label2»</button>
											<input type="file" name="«name»" style="opacity:0;position:absolute;right:0;left:0;top:0;bottom:0;"
											data-value="«value»"></div>',

		'check.option' 		=> '<label for="«id»"><input id="«id»" type="«type»" name="«name»"
																		class="«class»" value="«value»" «checked»>«label»</label>',

		'multi_checkbox'	=> '<div class="multi_checkbox_wrapper">«input»</div>',

		'multi_checkbox.entry'	=> '<div class="multi_checkbox"><span>«input»</span><span>«label»</span></div>',

		'images'	=> '<input type="hidden" id="fvin_images" name="images" value="«value»"><a href="'.
				'javascript:rkAdmin.toggleDiv(\'image_preview\')">Detailansicht</a><div id="images_wrapper"></div>'
	];

	$conf['error'] = [
		'message'					=> '<span class="error_message">«error»</span>',

		'message_concat'	=> ', ',

		'message_multi'		=> '<i>«name»</i>: <tt>«error»</tt><br class="fv" />',

		'const'						=> 'error',
	];

	$conf['output'] = [
		'in'						=> '<label for="«id»" class="label «error»">«label»</label>«input»«example»«error_message»<br class="fv" />',

		'in.cbox_query' => '«input»<label for="«id»" class="cbox_query «error»">«label»</label><br class="fv" />',

		'in.multi'			=> '<label for="«id»" class="label «error»">«label»</label>«input»
													<div class="example_error_wrapper">«example»«error_message»</div>',
	];

	$conf[''] = [
		'example'	=> '<span class="example">«example»</span>',

		'header'	=> '<form class="fv" action="«pl_link»" method="«pl_if_method»" «pl_if_upload»
										data-key13="prevent" novalidate>«pl_fv_hidden»',

		'form'		=> '<form class="fv {:=class}" action="«pl_link»" method="«pl_if_method»" «pl_if_upload»
										data-key13="prevent" novalidate>«pl_fv_hidden»',

		'footer'	=> '<button type="submit" class="{:=class}">«label»</button><div class="label2">
										«pl_if_label2»</div></form>',

		'submit'	=> '<button type="submit" class="{:=class}">«label»</button>',
	];

	if (isset($_REQUEST[SETTINGS_REQ_DIR])) {
		$conf['hidden.dir'] = $_REQUEST[SETTINGS_REQ_DIR];
	}

	return $conf;
}


/**
 * Set _REQUEST value if not submit and _REQUEST.id != add.
 * Use param = ? to check if preset mode is on.
 * Don't overwrite already set _REQUEST values.
 * Use param = * to force overwrite in hash mode (blank all if arg is empty).
 *
 * @tok {fv:preset:?} = 1|''  # 1 = preset mode is on
 * @tok {fv:preset:firstname}Joe{:fv}  # set single value (overwrite)
 * @tok {fv:preset:!}firstname=Joe|#|lastname=Smith{:fv}  # force overwrite
 * @tok {fv:preset:*}{:fv}  # blank all
 * @tok …
 * {fv:preset}
 * {sql:query}SELECT * FROM table WHERE id={esc:id}{:sql}
 * password=|#|{sql:col:*}
 * {:fv}
 * @eol
 */
public function tok_fv2_preset(string $param, ?string $arg) : string {
	// \rkphplib\lib\log_debug("FormValidator.tok_fv2_preset:219> param=[$param] arg=[$arg]");
	$skey = $this->getConf('submit');

	if (!empty($_REQUEST[$skey])) {
		return '';
	}

	if (!empty($_REQUEST['id']) && $_REQUEST['id'] == 'add') {
		return '';
	}

	if ($param == '?') {
		return 1;
	}
	else if ($param == '*' && empty($arg)) {
		$ckeys = $this->conf['current'];
		foreach ($ckeys as $key) {
			if (substr($key, 0, 3) == 'in.') {
				$key = substr($key, 3);
				if (isset($_REQUEST[$key])) {
					$_REQUEST[$key] = '';
				}
			}
		}
	}
	else if (!empty($param) && $param != '!') {
		$_REQUEST[$param] = $arg;
	}
	else {
		$overwrite = $param == '!';
		$conf = conf2kv($arg);

		foreach ($conf as $key => $value) {
			if ($overwrite || !isset($_REQUEST[$key])) {
				$_REQUEST[$key] = $value;
			}
		}
	}

  return '';
}


/**
 * Should not be called. Always throw exception.
 */
public function tok_fv2(?string $param, ?string $arg) : void {
	throw new Exception("no such plugin [fv:$param]...[:fv]", "param=[$param] arg=[$arg]");
}


/**
 * Return hidden input for conf.hidden_keep and conf.hidden.key.
 */
public function tok_fv2_hidden() : string {
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
 * Initialize form validator.
 *
 * @hash $p …
 * use: default (or bootstap|material)
 * submit: form_action
 * data_check: 1
 * @eol
 */
public function tok_fv2_init(string $do, array $p) : void {
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
		// \rkphplib\lib\log_debug([ "FormValidator.tok_fv2_init:320> reset, do=$do p: <1>", $p ]);
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
	// \rkphplib\lib\log_debug([ "FormValidator.tok_fv2_init:347> ($do, …) use_conf: <1>\ncurrent: <2>", $use_conf, $this->conf['current'] ]);
}


/**
 * Return validation result (yes|error|). Call get2NData() if multi_checkbox|radio input exists.
 * If _REQUEST[conf[submit]] is empty do nothing. Apply all conf[check.*] value checks.
 *
 * @tok {fv:check:} -> [|yes|error]
 * @tok {fv:check:0} -> no output
 * @tok {fv:check:name} -> return {tpl:name} output
 */
public function tok_fv2_check(string $ajax = '') : string {
	$submit = $this->getConf('submit');

	// \rkphplib\lib\log_debug([ "FormValidator.tok_fv2_check:362> submit=$submit _REQUEST: <1>", $_REQUEST ]);
	foreach ($this->conf['current'] as $key => $value) {
		if (substr($key, 0, 3) == 'in.' && (substr($value, 0, 14) == 'multi_checkbox' || substr($value, 0, 11) == 'multi_radio')) {
			$this->conf['current'][$key] = $this->get2NData(substr($key, 3), $value);
		}
	}

	if (empty($_REQUEST[$submit])) {
		return '';
	}

	if (count($this->error) > 0) {
		return $ajax == '0' ? '' : 'error';
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

			// \rkphplib\lib\log_debug([ "FormValidator.tok_fv2_check:412> column=$column allow_col: <1>", $allow_col ]);
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
					// \rkphplib\lib\log_debug("FormValidator.tok_fv2_check:430> auto-trim $name value [$req_value]");
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
				// \rkphplib\lib\log_debug([ "FormValidator.tok_fv2_check:443> path=$key name=$name error: <1>", $this->error[$name] ]);
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

	if ($ajax == '0') {
		$res = '';
	}

	// \rkphplib\lib\log_debug("FormValidator.tok_fv2_check:463> res=$res");
	return $res;
}


/**
 * Execute tpl:$ajax and exit.
 */
private function ajaxOutput(string $ajax) : string {
	try {
		$output = $this->tok->callPlugin('tpl', $ajax);
		// \rkphplib\lib\log_debug("FormValidator.ajaxOutput:474> tpl:$ajax=$output");
		http_code(200, [ '@output' => $output ]);
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
	$r = conf2kv($name_def, '=', ',');

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

	// \rkphplib\lib\log_debug([ "FormValidator.get2NData:545> <1>", $r ]);
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

	// \rkphplib\lib\log_debug([ "FormValidator.get2NData:576> name=$name value=[$value] res=[$res] r: <1>", $r ]);
	return $res;
}


/**
 * Return multi-checkbox|radio html.
 */
private function multiCheckbox(string $name, array $p) : string {
	// \rkphplib\lib\log_debug([ "FormValidator.multiCheckbox:585> name=$name p: <1>", $p ]);
	$col = empty($p['col']) ? 'col-md-4' : $p['col'];
	$entry = $this->tok->replaceTags($this->getConf('in.multi_checkbox.entry', true), [ 'col' => $col ] );
	$entries = $this->getConf('in.multi_checkbox', true);
	$entry_list = '';

	$is_checkbox = $p['type'] == 'checkbox';
	$value = 0;

	// \rkphplib\lib\log_debug("FormValidator.multiCheckbox:594> name=$name entry=[$entry] entries=[$entries]");
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
		// \rkphplib\lib\log_debug([ "FormValidator.multiCheckbox:609> var=$var input_name=$input_name r: <1>\n$html", $r);
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

	if (!empty($r['nobr']) && !empty($conf['nobr'])) {
		if (substr($r['nobr'], 0, 1) == '-') {
			$res = str_replace('" id="', ' nobr'.substr($r['nobr'], 1).'" id="', $res);
		}
		else {
			$res = str_replace([ '<br class="fv" />', '" id="' ], [ '', ' nobr'.$r['nobr'].'" id="' ], $res);
		}
	}

	$res = preg_replace([ '/>\s+</', '/<(label|span) [^>]+><\/(label|span)>/' ], [ '><', '' ], trim($res));
 
	// \rkphplib\lib\log_debug([ "FormValidator._fv_in_html:664> name=$name res=[$res] r: <1>", $r ]);
	return $res;
}


/**
 * Show input for $name. Default input template conf[ENGINE.in.$type].
 * Default output template is conf[ENGINE.output.in]. If $p[output] is not empty 
 * use conf[ENGINE.in.OUTPUT]. If there are multiple forms on same page,
 * set _REQUEST[use_FORM_ACTION]=1 to enable specific form.
 */
public function tok_fv2_in(string $name, array $p) : string {
	$conf = $this->conf['current'];

	$skey = $conf['submit'];
	$is_action = !empty($_REQUEST[$skey]);

	// \rkphplib\lib\log_debug([ "FormValidator.tok_fv2_in:681> name=$name key=$skey is_action=$is_action p: <1>", $p ]);
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
		throw new Exception('define [fv:init]in.'.$name.'= ...', print_r($p, true));
	}

	if ($p['type'] == 'const') {
		if (is_null($p['value']) || $p['value'] == 'NULL' || !empty($p['is_null'])) {
			return '';
		}
	}

	$this->setInputAttrib($name, $p);

	// \rkphplib\lib\log_debug([ "FormValidator.tok_fv2_in:717> name=$name p: <1>", $p ]);
	$p['input'] = $this->getInput($name, $p);

	return $this->_fv_in_html($name, $p);
}


/**
 * Set $p[maxlength]=N if check.name=maxLength:N exists
 */
function setInputAttrib(string $name, array &$p) : void {
	if (isset($p['maxlength'])) {
		return;
	}

	$mlc = 'check.'.$name;
	$dc = !empty($this->conf['current']['data_check']);
	foreach ($this->conf['current'] as $key => $check) {
		if ($key == $mlc) {
			if (substr($check, 0, 10) == 'maxLength:') {
				$p['maxlength'] = intval(substr($check, 10));
			}
			else if (substr($check, 0, 2) == 'is') {
				$p['data-check'] = $check;
			}
		}
	}
}


/**
 * Return configuration key. Use $engine = true|1 for conf[template.engine].
 * @param string|bool $engine
 */
private function getConf(string $key, $engine = '', bool $required = true) : string {
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

	// \rkphplib\lib\log_debug("FormValidator.getConf:765> ($key, $engine, $required) ckey = $ckey");
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
	$r = conf2kv($value, '=', ',');
	// \rkphplib\lib\log_debug([ "FormValidator.parseInName:812> name=$name, value=$value, r: <1>\np: <2>", $r, $p ]);

	if (is_string($r)) {
		$p['type'] = $r;
		$r = [];
	}
	else if (!empty($r[0])) {
		$p['type'] = $r[0];
		unset($r[0]);
	}

	if (!empty($r['multi'])) {
		$p = array_merge($p, $r);
		// \rkphplib\lib\log_debug([ "FormValidator.parseInName:825> name=$name, value=$value, multi p: <1>", $p ]);
		return;
	}

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
			$p['maxlength'] = $r[2];
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
		$p['value'] = isset($_REQUEST[$name]) && strlen($_REQUEST[$name]) > 0 ? $_REQUEST[$name] : 1;
		$p['checked'] = !empty($_REQUEST[$name]) && $_REQUEST[$name] == $p['value'];
	}
	else {
		throw new Exception("ToDo: name=$name type=$type p: ".join('|', $p));
	}

	foreach ($r as $key => $value) {
		$p[$key] = $value;
	}
	// \rkphplib\lib\log_debug([ "FormValidator.parseInName:917> name=$name, value=$value, p: <1>", $p ]);
}


/**
 * Return html input. Use [template.engine].in.input for type=[text|password|radio|checkbox|hidden|image|email|...].
 * Use textarea, select for type=[textarea|select]. Attribute keys: size, maxlength, on*, placeholder, type, class, style, 
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
		$ri['class'] = trim($ri['class'].' '.$this->getConf('error.const', true));
	}

	$tpl_in = $conf['template.engine'].'.in';

	// \rkphplib\lib\log_debug([ "FormValidator.getInput:947> name=$name tpl_in=$tpl_in ri: <1>", $ri ]);
	if (empty($ri['type'])) {
		$use = join(', ', array_keys($this->getMapKeys($tpl_in, $conf)));
		throw new Exception("missing form validator type for $name (use $use)", print_r($ri, true));
	}

	if (!empty($ri['tpl_in'])) {
		$input = $ri['tpl_in'];
		// \rkphplib\lib\log_debug("FormValidator.getInput:955> $input");
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

	$attributes = [ 'id', 'size', 'maxlength', 'placeholder', 'pattern', 'step', 'rows', 'cols', 
		'style', 'class', 'wrap', 'accept', 'onchange', 'onblur', 'autocomplete', 'min', 'max' ];
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

	// on* events
	foreach ($ri as $key => $value) {
		if (strpos($key, 'on') === 0) {
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

	// \rkphplib\lib\log_debug([ "FormValidator.getInput:1036> name=$name, input=[$input] ri: <1>", $ri ]);
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

	// \rkphplib\lib\log_debug([ "FormValidator.getCheckOptions:1052> name=[$name] str_options=[$str_options] tpl=[$tpl] p: <1>", $p ]);
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

	// \rkphplib\lib\log_debug("FormValidator.getCheckOptions:1070> return $html");
	return $html;
}


/**
 * Return html options. Options map p is conf2kv result map (unsed keys).
 */
private function getOptions(array &$p, string $opt_value, string $str_options) : string {
	// options are conf2kv result map ...
	$html = '';
	$empty_label = null;

	// \rkphplib\lib\log_debug([ "FormValidator.getOptions:1083> opt_value=[$opt_value] str_options=[$str_options] p: <1>", $p ]);
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

	// \rkphplib\lib\log_debug("FormValidator.getOptions:1131> return $html");
	return $html;
}


}

