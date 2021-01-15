<?php

namespace rkphplib\tok\TFormValidator;

/**
 * HTML Form Tag Templates
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2021 Roland Kujundzic
 *
 * alt+x = «, alt+y = »
 */
class Template {

public static $tok;

private static $rkey;

private static $rval;


/**
 *
 */
public static function conf() : array {
	self::$rkey = [ "\n", "\t" ];
	self::$rval = [ ' ', '' ];

	$conf = [];

	self::setTags();
	self::setPlugins();

	$conf = [
		'submit' 							=> 'form_action',
		'id_prefix'						=> 'fvin_',
		'template.engine' 		=> 'default',
		'option.label_empty'  => '',

		'show_error_message' 	=> 1,
		'show_error' 					=> 1,
		'show_example' 				=> 1,
	];

	$conf['label_required']	= '<div class="label_required">'.TAG_PREFIX.'label'.TAG_SUFFIX.'</div>';

	self::merge($conf, 'default');
	self::merge($conf, 'bootstrap');
	self::merge($conf, 'material');
	
	if (isset($_REQUEST[SETTINGS_REQ_DIR])) {
		$conf['default']['hidden.dir'] = $_REQUEST[SETTINGS_REQ_DIR];
	}

	return $conf;
}


/**
 *
 */
private function merge(array &$conf, string $prefix) : void {
	$p = self::$prefix();

	foreach ($p as $grp => $list) {
		foreach ($list as $key => $value) {
			$ckey = empty($grp) ? $prefix.'.'.$key : $prefix.'.'.$grp.'.'.$key;
			$conf[$ckey] = str_replace(self::$rkey, self::$rval, $value);
		}
	}
}


/**
 *
 */
private function replace(string $key, string $value) : void {
	array_push(self::$rkey, $key);
	array_push(self::$rval, $value);
}


/**
 *
 */
private static function setTags() : void {
	$tags = [ 'label', 'label2', 'input', 'error', 'error_message', 'example', 'id',
		'type', 'col', 'form_group', 'name', 'value', 'class',
		'method', 'upload', 'tags', 'options', 'checked', 'fselect_input' ];

	foreach ($tags as $key) {
		array_push(self::$rkey, '«'.$key.'»');
		array_push(self::$rval, TAG_PREFIX.$key.TAG_SUFFIX);
	}
}


/**
 *
 */
private static function setPlugins() : void {
	$d = HASH_DELIMITER;

	$method = TAG_PREFIX.'method'.TAG_SUFFIX;
	$label2 = TAG_PREFIX.'label2'.TAG_SUFFIX;
	$upload = TAG_PREFIX.'upload'.TAG_SUFFIX;

	self::plugin('«pl_link»', 'link', '', '_=');

	self::plugin('«pl_if_method»', 'if', '', $method.$d.$method.$d.'get');

	self::plugin('«pl_if_upload»', 'if', '', $upload.$d.'enctype="multipart/form-data"');

	self::plugin('«pl_fv_hidden»', 'fv', 'hidden', null);

	self::plugin('«pl_if_label2»', 'if', '', 
		$label2.$d.'<button type="submit" name="form_action" value="2">'.$label2.'</button>');

	self::plugin('«pl_if_label2_btn»', 'if', '',
		$label2.$d.'<button type="submit" name="form_action" value="2" class="btn">'.$label2.'</button>');

	self::plugin('«pl_if_col»', 'if', '', "{:=col}{$d}{:=col}{$d}col-md-12");
}


/**
 *
 */
private static function plugin(string $tag, string $name, string $param, ?string $arg) : void {
	array_push(self::$rkey, $tag);
	array_push(self::$rval, self::$tok->getPluginTxt([ $name, $param ], $arg));
}


/**
 *
 */
private function bootstrap() : array {
	$conf = [];

	$conf['in'] = [
		'input'	  => '<input type="«type»" name="«name»" value="«value»" class="form-control «class»" «tags»>',

		'check'		=> '<div class="form-check-inline">«options»</div>',

		'radio'	  => '<input type="radio" name="«name»" value="«value»" class="form-check-input «class»" «tags»>',

		'file'     => '<input class="form-control-file «class»" name="«name»" type="file" data-value="«value»" «tags»>',

		'textarea' => '<textarea name="«name»" class="form-control «class»" «tags»>«value»</textarea>',

		'select'   => '<select name="«name»" class="form-control «class»" «tags»>«options»</select>',

		'fselect'  => '<span id="fselect_list_«name»"><select name="«name»" class="form-control «class»"
										onchange="rkphplib.fselectInput(this)" «tags»>«options»</select></span>
										<span id="fselect_input_«name»" style="display:none">«fselect_input»</span>',
		
		'check.option'		=> '<label class="form-check-label" for="«id»"><input id="«id»" type="«type»" name="«name»"
														class="form-check-input «class»" value="«value»" «checked»>«label»</label>',

		'checkbox'				=> '<input type="checkbox" name="«name»" value="«value»" class="form-check-input «class»" «tags»>',

		'multi_checkbox'	=> '<div class="row">«input»</div>',

		'multi_checkbox.entry'	=> '<div class=«col»><label class="form-check-label">«input» «label»</label></div>',
	];

	$conf['error'] = [
		'const'	=> 'is-invalid',
	];

	$conf['output'] = [
		'in'					=> '<div class="form-group {:=class} «error»"><label for="«id»">«label»</label>
												«example»«error_message»«input»</div>',

		'in.multi'		=> '<div class="row"><div class="col-md-3"><label>«label»</label>«example»
												«error_message»</div><div class="col-md-9">«input»</div></div>',

		'in.multi.2'	=> '<div class="row"><div class="col-md-6"><label>«label»</label></div>
												<div class="col-md-6">«example»«error_message»</div></div>
												<div class="row"><div class="col-md-12">«input»</div></div>',
	];

	$conf[''] = [
		'header'	=> '<div class="container-fluid ml-0 pl-0 {:=class}"><div class="row"><div class="«pl_if_col»">
			<form class="fv form" method="«pl_if_method»" action="«pl_link»" «pl_if_upload» data-key13="prevent" novalidate>
			«pl_fv_hidden»',
	
		'footer'	=> '<div class="row"><div class="col-md-4"><button type="submit" class="btn">«label»</button></div>
			<div class="col-md-8">«pl_if_label2_btn»</div></div></form></div></div></div>',

		'submit'	=> '<button type="submit" class="btn">«label»</button>',

		'example'	=> '<span class="example">«example»</span>',
	];

	return $conf;
}


/**
 *
 */
private static function material() : array {
	$conf = [];

	$conf['in'] = [
		'input'	   => '<input type="«type»" name="«name»" value="«value»" class="mdl-textfield__input «class»" «tags»>',

		'file'     => '<input class="mdl-textfield__input «class»" name="«name»" type="file" data-value="«value»" «tags»>',

		'textarea' => '<textarea name="«name»" class="mdl-textfield__input «class»" «tags»>«value»</textarea>',

		'select'   => '<select name="«name»" class="mdl-textfield__input «class»" «tags»>«options»</select>',

		'fselect'  => '<span id="fselect_list_«name»"><select name="«name»" class="mdl-textfield__input «class»"
										onchange="rkphplib.fselectInput(this)" «tags»>«options»</select></span>
										<span id="fselect_input_«name»" style="display:none">«fselect_input»</span>',

		'checkbox' => '<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="«id»">
											<input type="checkbox" id="«id»" name="«name»" value="«value»"
											class="mdl-checkbox__input mdl-js-ripple-effect «class»" «tags»>
											<span class="mdl-checkbox__label">«label»</span></label>',
	];

	return $conf;
}


/**
 *
 */
private static function default() : array {
	$conf = [];

	$conf['in'] = [
		'const'		=> '<span class="const">«value»</span>',

		'input'		=> '<input type="«type»" name="«name»" value="«value»" class="«class»" «tags»>',

		'select'	=> '<select name="«name»" class="«class»" «tags»>«options»</select>',

		'check'		=> '<div class="check_wrapper">«options»</div>',

		'fselect'	=> '<span id="fselect_list_«name»"><select name="«name»" class="«class»"
										onchange="rkphplib.fselectInput(this)" «tags»>«options»</select></span>
										<span id="fselect_input_«name»" style="display:none">«fselect_input»</span>',

		'textarea'	=> '<textarea name="«name»" class="«class»" «tags»>«value»</textarea>',

		'file'			=> '<input type="file" name="«name»" class="«class»" data-value="«value»" «tags»>',

		'file_btn'	=> '<div class="file_btn_wrapper"><button class="file_btn" «tags»>«label2»</button>
											<input type="file" name="«name»" style="opacity:0;position:absolute;right:0;left:0;top:0;bottom:0;"
											data-value="«value»"></div>',

		'check.option' 		=> '<label for="«id»"><input id="«id»" type="«type»" name="«name»"
																		class="«class»" value="«value»" «checked»>«label»</label>',

		'multi_checkbox'	=> '<div class="multi_checkbox_wrapper">«input»</div>',

		'multi_checkbox.entry'	=> '<div class="multi_checkbox"><span>«input»</span><span>«label»</span></div>',
	];

	$conf['error'] = [
		'message'					=> '<span class="error_message">«error»</span>',

		'message_concat'	=> ', ',

		'message_multi'		=> '<i>«name»</i>: <tt>«error»</tt><br class="fv" />',

		'const'						=> 'error',
	];

	$conf['output'] = [
		'in'						=> '<span class="label «error»">«label»</span>«input»«example»«error_message»<br class="fv" />',

		'in.cbox_query' => '«input»<span class="cbox_query «error»">«label»</span><br class="fv" />',		

		'in.multi'			=> '<span class="label «error»">«label»</span>«input»
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

	return $conf;
}

}

