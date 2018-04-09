<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Dir.class.php');
require_once(__DIR__.'/../File.class.php');
require_once(__DIR__.'/../lib/split_str.php');

use \rkphplib\Exception;
use \rkphplib\FSEntry;
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
  $plugin['upload:init'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['upload:get'] = 0;
  $plugin['upload:file'] = 0;
  $plugin['upload:exists'] = 0;
	$plugin['upload'] = 0;

	return $plugin;
}


/**
 * Execute upload ([upload:init:name]...[:upload]). Export _REQUEST[upload_name_(saved|file|name)]. Parameter:
 * 
 * url: retrieve upload from url
 * stream: yes=upload is stream, no=ignore stream, []=normal post upload
 * save_in: save directory (auto-create) (default = data/upload/name)
 * save_as: basename (default = name) @see getSaveAs
 * allow_suffix: jpg, png, ...
 * type: image 
 * overwrite: yes|no (only if save_as is empty) - default = no (add suffix _nn)
 * min_width: nnn 
 * min_height: nnn
 * max_size: nnn 
 * image_ratio: image.width % image.height
 * image_convert: execute convert command
 * 
 * @param hash $p
 */
public function tok_upload_init($name, $p) {
	// reset save upload export
	$_REQUEST['upload_'.$name.'_saved'] = 'no';
	$_REQUEST['upload_'.$name.'_file'] = '';
	$_REQUEST['upload_'.$name] = '';

	$p['upload'] = $name;
	$p['save_in'] = empty($p['save_in']) ? 'data/upload/'.$name : $p['save_in'];
	$p['save_as'] = empty($p['save_as']) ? 'count' : $p['save_as'];
	$p['overwrite'] = empty($p['overwrite']) ? 'yes' : $p['overwrite'];

	$this->conf = $p;

	$upload_type = '';

	if (!empty($p['stream'])) {
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

		if (!empty($_FILES[$fup]['name']) && $_FILES[$fup]['error']) {
			$this->error($_FILES[$fup]['error']);
		}

		if (isset($_FILES[$fup]) && is_array($_FILES[$fup]) && !empty($_FILES[$fup]['tmp_name']) && $_FILES[$fup]['tmp_name'] != 'none') {
			$upload_type = 'file';
		}
	}

	if ($upload_type == 'file') {
		$this->saveFileUpload($fup);
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
}


/**
 * Export error message as _REQUEST[upload_NAME_error]. 
 * Set _REQUEST[upload_error], _REQUEST[upload_NAME(_file)] = '' and _REQUEST[upload_NAME_saved] = no.
 *
 * @exit
 * @param string $message
 */
private function error($message) {
	$name = $this->conf['upload'];
  $_REQUEST['upload_'.$name.'_saved'] = 'no';
  $_REQUEST['upload_'.$name.'_file'] = '';
  $_REQUEST['upload_'.$name] = '';
  $_REQUEST['upload_'.$name.'_error'] = $message;

	$_REQUEST['upload_error'] = 1;

	exit(0);
}


/**
 * Save upload. Autocreate conf.save_in directory. 
 *
 * @see getSaveAs 
 * @param string $fup
 */
private function saveFileUpload($fup) {
	\rkphplib\lib\log_debug("TUpload.saveUpload> fup=$fup conf: ".print_r($this->conf, true));
	Dir::create($this->conf['save_in'], 0777, true);
	$target = $this->conf['save_in'].'/'.$this->getSaveAs($_FILES[$fup]['name'], $_FILES[$fup]['tmp_name']);

	if (!empty($this->conf['image_convert'])) {
		$this->convertImage($_FILES[$fup]['tmp_name'], $target);
	}
	else {
		File::move($_FILES[$fup]['tmp_name'], $target, 0666);
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
 */
private function getSaveAs($upload_file, $temp_file) {
	$base = File::basename($upload_file, true);
	$suffix = File::suffix($upload_file, true);
	$save_as = $this->conf['save_as'];
	$fsize = File::size($temp_file);
	$res = '';
	
	\rkphplib\lib\log_debug("TUpload.getSaveAS> upload_file=$upload_file temp_file=$temp_file base=$base suffix=$suffix fsize=$fsize");

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

		if (!empty($p['image_ratio'])) {
			$this->checkImageRatio($ii['width'], $ii['height']);
		}
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
		$res = sprintf("%02d", 1).$suffix;
	}
	else if ($save_as == '@base_count') {
		$res = $base.'_'.sprintf("%02d", 1).$suffix;
	}
	else {
		$res = basename($save_as).$suffix;
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

	\rkphplib\lib\log_debug("TUpload.getSaveAS> return [$res]");
	return $res;
}


/**
 * Compare image ratio with conf.image_ratio. If wrong call error(...).
 * Shave image if conf.image_shave=yes.
 *
 * @param int $w
 * @param int $h
 */
private function checkImageRatio($w, $h) {
	$w = $this->_upload_file('image_width');
	$w_min = intval($this->_conf['min_width']);

	$h = $this->_upload_file('image_height');
	$h_min = intval($this->_conf['min_height']); 	
	
	$rn = strlen($this->_conf['image_ratio']) - strpos($this->_conf['image_ratio'], '.') - 1;
  $ratio = round($w / $h, $rn);
  	
	if (floatval($this->_conf['image_ratio']) != $ratio) {
		if (!empty($this->_conf['image_shave']) && $this->_conf['image_shave'] == 'yes' && 
				$h > 0 && $h_min > 0 && $w > 0 && $w_min > 0) {
			$ra = min(round($w / $w_min, 4), round($h / $h_min, 4));
			$w2 = round($w_min * $ra, 0);
			$h2 = round($h_min * $ra, 0);
			$sw = ($w - $w2 > 1) ? round(($w - $w2) / 2, 0) : 0;
			$sh = ($h - $h2 > 1) ? round(($h - $h2) / 2, 0) : 0;
  		
			throw new Exception('ToDo ...');
	
			$orig_file = dirname($_REQUEST['upload_file']).'/pre_shave_'.basename($_REQUEST['upload_file']);
			File::copy($_REQUEST['upload_file'], $orig_file);
			lib_exec('convert -shave '.$sw.'x'.$sh." {:=upload} {:=shaved}", 
				array('upload' => $orig_file, 'shaved' => $_REQUEST['upload_file'])); 				
		}
		else {
			$this->error("image ratio != $ratio");
		}
	}
}


/**
 * 
 */
public function tokCall($action, $param, $arg) {
  $res = '';

  if ($action == 'upload') {
    $this->_upload(lib_arg2hash($arg));
  }
  else if ($action == 'upload_file') {
    $res = $this->_upload_file($param, false);
  }
  else if ($action == 'upload_exists') {
    $res = $this->_upload_exists($param);
  }
  else if ($action == 'upload_get') {
    $res = $this->_upload_get($param);
  }

  return $res;
}


/**
 * Return [upload_get:max_file|max_post|has_uploadprogress].
 * Parameter is max_file|max_post|has_uploadprogress.
 * 
 * @param string $param
 * @return string
 */
private function _upload_get($param) {
  $res = '';

  if ($param == 'max_file') {
    $res = ini_get('upload_max_filesize');
  }
  else if ($param == 'max_post') {
    $res = ini_get('post_max_size');
  }
  else if ($param == 'has_uploadprogress') {
    if (function_exists("uploadprogress_get_info")) {
     $log = ini_get("uploadprogress.file.filename_template");

     if (strpos($log, '%s') == false) {
       // default log is: /tmp/upt_%s.txt
       lib_abort("invalid uploadprogress logfile", "log=[$log]");
     }

     // is uploadprogress directory writable ?

     $res = 1;
    }
  }

  return $res;
}


/**
 * Return [upload_exists:param].
 * Result is "yes" if upload exists.
 * 
 * @param string $param
 * @return string
 */
private function _upload_exists($param) {

  if ($param) {
    $file = $this->_conf[$param.'_file'];
  }
  else {
    $file = $this->_conf['save_in'].'/'.$this->_conf['save_as'];
  }

  $res = FSEntry::isFile($file, false) ? 'yes' : '';

  return $res;
}


/**
 * Return [upload_file:size|path|md5|suffix|name|save_as|save_in|image_XXX] info.
 * 
 * @param string $action
 * @param boolean $required
 * @return string
 */
private function _upload_file($action, $required = true) {
  $res = '';

  if (empty($_REQUEST['upload_file']) ||  empty($_REQUEST['upload_name'])) {

    if ($required) {
      lib_abort("no upload file");
    }
    else {

      if (!empty($this->_conf['optional']) && $this->_conf['optional'] == 'yes') {
        // upload is not required ...
        return '';
      }

      if (!empty($this->_conf['error_redirect'])) {
        $this->_error_redirect('no_upload');
      }
      else {
        lib_abort("no upload file");
      }
    }
  }

  $file = $_REQUEST['upload_file'];

  if ($action == 'size') {
    $res = File::size($file, false);
  }
  else if ($action == 'path') {
    $res = $file;
  }
  else if ($action == 'md5') {
    $res = File::md5($file);
  }
  else if ($action == 'suffix') {
    $res = File::suffix($file);
  }
  else if ($action == 'name' && !empty($_REQUEST['upload_name'])) {
    $res = $_REQUEST['upload_name'];
  }
  else if ($action == 'save_as' && !empty($_REQUEST['upload_file'])) {
    $res = basename($_REQUEST['upload_file']);
  }
  else if ($action == 'save_in' && !empty($_REQUEST['upload_file'])) {
    $res = dirname($_REQUEST['upload_file']);
  }
  else if ($action == 'file' && !empty($_REQUEST['upload_file'])) {
    $res = $_REQUEST['upload_file'];
  }
  else if (substr($action, 0, 6) == 'image_') {

    if (!isset($this->_image['file']) || $this->_image['file'] != $file) {
      $this->_image = File::imageInfo($file, false);
      $this->_image['type'] = $this->_image['mime'];
    }

    $info = substr($action, 6);
    if (!isset($this->_image[$info])) {
      lib_abort("no such action [upload_file:$action]");
    }

    $res = $this->_image[$info];
  }
  else {
    lib_abort("no such action [upload_file:$action]");
  }

  return $res;
}


/**
 * Save file upload stream.
 */
private function _save_stream() {
	
	if (empty($_SERVER['HTTP_X_FILE_NAME']) || empty($_SERVER['CONTENT_LENGTH'])) {
  	lib_abort("Error retrieving headers");
	}

	$file_name = $_SERVER['HTTP_X_FILE_NAME'];
  if (!empty($this->_conf['fix_name'])) {
  	if ($this->_conf['fix_name'] == 'yes') {
    	$file_name = preg_replace('/[^0-9A-Za-z_\-\.\,]/', '', $file_name);
  	}
  }
	
  // if upload name already exists ... try basename_[01,02,...,99].suffix
  $save_as = ($this->_conf['overwrite'] == 'yes') ? $file_name : 
  	basename(File::uniqueName($this->_conf['save_in'].'/'.$file_name));
    
  // use save_as parameter to overwrite existing files or overwrite = yes!!!
  if (!empty($this->_conf['save_as'])) {
    $save_as = $this->getSaveAs($_SERVER['HTTP_X_FILE_NAME']);
  }
  
  $target = $this->_conf['save_in'].'/'.$save_as;
  Dir::create($this->_conf['save_in'], 0777, true);
  file_put_contents($target, file_get_contents("php://input")); 
	File::chmod($target, 0666);
			
  $_REQUEST['upload_saved'] = 'yes';
  $_REQUEST['upload_file'] = $target;
  $_REQUEST['upload_name'] = $_SERVER['HTTP_X_FILE_NAME'];
}


/**
 *
 */
private function _use_other() {

  $key = $_REQUEST['use_other'].'_file';

  if (!empty($this->_conf[$key]) && FSEntry::isFile($this->_conf[$key], false)) {
    Dir::create($this->_conf['save_in'], 0777, true);
    File::copy($this->_conf[$key], $this->_conf['save_in'].'/'.$this->_conf['save_as']);
    $this->_use_existing();
  }
}

/**
 * 
 * @param string $target
 */
private function _use_existing($target = '') {

  if (!$target) {
    $target = $this->_conf['save_in'].'/'.$this->_conf['save_as'];
  }

  if (FSEntry::isFile($target, false)) {
    $_REQUEST['upload_saved'] = 'yes';
    $_REQUEST['upload_reused'] = 'yes';
    $_REQUEST['upload_file'] = $target;
    $_REQUEST['upload_name'] = basename($target);
  }
}


/**
 * Redirect to conf.error_redirect url is not empty.
 * Use xxx={:=upload_error} in conf.error_redirect url to get abort reason.
 * 
 * @param string $reason
 */
private function _error_redirect($reason) {

	if (empty($this->_conf['error_redirect'])) {
		return;
  }

  $url = str_replace('{:=upload_error}', $reason, $this->_conf['error_redirect']);
  lib_redirect($url);
}


/**
 * Retrieve upload from url.
 * 
 * @param string $url
 */
private function _retrieve_from_url($url) {

  $save_as = empty($this->_conf['save_as']) ? basename($url) : $this->_conf['save_as'];
  $target = $this->_conf['save_in'].'/'.$save_as;

  Dir::create($this->_conf['save_in'], 0777, true);
  File::httpGet($url, $target);

  $_REQUEST['upload_saved'] = 'yes';
  $_REQUEST['upload_file'] = $target;
  $_REQUEST['upload_name'] = basename($url);
}


}

