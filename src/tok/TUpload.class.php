<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TPicture.class.php');
require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/THttp.class.php');
require_once(__DIR__.'/../Database.class.php');
require_once(__DIR__.'/../Dir.class.php');
require_once(__DIR__.'/../File.class.php');
require_once(__DIR__.'/../JSON.class.php');
require_once(__DIR__.'/../lib/split_str.php');

use \rkphplib\tok\TPicture;
use \rkphplib\tok\THttp;
use \rkphplib\Exception;
use \rkphplib\Database;
use \rkphplib\FSEntry;
use \rkphplib\JSON;
use \rkphplib\File;
use \rkphplib\Dir;



/**
 * File upload.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TUpload implements TokPlugin {

/** @var Tokenizer $tok */
protected $tok = null;

/** @var hash $conf */
protected $conf = [];

// private $_image = array();



/**
 * 
 */
public function getPlugins($tok) {
	$this->tok = $tok;

	$plugin = [];
  $plugin['upload:init'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY | TokPlugin::REDO;
  $plugin['upload:get'] = 0;
  $plugin['upload:file'] = 0;
  $plugin['upload:exists'] = TokPlugin::NO_PARAM |  TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['upload'] = 0;

	return $plugin;
}


/**
 * Return path to thumbnail of $file.
 */
private function getThumbnail($file, $http_path = false) {
	// \rkphplib\lib\log_debug("TUpload.getThumbnail> file=$file http_path=$http_path");
	$tpic = new TPicture();

	$tpic->tok_picture_init([ 
		'picture_dir' => $this->conf['save_in'], 
		'name' => basename($file),
		'source' => $file,
		'target' => $this->conf['save_in'].'/tbn/'.basename($file),
		'resize' => $this->conf['thumbnail'] 
		]);

	$resize_pic = $tpic->resize();
	$target = $http_path ? dirname(THttp::httpGet('abs_url')).'/'.$resize_pic : $resize_pic;

	// \rkphplib\lib\log_debug("TUpload.getThumbnail> return $target ($resize_pic)");
	return $target;
}


/**
 * Return list of existing files. Return format depends on p.mode (e.g. dropzone).
 * 
 * @throws
 * @param hash $p
 * @return string
 */
public function tok_upload_exists($p) {
	if (empty($p['mode'])) {
		throw new Exception('missing mode parameter');
	}
	else if ($p['mode']	== 'dropzone') {
		$entries = Dir::entries($this->conf['save_in']);
		$url_prefix = dirname(THttp::httpGet('abs_url'));
		$list = [];

		foreach ($entries as $entry) {
			$ii = File::imageInfo($entry, false);

			if (!empty($ii['width'])) {
				$tbn_url = $this->getThumbnail($entry, true);
				$url = $url_prefix.'/'.$entry;
				\rkphplib\lib\log_debug("entry=$entry tbn=$tbn_url");
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
 * Execute upload ([upload:init:name]...[:upload]). Export _REQUEST[upload_name_(saved|file|name)]. Parameter:
 * 
 * url: retrieve upload from url
 * stream: yes=upload is stream, no=ignore stream, []=normal post upload
 * save_in: save directory (auto-create) (default = data/upload/name)
 * save_dir: if set remove from path export
 * save_as: basename (default = [at]name) @see getSaveAs
 * allow_suffix: jpg, png, ...
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
 *
 * If ajax_output is specified, print parsed {tpl:AJAX_TEMPLATE} and exit.
 * 
 * @param hash $p
 */
public function tok_upload_init($name, $p) {

	try {

	// reset save upload export
	$_REQUEST['upload_'.$name.'_saved'] = 'no';
	$_REQUEST['upload_'.$name.'_file'] = '';
	$_REQUEST['upload_'.$name] = '';

	$p['upload'] = $name;
	$p['save_in'] = empty($p['save_in']) ? 'data/upload/'.$name : $p['save_in'];
	$p['save_as'] = empty($p['save_as']) ? '@name' : $p['save_as'];
	$p['overwrite'] = empty($p['overwrite']) ? 'yes' : $p['overwrite'];

	$upload_type = '';

	if (!isset($p['remove_image']) && !empty($_REQUEST['remove_image'])) {
		$p['remove_image'] = $_REQUEST['remove_image'];
	}
	else if (!isset($p['replace_image']) && !empty($_REQUEST['replace_image'])) {
		$p['replace_image'] = $_REQUEST['replace_image'];
	}

	if (!isset($p['jpeg2jpg'])) {
		$p['jpeg2jpg'] = 1;
	}

	$this->conf = $p;

	// \rkphplib\lib\log_debug("TUpload.tok_upload_init($name, ...)> this.conf: ".print_r($this->conf, true));

	if (!empty($p['remove_image']) && !empty($p['table_id']) && strpos($p['remove_image'], $name) === 0) {
		$this->removeImage();
	}
	else if (!empty($p['replace_image']) && !empty($p['table_id']) && strpos($p['replace_image'], $name) === 0) {
		$this->replaceImage();
	}
	else if (!empty($p['stream'])) {
		if ($p['stream'] == 'yes') {
			$upload_type = 'stream';
		}
	}
	else {
		$fup = $name;

		if (!isset($_FILES[$fup]) && isset($_FILES[$fup.'_'])) {
			// catch select/upload box
			$fup .= '_';
		}

		if (!isset($_FILES[$fup]) || (empty($_FILES[$fup]['name']) && empty($_FILES[$fup]['tmp_name']))) {
			// \rkphplib\lib\log_debug("TUpload.tok_upload_init> exit - no single _FILES[$fup] upload");
			return;
		} 

		if (is_array($_FILES[$fup]['name']) && count($_FILES[$fup]['name']) == 1 && 
				(empty($_FILES[$fup]['name'][0]) && empty($_FILES[$fup]['tmp_name'][0]))) {
			// \rkphplib\lib\log_debug("TUpload.tok_upload_init> exit - no multi _FILES[$fup] upload");
			return;
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

		// \rkphplib\lib\log_debug("_FILES[$fup]: ".print_r($_FILES[$fup], true));
		if (is_array($_FILES[$fup]['tmp_name'])) {
			$upload_type = 'multiple_files';
		}
		else if (!empty($_FILES[$fup]['tmp_name']) && $_FILES[$fup]['tmp_name'] != 'none') {
			$upload_type = 'file';
		}
	}

	if ($upload_type == 'file') {
		$this->saveFileUpload($fup);
	}
	else if ($upload_type == 'multiple_files') {
		$max = empty($p['max']) ? 0 : intval($p['max']);
		$this->saveMultipleFileUpload($fup, $max);			
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

	if (!empty($p['ajax_output'])) {
		print $this->tok->callPlugin('tpl', $p['ajax_output']);
		exit(0);
	}

	}
	catch (\Exception $e) {
		// do nothing ... 
		$msg = "TUpload.tok_upload_init> Exception: ".$e->getMessage();
		$trace = $e->getFile()." on line ".$e->getLine()."\n".$e->getTraceAsString();
		$internal = property_exists($e, 'internal_message') ? "INFO: ".$e->internal_message : '';
		// \rkphplib\lib\log_debug("$msg\n$trace\n$internal");
	}

	// \rkphplib\lib\log_debug("TUpload.tok_upload_init> return");
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
	// \rkphplib\lib\log_debug("TUpload.removeImage> name=$name num=$num table=$table $id_col=$id_val - query: $query");
	$dbres = $db->selectOne($query);
	$images = \rkphplib\lib\split_str(',', $dbres[$name]);
	$remove_img = array_splice($images, $num - 1, 1);
	$r['images'] = join(',', $images);
	// \rkphplib\lib\log_debug("TUpload.removeImage> r.images=".$r['images']." remove_img=".$remove_img[0]." images: ".print_r($images, true));

	$path_prefix = empty($this->conf['save_dir']) ? '' : $this->conf['save_dir'].'/';
	$update_query = $db->getQuery('update_images', $r);
	// \rkphplib\lib\log_debug("TUpload.removeImage> update_query (remove: ".$path_prefix.$remove_img[0]."): $update_query");
	$db->execute($update_query);
	File::remove($path_prefix.$remove_img[0]);
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
	// \rkphplib\lib\log_debug("TUpload.replaceImage> name=$name num=$num table=$table $id_col=$id_val - query: $query");
	$dbres = $db->selectOne($query);
	$images = \rkphplib\lib\split_str(',', $dbres[$name]);
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
	// \rkphplib\lib\log_debug("TUpload.replaceImage> move $path_prefix$target to $path_prefix$old_img - r.images=".$r['images']);
	File::move($path_prefix.$target, $path_prefix.$old_img);

	$update_query = $db->getQuery('update_images', $r);
	// \rkphplib\lib\log_debug("TUpload.replaceImage> exit - update_query: $update_query");
}


/**
 * Export error message as _REQUEST[upload_NAME_error]. 
 * Set _REQUEST[upload_error], _REQUEST[upload_NAME(_file)] = '' and _REQUEST[upload_NAME_saved] = no.
 *
 * @throws
 * @param string $message
 */
private function error($message) {
	$name = $this->conf['upload'];
  $_REQUEST['upload_'.$name.'_saved'] = 'no';
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
	// \rkphplib\lib\log_debug("TUpload.saveMultipleFileUpload> fup=$fup max=$max conf: ".print_r($this->conf, true));
	Dir::create($this->conf['save_in'], 0777, true);

	$name = $this->conf['upload'];
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

	$_REQUEST['upload_'.$name.'_file'] = join(',', $save_list);
	$_REQUEST['upload_'.$name] = join(',', $file_list);

	if (count($_FILES[$fup]['tmp_name']) == count($save_list)) {
		$_REQUEST['upload_'.$name.'_saved'] = 'yes';
		$_REQUEST['upload_'.$name.'_error'] = '';
	}
	else {
		$_REQUEST['upload_'.$name.'_saved'] = '';
		$_REQUEST['upload_'.$name.'_error'] = (count($_FILES[$fup]['tmp_name']) - count($save_list)).' missing';
	}
}


/**
 * Save upload. Autocreate conf.save_in directory. 
 *
 * @see getSaveAs 
 * @param string $fup
 */
private function saveFileUpload($fup) {
	// \rkphplib\lib\log_debug("TUpload.saveFileUpload> fup=$fup conf: ".print_r($this->conf, true));
	Dir::create($this->conf['save_in'], 0777, true);
	$target = $this->conf['save_in'].'/'.$this->getSaveAs($_FILES[$fup]['name'], $_FILES[$fup]['tmp_name']);

	if (!empty($this->conf['image_convert'])) {
		$this->convertImage($_FILES[$fup]['tmp_name'], $target);
	}
	else {
		File::move($_FILES[$fup]['tmp_name'], $target, 0666);
	}

	if (!empty($this->conf['save_dir'])) {
		$target = str_replace($this->conf['save_dir'].'/', '', $target);
	}

	$name = $this->conf['upload'];
	$_REQUEST['upload_'.$name.'_saved'] = 'yes';
	$_REQUEST['upload_'.$name.'_file'] = $target;
	$_REQUEST['upload_'.$name.'_error'] = '';
	$_REQUEST['upload_'.$name] = $_FILES[$fup]['name'];
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
		lib_abort("invalid image converstion [".$this->_conf['image_convert']."] use cmyk|gray|convert ...");
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
	$save_as = $this->conf['save_as'];
	$fsize = File::size($temp_file);
	$res = '';

	if (!empty($this->conf['jpeg2jpg']) && $suffix == '.jpeg') {
		$suffix = '.jpg';
	}
	
	// \rkphplib\lib\log_debug("TUpload.getSaveAS> upload_file=$upload_file temp_file=$temp_file nc=$nc base=$base suffix=$suffix fsize=$fsize");

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
	else {
		$res = ($nc > 0) ? basename($save_as).'_'.sprintf("%02d", $nc).$suffix : basename($save_as).$suffix;
	}

/*
	if (File::exists($this->conf['save_in'].'/'.$this->conf['rel_path']) && $this->_conf['overwrite'] != 'yes') {
		$this->conf['rel_path'] = basename(File::uniqueName($this->_conf['save_in'].'/'.$this->conf['rel_path']));
	}
*/

	if (!empty($this->conf['allow_suffix'])) {
		$allow_suffix = \rkphplib\lib\split_str(',', $this->conf['allow_suffix']);
		$my_suffix = substr($suffix, 1);
			
		if (!in_array($my_suffix, $allow_suffix)) {
			$this->error('invalid suffix '.$suffix.' use: '.join('|', $allow_suffix));
		}
	}

	// \rkphplib\lib\log_debug("TUpload.getSaveAS> return [$res]");
	return $res;
}


}

