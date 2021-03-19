<?php

namespace rkphplib\tok;

require_once __DIR__.'/TPicture.class.php';
require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/THttp.class.php';
require_once __DIR__.'/../Database.class.php';
require_once __DIR__.'/../Dir.class.php';
require_once __DIR__.'/../File.class.php';
require_once __DIR__.'/../JSON.class.php';
require_once __DIR__.'/../lib/split_str.php';
require_once __DIR__.'/../lib/conf2kv.php';

use rkphplib\tok\TPicture;
use rkphplib\tok\THttp;
use rkphplib\Exception;
use rkphplib\Database;
use rkphplib\FSEntry;
use rkphplib\JSON;
use rkphplib\File;
use rkphplib\Dir;

use function rkphplib\lib\split_str;
use function rkphplib\lib\conf2kv;



/**
 * File upload.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TUpload implements TokPlugin {

// @var Tokenizer $tok
protected $tok = null;

// @var hash $conf common configuration hash
protected $conf = [];

// @var <string:hash> $options custom multi-upload configuration 
protected $options = [ '@plugin_action' => 0 ];



/**
 * Return {upload:init|conf|formData|exits|scan}.
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [];
  $plugin['upload:save'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['upload:init'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['upload:conf'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['upload:formData'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
  $plugin['upload:exists'] = TokPlugin::NO_PARAM |  TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['upload:scan'] = TokPlugin::REQUIRE_PARAM | TokPlugin::NO_BODY;
	$plugin['upload'] = 0;

	return $plugin;
}


/**
 * Return javascript form data.
 *
 * @tok {upload:get:ajax_parameter}@@4=":",","|#|...|#|ajax_parameter= @4 id:#fvin_id, ajax:upload{:upload}
 * @tok {upload:formData:hidden}
 * 		let f = this.element.parentNode;
 *		this.options.rkHiddenInput("ajax", "upload");
 * @tok {upload:formData}
 * 		formData.append("id", document.getElementById("fvin_id").value); 
 *		formData.append("ajax", document.getElementById("ajax").value);
 *
 * @param string $param (hidden|append)
 * @return string
 */
public function tok_upload_formData($param) {

	if (!isset($this->conf['ajax_parameter'])) {
		throw new Exception("ajax_parameter missing", print_r($this->conf, true));
	}

	if (!is_array($this->conf['ajax_parameter'])) {
		$this->conf['ajax_parameter'] = conf2kv($this->conf['ajax_parameter'], ':', ',');
	}

	if ($param == 'hidden') {
		$res = "let f = this.element.parentNode;\n";
		foreach ($this->conf['ajax_parameter'] as $key => $value) {
			if (substr($value, 0, 1) != '#') {
				$res .= 'this.options.rkHiddenInput("'.basename($key).'", "'.$value.'");'."\n";
			}
		}
	}
	else if ($param == 'append') {
		$res = '';
		foreach ($this->conf['ajax_parameter'] as $key => $value) {
			if (substr($value, 0, 1) == '#') {
				$res .= 'formData.append("'.basename($key).'", document.getElementById("'.basename($value).'").value);'."\n";
			}
			else {
				$res .= 'formData.append("'.basename($key).'", document.getElementById("'.basename($key).'").value);'."\n";
			}
		}
	}
	else {
		throw new Exception('no parameter use hidden|append', "param=[$param]");
	}

	return $res;
}


/**
 * Return path to thumbnail of $file.
 */
private function getThumbnail($file, $http_path = false) {
	// \rkphplib\lib\log_debug("TUpload.getThumbnail:122> file=$file http_path=$http_path");
	$tpic = new TPicture();

	$tpic->tok_picture_init([ 
		'picture_dir' => $this->conf['save_in'], 
		'name' => basename($file),
		'source' => $file,
		'target' => $this->conf['save_in'].'/tbn/'.basename($file),
		'resize' => $this->conf['thumbnail'] 
		]);

	$resize_pic = $tpic->resize();
	$target = $http_path ? THttp::httpGet('abs_path').'/'.$resize_pic : $resize_pic;

	// \rkphplib\lib\log_debug("TUpload.getThumbnail:136> return $target ($resize_pic)");
	return $target;
}


/**
 * Return list of existing files. Return format depends on p.mode (e.g. dropzone).
 * Parameter are mode, images (=a1.jpg, ...), save_in and thumbnail (last two 
 * parameter can be defined in upload:init).
 * 
 * @tok {upload:exists}mode=dropzone|#|images={get:images}|#|url_prefix=|#|save_in=...|#|thumbnail=...{:upload}
 * 
 * @throws
 * @param hash $p
 * @return string
 * @return json
 */
public function tok_upload_exists($p) {

	if (empty($this->conf['save_in'])) {
		if (!empty($p['save_in'])) {
			$this->conf['save_in'] = $p['save_in'];
		}
		else {
			if (!empty($p['images'])) {
				throw new Exception('parameter save_in is empty', print_r($this->conf, true));
			}

			// \rkphplib\lib\log_debug("TUpload.tok_upload_exists:164> return [] - no pictures");
			return '[]';
		}
	}

	if (!Dir::exists($this->conf['save_in'])) {
		// \rkphplib\lib\log_debug("TUpload.tok_upload_exists:170> return [] - no such directory ".$this->conf['save_in']);
		return '[]';
	}

	if (empty($this->conf['thumbnail'])) {
		if (!empty($p['thumbnail'])) {
			$this->conf['thumbnail'] = $p['thumbnail'];
		}
		else {
			throw new Exception('parameter thumbnail is empty');
		}
	}

	// \rkphplib\lib\log_debug("TUpload.tok_upload_exists:183> save_in=".$this->conf['save_in']." p: ".print_r($p, true));
	if (empty($p['mode'])) {
		throw new Exception('missing mode parameter');
	}
	else if ($p['mode']	== 'dropzone') {
		$entries = Dir::entries($this->conf['save_in']);
		$url_prefix = THttp::httpGet('abs_path');
		$list = [];

		foreach ($entries as $entry) {
			$ii = File::imageInfo($entry, false);

			if (!empty($ii['width'])) {
				$tbn_url = $this->getThumbnail($entry, true);
				$url = $url_prefix.'/'.$entry;
				// \rkphplib\lib\log_debug("TUpload.tok_upload_exists:198> entry=$entry tbn=$tbn_url");
				$info = [ 'name' => basename($entry), 'size' => File::size($entry), 'mime' => $ii['mime'], 
					'path' => $entry, 'url' => $url, 'tbnUrl' => $tbn_url ];
				array_push($list, $info);
			}
		}

		return JSON::encode($list);
	}
	else {
		throw new Exception('invalid mode parameter '.$p['mode']);
	}
}


/**
 * Same as {upload:init}scan=1{:upload}{get:upload_upload_file}
 * @see tok_upload_init
 */
public function tok_upload_save(string $name, array $p) : string {
	$p['scan'] = 1;
	$this->tok_upload_init($name, $p);
	$rfile = 'upload_'.$this->getUploadName($name, $p).'_file';
	return empty($_REQUEST[$rfile]) ? '' : $_REQUEST[$rfile];
}


/**
 * Execute upload ([upload:init:name]...[:upload]). Export _REQUEST[upload_name_(saved|file|name)]. Parameter:
 *
 * name: upload (=default) overwrite with $p['upload'] or $name
 * upload: upload (=default)
 * url: retrieve upload from url
 * stream: yes=upload is stream, no=ignore stream, []=normal post upload
 * save_in: save directory (auto-create) (default = data/upload/name)
 * save_dir: if set remove from path export
 * save_as: basename (default = [at]name) @see getSaveAs
 * acceptedFiles: .jpg, .png, ...
 * type: image 
 * table_id: e.g. shop_customer.id:382
 * remove_image: (default = {get:remove_image})
 * replace_image: (default = {get:replace_image})
 * overwrite: yes|no (only if save_as is empty) - default = no (add suffix _nn)
 * thumbnail= (default = '' = no thumbnail) e.g. 120x120^
 * min_width: nnn 
 * min_height: nnn
 * max_size: nnn 
 * jpeg2jpg: 1 (=default)
 * image_ratio: image.width % image.height
 * image_convert: execute convert command
 * ajax_output: AJAX_TEMPLATE
 * scan: 0 (if true parse upload)
 *
 * If ajax_output is specified, print parsed {tpl:AJAX_TEMPLATE} and exit.
 *
 * @param string $name 
 * @param hash $p
 */
public function tok_upload_init(string $name, array $p) : void {
	$scan = !empty($name) || !empty($p['scan']) || (empty($p['name']) && !empty($p['upload']));
	unset($p['scan']);

	$name = $this->getUploadName($name, $p);
	$p['upload'] = $name;

	// reset save upload export
	$_REQUEST['upload_'.$name.'_saved'] = '';
	$_REQUEST['upload_'.$name.'_file'] = '';
	$_REQUEST['upload_'.$name] = '';

	if ($scan) {
		$p['save_in'] = empty($p['save_in']) ? 'data/.tmp/'.$name : $p['save_in'];
		$p['save_as'] = empty($p['save_as']) ? '@name' : $p['save_as'];
		$p['overwrite'] = empty($p['overwrite']) ? 'yes' : $p['overwrite'];
	}

	if (!isset($p['remove_image']) && !empty($_REQUEST['remove_image'])) {
		$p['remove_image'] = $_REQUEST['remove_image'];
	}
	else if (!isset($p['replace_image']) && !empty($_REQUEST['replace_image'])) {
		$p['replace_image'] = $_REQUEST['replace_image'];
	}

	if (!isset($p['jpeg2jpg'])) {
		$p['jpeg2jpg'] = 1;
	}

	// \rkphplib\lib\log_debug([ "TUpload.tok_upload_init:285> name=[$name] <1>", $p ]);

	if (!isset($this->options['_default'])) {
		$this->options['_default'] = $p;
		$this->conf = $p;
	}
	else {
		$this->options[$name] = $p;
		$this->conf = array_merge($this->options['_default'], $this->options[$name]);
	}

	if ($scan) {
		$this->tok_upload_conf($name, [ 'scan' => 1 ]);
	}
}


/**
 * If $name is set use name. Otherwise use $p[name].
 * If both are unset use default name 'upload'.
 *
 * @throws
 * @param string $name
 * @param hash $p
 * @return string
 */
private function getUploadName($name, $p) {
	
	if (!empty($p['upload'])) {
		if (!empty($name) && $name != $p['upload']) {
			throw new Exception('upload name mismatch', 'name=['.$name.'] != p.upload=['.$p['upload'].']');
		}

		$name = $p['upload'];
	}
	else if (empty($name)) {
		$name = 'upload';
	}

	return $name;
}


/**
 * Set optional custom upload configuration (in case of multi-upload).
 * Call tok_upload_scan if scan=1.
 * 
 * @tok {upload:conf:file}save_as=...{:upload}
 * @tok {upload:conf}upload=logo|#|save_as=...|#|scan=1{:upload} 
 *
 * @throws
 * @param string $name
 * @param hash $p
 */
public function tok_upload_conf($name, $p) {

	$name = $this->getUploadName($name, $p);
	$scan = !empty($p['scan']);
	unset($p['scan']);

	if (count($p) > 0) {
		// \rkphplib\lib\log_debug("TUpload.tok_upload_conf:346> name=[$name] p: ".print_r($p,true));
		$this->tok_upload_init($name, $p);
	}

	if (isset($this->conf['ajax_parameter']) && $this->conf['ajax_parameter']['module'] == 'dropzone') {
		$this->tok->setVar('dz_name', $name);
		$this->tok->setVar('dz_paramName', $name);

		if (!empty($this->conf['maxFiles'])) {
			$this->conf['dz_maxFiles'] = $this->conf['maxFiles'];
		}
	
		if (!empty($this->conf['acceptedFiles'])) {
			$this->conf['dz_acceptedFiles'] = $this->conf['acceptedFiles'];
		}
		else {
			throw new Exception('missing required parameter acceptedFiles');
		}

		foreach ($this->conf as $key => $value) {
			if (substr($key, 0, 3) == 'dz_') {
				$this->tok->setVar($key, $value);
			}
		}
	}

	if ($scan) {
		$this->tok_upload_scan($name);
	}
}


/**
 * Scan upload. Only do something if name == basename(conf.save_in).
 *
 * @tok {upload:scan:logo}
 *
 * @param string $name (=upload)
 */
public function tok_upload_scan($name = 'upload') {

	if ((!empty($_REQUEST['ajax']) && $_REQUEST['ajax'] != $name && $_REQUEST['ajax'] != 'upload') || isset($this->options['_done_'.$name])) {
		// \rkphplib\lib\log_debug("TUpload.tok_upload_scan:388> return - name=[$name] _REQUEST=".print_r($_REQUEST, true));
		return;
	}

	$this->conf = isset($this->options[$name]) ? array_merge($this->options['_default'], $this->options[$name]) : $this->options['_default'];
	// \rkphplib\lib\log_debug("TUpload.tok_upload_scan:393> name=[$name] this.conf: ".print_r($this->conf, true));

	try {

	if (!empty($this->conf['save_in']) && !Dir::exists($this->conf['save_in'])) {
		Dir::create($this->conf['save_in'], 0, true);
	}

	if (!empty($this->conf['remove_image'])) { 
		if (defined('SETTINGS_DSN') && !empty($this->conf['table_id']) && strpos($this->conf['remove_image'], $this->conf['upload']) === 0) {
			$this->removeImage();
		}
		else {
			$this->removeFSImages();
		}
	}
	else if (!empty($this->conf['replace_image']) && !empty($this->conf['table_id']) && 
						strpos($this->conf['replace_image'], $this->conf['upload']) === 0) {
		$this->replaceImage();
	}

	$upload_type = '';

	if (!empty($this->conf['stream'])) {
		if ($this->conf['stream'] == 'yes') {
			$upload_type = 'stream';
		}
	}
	else {
		$upload_type = $this->scanFiles($name);
	}

	if ($upload_type == 'file') {
		$this->saveFileUpload($name);
	}
	else if ($upload_type == 'multiple_files') {
		$max = empty($this->conf['max']) ? 0 : intval($this->conf['max']);
		$this->saveMultipleFileUpload($name, $max);			
	}

/*
	else if ($upload_type == 'stream') {
		$this->_save_stream();
		$this->_check_upload();
	}
	else if (!empty($_REQUEST['use_existing']) && $_REQUEST['use_existing'] == 'yes' && !empty($this->_conf['save_as'])) {
		$this->_use_existing();
	}
	else if (!empty($this->_conf['previous_upload'])) {
		$this->_use_existing($this->_conf['previous_upload']);
	}
	else if (!empty($_REQUEST['use_other'])) {
		$this->_use_other();
	}
	else if (!empty($p['url']) && !empty($_REQUEST[$p['url']])) {
		$this->_retrieve_from_url($_REQUEST[$p['url']]);
	}
*/

	if (!empty($this->conf['ajax_output']) && !empty($this->options['@plugin_action'])) {
		print $this->tok->callPlugin('tpl', $this->conf['ajax_output']);
		exit(0);
	}

	}
	catch (\Exception $e) {
		if (!empty($p['ajax_output'])) {
			Exception::httpError(400, '@ajax { "error": 1, "error_message": "Exception in TUpload.tok_upload_init('.
				$name.')", "exception": "'.$e->getMessage().'" }');
		}
		else {
			Exception::logError($e);
		}
	}

	// \rkphplib\lib\log_debug("TUpload.tok_upload_scan:468> return");
}


/**
 * Check if _FILES contains $name files.
 *
 * @param string $name
 * @return string [|files|multiple_files]
 */
private function scanFiles($name) {
	$fup = $name;

	// \rkphplib\lib\log_debug("TUpload.scanFiles:481> name=[$name] _FILES: ".print_r($_FILES, true));
	if (!isset($_FILES[$fup]) && isset($_FILES[$fup.'_'])) {
		// catch select/upload box
		$fup .= '_';
	}

	if (!isset($_FILES[$fup]) || (empty($_FILES[$fup]['name']) && empty($_FILES[$fup]['tmp_name']))) {
		// \rkphplib\lib\log_debug("TUpload.scanFiles:488> return - no single _FILES[$fup] upload");
		return '';
	} 

	if (is_array($_FILES[$fup]['name']) && count($_FILES[$fup]['name']) == 1 && 
			(empty($_FILES[$fup]['name'][0]) && empty($_FILES[$fup]['tmp_name'][0]))) {
		// \rkphplib\lib\log_debug("TUpload.scanFiles:494> exit - no multi _FILES[$fup] upload");
		return '';
	}

	if (is_array($_FILES[$fup]['error'])) {
		$error = str_replace('0', '', join('', $_FILES[$fup]['error']));
		if ($error) {
			$this->error(join('. ', $_FILES[$fup]['error']));
		}
	}
	else if (!empty($_FILES[$fup]['error'])) {
		$this->error($_FILES[$fup]['error']);
	}

	// \rkphplib\lib\log_debug("TUpload.scanFiles:508> _FILES[$fup]: ".print_r($_FILES[$fup], true));
	if (is_array($_FILES[$fup]['tmp_name'])) {
		return 'multiple_files';
	}
	else if (!empty($_FILES[$fup]['tmp_name']) && $_FILES[$fup]['tmp_name'] != 'none') {
		return 'file';
	}

	return '';
}


/**
 * Remove image if conf.remove_image=name:num and table_id=table.id:val is set.
 *
 */
private function removeImage() {
	list ($name, $num) = explode(':', $this->conf['remove_image']);
	list ($tmp, $id_val) = explode(':', $this->conf['table_id']);
	list ($table, $id_col) = explode('.', $tmp);

	$db = Database::getInstance(SETTINGS_DSN, [
		'select_images' => "SELECT {:=^name} FROM {:=^table} WHERE {:=^id_col}={:=id_val}",
		'update_images' => "UPDATE {:=^table} SET {:=^name}={:=images} WHERE {:=^id_col}={:=id_val}"
		]);

	$r = [ 'name' => $name, 'table' => $table, 'id_col' => $id_col, 'id_val' => $id_val, 'images' => '' ];

	$query = $db->getQuery('select_images', $r);
	// \rkphplib\lib\log_debug("TUpload.removeImage:537> name=$name num=$num table=$table $id_col=$id_val - query: $query");
	$dbres = $db->selectOne($query);
	$images = split_str(',', $dbres[$name]);
	$remove_img = array_splice($images, $num - 1, 1);
	$r['images'] = join(',', $images);
	// \rkphplib\lib\log_debug("TUpload.removeImage:542> r.images=".$r['images']." remove_img=".$remove_img[0]." images: ".print_r($images, true));

	$path_prefix = empty($this->conf['save_dir']) ? '' : $this->conf['save_dir'].'/';
	$update_query = $db->getQuery('update_images', $r);
	// \rkphplib\lib\log_debug("TUpload.removeImage:546> update_query (remove: ".$path_prefix.$remove_img[0]."): $update_query");
	$db->execute($update_query);
	File::remove($path_prefix.$remove_img[0]);
	$this->options['@plugin_action'] = 1;
}


/**
 * Remove images from filesystem. Use conf.save_in|save_as|remove_image.
 * Export _REQUEST[removed_files|removed_filenum].
 */
private function removeFSImages() {
	$remove = [];

	$rm_file = basename($this->conf['remove_image']);
	
	if (!empty($this->conf['save_in'])) {
		array_push($remove, $this->conf['save_in'].'/'.$rm_file);

		if (!empty($this->conf['thumbnail'])) {
			$resize_dir = str_replace([ '>', '<', '!', '^' ], [ 'g', 'l', 'x', '' ], $this->conf['thumbnail']);
			array_push($remove, $this->conf['save_in']."/tbn/$resize_dir/$rm_file");
		}
	}
	else {
		throw new Exception('ToDo - removeFSImages');
	}

	$removed = [];

	for ($i = 0; $i < count($remove); $i++) {
		$file = $remove[$i];
		if (File::exists($file)) {
			// \rkphplib\lib\log_debug("TUpload.removeFSImages:579> remove $file");
			$this->options['@plugin_action'] = 1;
			array_push($removed, $file);
			File::remove($file);
		}
	}

	$_REQUEST['removed_filenum'] = count($removed);
	$_REQUEST['removed_files'] = join("\n", $removed);
}


/**
 * Replace image if conf.replace_image=name:num and table_id=table.id:val is set.
 *
 */
private function replaceImage() {
	list ($name, $num) = explode(':', $this->conf['replace_image']);
	list ($tmp, $id_val) = explode(':', $this->conf['table_id']);
	list ($table, $id_col) = explode('.', $tmp);

	$db = Database::getInstance(SETTINGS_DSN, [
		'select_images' => "SELECT {:=^name} FROM {:=^table} WHERE {:=^id_col}={:=id_val}",
		'update_images' => "UPDATE {:=^table} SET {:=^name}={:=images} WHERE {:=^id_col}={:=id_val}"
		]);

	$r = [ 'name' => $name, 'table' => $table, 'id_col' => $id_col, 'id_val' => $id_val, 'images' => '' ];

	$query = $db->getQuery('select_images', $r);
	// \rkphplib\lib\log_debug("TUpload.replaceImage:608> name=$name num=$num table=$table $id_col=$id_val - query: $query");
	$dbres = $db->selectOne($query);
	$images = split_str(',', $dbres[$name]);
	$r['images'] = $dbres[$name]; // images entry in database does not change

	// recursion: upload image ...
	$name = $this->conf['upload'];
	$this->conf['replace_image'] = '';
	// recursion is single file upload - images column is single file ($target) afterwards
	$this->tok_upload_init($name, $this->conf);

	$saved = isset($_REQUEST['upload_'.$name.'_saved']) ? $_REQUEST['upload_'.$name.'_saved'] : '';
  $target = isset($_REQUEST['upload_'.$name.'_file']) ? $_REQUEST['upload_'.$name.'_file'] : '';

	if ($saved != 'yes' || empty($target)) {
		$this->error('no replace upload');	
	}

	// reset upload export
	unset($_REQUEST['upload_'.$name]);
	unset($_REQUEST['upload_'.$name.'_saved']);
	unset($_REQUEST['upload_'.$name.'_file']);

	$old_img = $images[$num - 1];
	$path_prefix = empty($this->conf['save_dir']) ? '' : $this->conf['save_dir'].'/';
	// \rkphplib\lib\log_debug("TUpload.replaceImage:633> move $path_prefix$target to $path_prefix$old_img - r.images=".$r['images']);
	File::move($path_prefix.$target, $path_prefix.$old_img);

	$update_query = $db->getQuery('update_images', $r);
	// \rkphplib\lib\log_debug("TUpload.replaceImage:637> exit - update_query: $update_query");
}


/**
 * Export error message as _REQUEST[upload_NAME_error]. 
 * Set _REQUEST[upload_error], _REQUEST[upload_NAME(_file)] = '' and _REQUEST[upload_NAME_saved] = ''.
 *
 * @throws
 * @param string $message
 */
private function error($message) {
	$name = $this->conf['upload'];
  $_REQUEST['upload_'.$name.'_saved'] = '';
  $_REQUEST['upload_'.$name.'_file'] = '';
  $_REQUEST['upload_'.$name] = '';
  $_REQUEST['upload_'.$name.'_error'] = $message;

	$_REQUEST['upload_error'] = 1;

	throw new Exception('upload error', "$name: $message");
}


/**
 * Save upload. Autocreate conf.save_in directory. 
 * Use save_as=@count|@base_count|NAME(_nn.suffix).
 *
 * @see getSaveAs 
 * @param string $fup
 * @param int $max
 */
private function saveMultipleFileUpload($fup, $max) {
	// \rkphplib\lib\log_debug("TUpload.saveMultipleFileUpload:670> fup=$fup max=$max conf: ".print_r($this->conf, true));
	Dir::create($this->conf['save_in'], 0777, true);

	$file_list = [];
	$save_list = [];
	$max = ($max == 0) ? count($_FILES[$fup]['tmp_name']) : min($max, count($_FILES[$fup]['tmp_name']));

	for ($i = 0; $i < $max; $i++) {
		$fname = $_FILES[$fup]['name'][$i];
		$tmp_name = $_FILES[$fup]['tmp_name'][$i];
		$target = $this->conf['save_in'].'/'.$this->getSaveAs($fname, $tmp_name, ($i + 1));

		if (!empty($this->conf['image_convert'])) {
			$this->convertImage($tmp_name, $target);
		}
		else {
			File::move($tmp_name, $target, 0666);
		}

		if (!empty($this->conf['save_dir'])) {
			$target = str_replace($this->conf['save_dir'].'/', '', $target);
		}

		array_push($save_list, $target);
		array_push($file_list, $fname);
	}

	$this->options['@plugin_action'] = 1;
	$_REQUEST['upload_'.$fup.'_file'] = join(',', $save_list);
	$_REQUEST['upload_'.$fup] = join(',', $file_list);

	if (count($_FILES[$fup]['tmp_name']) == count($save_list)) {
		$_REQUEST['upload_'.$fup.'_saved'] = 'yes';
		$_REQUEST['upload_'.$fup.'_error'] = '';
	}
	else {
		$_REQUEST['upload_'.$fup.'_saved'] = '';
		$_REQUEST['upload_'.$fup.'_error'] = (count($_FILES[$fup]['tmp_name']) - count($save_list)).' missing';
	}
}


/**
 * Save upload. Autocreate conf.save_in directory. 
 *
 * @see getSaveAs 
 * @param string $fup
 */
private function saveFileUpload($fup) {
	// \rkphplib\lib\log_debug([ "TUpload.saveFileUpload:719> fup=$fup <1>\n<2>", $this->conf, $_FILES[$fup] ]);
	Dir::create($this->conf['save_in'], 0777, true);
	$target = $this->conf['save_in'].'/'.$this->getSaveAs($_FILES[$fup]['name'], $_FILES[$fup]['tmp_name']);

	if (!empty($this->conf['image_convert'])) {
		$this->convertImage($_FILES[$fup]['tmp_name'], $target);
	}
	else {
		// \rkphplib\lib\log_debug("TUpload.saveFileUpload:727> move fup=$fup {$_FILES[$fup]['tmp_name']} to {$target}");
		File::move($_FILES[$fup]['tmp_name'], $target, 0666);
	}

	if (!empty($this->conf['save_dir'])) {
		$target = str_replace($this->conf['save_dir'].'/', '', $target);
	}

	$this->options['@plugin_action'] = 1;
	$_REQUEST['upload_'.$fup.'_saved'] = 'yes';
	$_REQUEST['upload_'.$fup.'_file'] = $target;
	$_REQUEST['upload_'.$fup.'_error'] = '';
	$_REQUEST['upload_'.$fup] = $_FILES[$fup]['name'];
}


/**
 * Convert image to cmyk, gray or use custom convert or gm command.
 * Use conf.image_convert=cmyk|gray|convert {:=source} {:=target}|gm command.
 *
 * @param string $source
 * @param string $target
 */
private function convertImage($source, $target) {

	throw new Exception('ToDo ...');
/*
	$img_info = lib_exec("identify -verbose {:=image}", array('image' => $_REQUEST['upload_file']));
	$cmd = '';

	if ($this->_conf['image_convert'] == 'gray') {
		$is_gray = 	strpos($img_info, 'exif:ColorSpace: 65535') !== false ||
			strpos($img_info, 'Type: Grayscale') !== false;
			
		if (!$is_gray) {
			$cmd = 'convert {:=source} -colorspace gray {:=target}';
		}
	}
	else if ($this->_conf['image_convert'] == 'cmyk') {
		
		$is_cmyk = strpos($img_info, 'Colorspace: CMYK') !== false ||
			strpos($img_info, 'Type: ColorSeparation') !== false;	
	
		if (!$is_cmyk) {
			if (!empty($this->_conf['image_convert_icc'])) {
				$tmp = explode(':', $this->_conf['image_convert_icc']);
				$profile = '';
		
				for ($i = 0; $i < count($tmp); $i++) {
					$icc_file = (basename($tmp[$i]) == $tmp[$i]) ? '/usr/share/color/icc/'.$tmp[$i] : $tmp[$i];							
					File::exists($icc_file, true);
					$profile .= ' -profile '.$icc_file;
				}
			
				$cmd = 'convert {:=source}'.$profile.' {:=target}';
			}	
			else {
				$cmd = 'convert {:=source} -colorspace cmyk {:=target}';		
			}
		}
	}
	else if ((substr($this->_conf['image_convert'], 0, 8) == 'convert ' || substr($this->_conf['image_convert'], 0, 3) == 'gm ') && 
						strpos($this->_conf['image_convert'], '{:=source}') !== false &&
						strpos($this->_conf['image_convert'], '{:=target}') !== false) {
		$cmd = $this->_conf['image_convert'];
	}
	else {  
		throw new Exception("invalid image converstion [".$this->_conf['image_convert']."] use cmyk|gray|convert ...");
	}
	
  if ($cmd) {
 		$orig_file = dirname($_REQUEST['upload_file']).'/pre_convert_'.basename($_REQUEST['upload_file']);
 		File::copy($_REQUEST['upload_file'], $orig_file);
 		lib_exec($cmd, array('source' => $orig_file, 'target' => $_REQUEST['upload_file'])); 				
 	}
*/
}


/**
 * Return save_as base filename (normalize suffix). Save as values:
 *
 * - @md5: md5(upload).SUFFIX 
 * - @name: UPLOAD_NAME.SUFFIX (= default)
 * - @count: 01.SUFFIX
 * - @base_count: BASE_01.SUFFIX
 * - @original: FILE_NAME.SUFFIX
 * - value: basename(value) (e.g. logo, floorplan, ...)
 *
 * @throws
 * @param string $upload_file
 * @param string $temp_file
 * @param int $nc (default = 0) 
 * @return string
 */
private function getSaveAs($upload_file, $temp_file, $nc = 0) {
	$base = File::basename($upload_file, true);
	$suffix = File::suffix($upload_file, true);
	$save_as = isset($this->conf['save_as']) ? $this->conf['save_as'] : null;
	$fsize = File::size($temp_file);
	$res = '';

	if (!empty($this->conf['jpeg2jpg']) && $suffix == '.jpeg') {
		$suffix = '.jpg';
	}
	
	// \rkphplib\lib\log_debug("TUpload.getSaveAs:833> upload_file=$upload_file temp_file=$temp_file nc=$nc base=$base suffix=$suffix fsize=$fsize");

	if ($fsize == 0) {
		$this->error('upload is 0 byte');
	}

	if (!empty($this->conf['max_size']) && $fsize > $this->conf['max_size']) {
   	$this->error('upload is &gt; '.$this->conf['max_size'].' byte');
  }
 
	if (!empty($p['type']) && $p['type'] == 'image') {
		$ii = File::imageInfo($temp_file);
		$suffix = $ii['suffix'];

		if (!empty($p['min_width']) && $ii['width'] < $p['min_width']) {
			$this->error('image width '.$ii['width'].' &lt; '.$p['min_width']);
		}

		if (!empty($p['min_height']) && $ii['height'] < $p['min_height']) {
			$this->error('image height '.$ii['height'].' &lt; '.$p['min_height']);
		}

/* ToDo:
		if (!empty($p['image_ratio'])) {
			$this->checkImageRatio($ii['width'], $ii['height']);
		}
*/
	}

	if (empty($suffix)) {
		$this->error('could not detect suffix');
	}		

	if ($save_as == '@md5') {
		$res = File::md5($temp_file);
	}
	else if ($save_as == '@name') {
		$res = $base.$suffix;
	}
	else if ($save_as == '@original') {
		$res = $base.$suffix;
	}
	else if ($save_as == '@count') {
		$res = sprintf("%02d", $nc).$suffix;
	}
	else if ($save_as == '@base_count') {
		$res = $base.'_'.sprintf("%02d", $nc).$suffix;
	}
	else if ($this->conf['maxFiles'] > 1 && $nc > 0) {
		$res = $base.'_'.sprintf("%02d", $nc).$suffix;
	}
	else if (is_null($save_as)) {
		$res = $this->conf['upload'].$suffix;
	}

/*
	if (File::exists($this->conf['save_in'].'/'.$this->conf['rel_path']) && $this->_conf['overwrite'] != 'yes') {
		$this->conf['rel_path'] = basename(File::uniqueName($this->_conf['save_in'].'/'.$this->conf['rel_path']));
	}
*/

	if (!empty($this->conf['acceptedFiles'])) {
		if ($this->conf['acceptedFiles'] == 'image/*') {
			$this->conf['acceptedFiles'] = [ '.jpg', '.png', '.gif' ];
		}

		$allow_suffix = split_str(',', $this->conf['acceptedFiles']);
	
		if (!in_array($suffix, $allow_suffix)) {
			$this->error('invalid suffix '.$suffix.' use conf.acceptedFiles='.join(',', $allow_suffix));
		}
	}

	// \rkphplib\lib\log_debug("TUpload.getSaveAs:906> return [$res]");
	return $res;
}


}

