<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Dir.class.php');
require_once(__DIR__.'/../File.class.php');

use \rkphplib\Exception;
use \rkphplib\File;
use \rkphplib\Dir;


/**
 * Server side picture handling plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TPicture implements TokPlugin {

/** @var array[string]string $conf */
protected $conf = [];


/**
 * Return Tokenizer plugin list:
 *
 *  picture
 *
 * @param Tokenizer $tok
 * @return map<string:int>
 */
public function getPlugins($tok) {
  $plugin = [];
  $plugin['picture:init'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['picture:src'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY; 
  $plugin['picture'] = 0;
  return $plugin;
}


/**
 * Set configuration hash. Default parameter:
 *
 * picture_dir: data/picture/upload
 * preview_dir: data/picture/preview
 * convert.resize: convert {:=_colorspace} -geometry {:=resize} {:=source} {:=target}
 * module: convert (=default) | gdlib
 * use_cache: 1
 * ignore_missing: 1
 * default: default.jpg
 * reset: 1 
 *
 * @param array[string]string $p
 * @return ''
 */
public function function tok_picture_init($p) {

	if (count($this->conf) == 0 || !empty($this->conf['reset'])) {
	  $default['picture_dir'] = 'data/picture/upload';
  	$default['preview_dir'] = 'data/picture/preview';
  	$default['convert.resize'] = 'convert {:=_colorspace} -geometry {:=resize} {:=source} {:=target}';
	  $default['default'] = 'default.jpg';
		$default['ignore_missing'] = 1;
		$default['mode'] = empty (=default) | center | box | custom (define convert.custom)
		$default['module'] = 'convert';
		$default['use_cache'] = 1;
		$default['resize'] = '';
		$default['reset'] = 1;

		$this->conf = $default;
	}

	foreach ($p as $key => $value) {
		$this->conf[$key] = $value;
	}
}


/**
 * Return picture source path. Apply conversion if configured. Parameter:
 *
 *  source: auto-compute
 *  target: auto-compute
 *  name: e.g. 01.jpg (set source = picture_dir/name)
 *  resize: e.g. 250x, 250x100, ... (see convert) 
 *  abs_path:
 *  rel_path:
 *
 * Every parameter from picture:init can be (temporary) overwritten.
 * Fallback is picture_dir/default or no-picture if conf.ignore_missing=1.
 *
 * @param array[string]string $p
 * @return string|empty
 */
public function tok_picture_src($p) {

  $conf = $this->conf;

	foreach ($p as $key => $value) {
		$this->conf[$key] = $value;
	}

	$this->computeImgSource();

	if (empty($this->conf['source'])) {
		return '';
	}

	if (empty($this->conf['target']) && !empty($this->conf['picture_dir'])) {
		$this->conf['target'] = str_replace($this->conf['picture_dir'], $this->conf['preview_dir'], $this->conf['source']);
	}

	if (!empty($this->conf['resize'])) {
		$this->resize($this->conf['source'], $this->conf['target']);
	}

	$img =  $this->conf['target'];

	if (!empty($this->conf['abs_path']) && !empty($this->conf['rel_path'])) {
		$img = str_replace($this->conf['abs_path'], $this->conf['rel_path'], $img);
	}
 
  return $img;
}
    

/**
 * Set conf.source. 
 *
 */
private function computeImgSource() {
	if (!empty($this->conf['source'])) {
		// do nothing ...
		return;
	}

	if (!empty($this->conf['name'])) {
		$this->conf['source'] = $this->conf['picture_dir'].'/'.$this->conf['name'];
	}

	if (empty($this->conf['source']) || !File::exists($this->conf['source'])) {
		$default = $this->conf['picture_dir'].'/'.$this->conf['default'];

		if (!File::exists($default)) {
			if (empty($this->conf['ignore_missing'])) {
				throw new Exception('image missing', "img=".$this->conf['source']." default=$default");
			}

			$this->conf['source'] = '';
		}

		$this->conf['source'] = $default;
	}
}


/**
 * Resize source to target. Allow configuration:
 *
 *  resize= a
 *  resize.a= 150x150
 *
 * @see http://www.imagemagick.org/script/convert.php
 * @param string $source
 * @param string $target
 */
public function resize($source, $target) {
	$resize = $this->conf['resize'];

	if (isset($this->conf['resize.'.$resize])) {
		$resize = $this->conf['resize.'.$resize];
	}

	if (!preg_match('/^([0-9]+)x([0-9]+)[\<\>\!]?$/', $resize)) {
		throw new Exception('invalid resize '.$resize);
	}

  $mode = empty($p['mode']) ? '' : $p['mode'];

	if (File::exists($this->conf['target']) && !empty($this->conf['use_cache'])) {
  }
  else {
  	$mode_prefix = (substr($mode, 0, 4) == 'cmd.') ? substr($mode, 4, 3) : substr($mode, 0, 1);   
  	$rc = $this->_resize_cache($img_file, $mode_prefix.$resize, $resize_subdir);
  }

  if ($rc['use_cache']) {
    return $rc['target'];
  }

  if (!empty($this->_conf['module']) && $this->_conf['module'] == 'gdlib') {
    $res = $this->_gdlib($img_file, $rc['target'], $resize, $mode);
  }
  else if ($mode == 'center') {
    $res = $this->_center($img_file, $rc['target'], $resize);
  }
  else if ($mode == 'box') {
    $res = $this->_thumbnail($img_file, $rc['target'], $resize);
  }
  else if (substr($mode, 0, 4) == 'cmd.' && !empty($this->_conf[$mode])) {
  	$res = $this->_imagic_cmd(array('cmd' => $this->_conf[$mode], 'resize' => $resize, 
  		'source' => $img_file, 'target' => $rc['target']));
  }
  else {
    $res = $this->_scale($img_file, $rc['target'], $resize);
  }

  $this->_resize_cache_save_export();

  if (!empty($this->_conf['watermark'])) {
    $res = $this->_watermark($res);
  }

  return $res;
}


//-----------------------------------------------------------------------------
private function _watermark($img, $target_dir = '') {

  if (basename($img) == 'default.jpg' || basename($img) == 'default.png') {
    // do nothing
    return $img;
  }

  if ($this->_conf['watermark'] == 'yes') {
    $h_png = empty($this->_conf['watermark_h']) ? 
      $this->_conf['picture_dir'].'/watermark_h.png' : $this->_conf['watermark_h'];

    $w_png = empty($this->_conf['watermark_w']) ? 
      $this->_conf['picture_dir'].'/watermark_w.png' : $this->_conf['watermark_w'];
  }
  else {
    // do nothing
    return $img;
  }

  if ($target_dir) {
    // copy img to target_dir
    if (!FSEntry::isDir($target_dir, false)) {
      Dir::create($target_dir);
    }

    File::copy($img, $target_dir.'/'.basename($img));
    $img = $target_dir.'/'.basename($img);
  }

  $img_info = File::imageInfo($img);
  $w = $img_info['width'];
  $h = $img_info['height'];

  $limit = intval($this->_conf['watermark_limit']);
  if ($w <= $limit && $h <= $limit) {
    // do nothing
    return $img;
  }

  $stamp_dir = $this->_conf['preview_dir'].'/watermark';

  if (!FSEntry::isDir($stamp_dir, false)) {
    Dir::create($stamp_dir);
  }

  $stamp = $stamp_dir.'/south_'.$w.'x'.$h.'.png';
  $watermark_png = $w_png;
  $gravity = 'south';

  if (2 * $w < $h) {
    $stamp = $stamp_dir.'/east_'.$w.'x'.$h.'.png';
    $watermark_png = $h_png;
    $gravity = 'east';
  }

  if (!FSEntry::isFile($stamp, false)) {
    $exec_param = array('_colorspace' => $this->_conf['convert.colorspace'], 'wxh' => $w.'x'.$h, 
			'watermark_png' => $watermark_png, 'stamp' => $stamp);
    lib_exec($this->_conf['convert.watermark_stamp'], $exec_param);
    FSEntry::isFile($stamp);
  }

  # -watermark [brightness]%x[saturation]%
  # -dissolve 40
  # -compose difference
  $target = dirname($img).'/wm_'.basename($img);
  $percent = empty($this->_conf['watermark_percent']) ? 30 : intval($this->_conf['watermark_percent']);
  $exec_param = array('_colorspace' => $this->_conf['convert.colorspace'], 'gravity' => $gravity, 
		'percent' => $percent, 'stamp' => $stamp, 'source' => $img, 'target' => $target);
  lib_exec($this->_conf['composite.watermark'], $exec_param);

  File::move($target, $img);

  return $img;
}


//-----------------------------------------------------------------------------
private function _resize_cache_save_export() {

  if (isset($this->_info['save_cache_info'])) {
    $cache_info = $this->_info['save_cache_info'];
    unset($this->_info['save_cache_info']);

    File::save_rw($cache_info, lib_hash2arg($this->_info));
  }

  if (!empty($this->_conf['export_cache'])) {
    $prefix = $this->_conf['export_cache'];

    foreach ($this->_info as $key => $value) {
      $_REQUEST[$prefix.$key] = $value;
    }
  }
}


//-----------------------------------------------------------------------------
private function _target_file($img) {

  if (empty($this->_conf['convert.to_jpg']) && 
      empty($this->_conf['convert.to_png'])) {
    return $img;
  }

  $pos = strrpos($img, '.');
  $base = substr($img, 0, $pos);
  $suffix = strtolower(substr($img, $pos));

  if (strpos($this->_conf['convert.to_jpg'], $suffix) !== false) {
    $suffix = '.jpg';
  }
  else if (strpos($this->_conf['convert.to_png'], $suffix) !== false) {
    $suffix = '.png';    
  }

  $res = $base.$suffix;

  return $res;
}


/**
 * Return resize info hash (use_cache,target).
 * 
 * @param string
 * @param string
 * @param string
 * @return hash
 */
private function _resize_cache($img_src, $resize, $subdir = '') {

  $resize_dir = str_replace('>', 'g', $resize);
  $resize_dir = str_replace('<', 'l', $resize_dir);
  $resize_dir = str_replace('!', 'x', $resize_dir);

  $target_dir = $this->_conf['preview_dir'].'/'.$resize_dir.$subdir;
  $target = $target_dir.'/'.$this->_target_file(basename($img_src));

  $res = array('use_cache' => false, 'target' => $target);

  if (FSEntry::isFile($target, false)) {
    $lm_src = File::lastModified($img_src);
    $lm_target =  File::lastModified($target);

    if ($lm_src <= $lm_target) {
      $res['use_cache'] = true;

      if (!empty($this->_conf['check_cache'])) {
        // cache = size|md5
        $res['use_cache'] = $this->_check_cache($img_src, $target.'.nfo');
      }
    }
  }

  Dir::create($target_dir, 0777);

  return $res;
}


/**
 * Return true if cache file can be used.
 * 
 * @param string $file
 * @param string $file_nfo
 * @return boolean
 */
private function _check_cache($file, $file_nfo) {

  $curr_nfo = array('size' => File::size($file));
  $res = true;

  if ($this->_conf['check_cache'] == 'md5') {
    $curr_nfo['md5'] = File::md5($file);
  }

  if (FSEntry::isFile($file_nfo, false)) {
    $this->_info = lib_arg2hash(File::load($file_nfo));

    foreach ($curr_nfo as $key => $value) {
      if (!isset($this->_info[$key]) || $this->_info[$key] != $value) {
        $res = false;
      }
    }
  }
  else {
    $res = false;
  }

  if (!$res) {
    $this->_info = $curr_nfo;
    $this->_info['save_cache_info'] = $file_nfo;
  }

  return $res;
}


//-----------------------------------------------------------------------------
private function _center($img_src, $target, $resize) {

  $tmp = explode('x', $resize);
  $w_n = intval($tmp[0]);
  $h_n = intval($tmp[1]);

  $this->_info['width'] = $w_n;
  $this->_info['height'] = $h_n;

  $img_info = File::imageInfo($img_src);
  $w = $img_info['width'];
  $h = $img_info['height'];

  if ($w > 0 && $h > 0 && $w_n > 0 && $h_n > 0) {
    $f = min($w/$w_n, $h/$h_n);
    $w_max = intval($w_n * $f);
    $h_max = intval($h_n * $f);
    $x_off = intval(($w - $w_max) / 2);
    $y_off = intval(($h - $h_max) / 2);
    $crop = $w_max.'x'.$h_max.'+'.$x_off.'+'.$y_off;

    $exec_param = array('_colorspace' => $this->_conf['convert.colorspace'], 'crop' => $crop, 
			'source' => $img_src, 'target' => $target);
    lib_exec($this->_conf['convert.crop'], $exec_param);

    if (!FSEntry::isFile($target, false)) {
      lib_warn("crop [$crop] of [$img_src] to [$target] failed");
    }

    $exec_param = array('_colorspace' => $this->_conf['convert.colorspace'], 
			'resize' => $resize, 'source' => $target, 'target' => $target);
    lib_exec($this->_conf['convert.resize'], $exec_param);
  }

  FSEntry::chmod($target, 0666);

  return $target;
}


/**
 * Create thumbnail. Use _conf[transparent_tbn] = yes for *.png with transparent background.
 * 
 * @param string $img_src
 * @param string $target
 * @param resize
 * @return string
 */
private function _thumbnail($img_src, $target, $resize) {

  $w_x_h = explode('x', $resize);
  $w = intval($w_x_h[0]);
  $h = intval($w_x_h[1]);

  $this->_info['width'] = $w;
  $this->_info['height'] = $h;

  $size = '-size '.(2 * $w).'x'.(2 * $h);
  $thumb = "-thumbnail '".($w - 4).'x'.($h - 4).">'";
  $crop = "-crop '".$resize."+0+0!'";


  if (!empty($this->_conf['transparent_tbn']) && lib_bool($this->_conf['transparent_tbn'])) {
		$target = substr($target, 0, -4).'.png';
		$bg = '-background transparent -flatten';
	}
	else {
	  $bg = '-background white -flatten';
	}

  $cmd = "convert {:=_colorspace} $size {:=source} $thumb -gravity center $crop $bg {:=target}";
  $cmd_param = array('_colorspace' => $this->_conf['convert.colorspace'], 'source' => $img_src, 'target' => $target);
  lib_exec($cmd, $cmd_param);

  if (!FSEntry::isFile($target, false)) {
    lib_warn("resize [$resize] of [$img_src] to [$target] failed");
  }
  else {
    FSEntry::chmod($target, 0666);
  }

  return $target;
}


//-----------------------------------------------------------------------------
private function _gdlib($img_src, $target, $resize, $mode) {

  include_once('TGDLib.class.php');

  $w_x_h = explode('x', $resize);

  Dir::create(dirname($target));

  $gd = new TGDLib();
  $gd->resize(intval($w_x_h[0]), intval($w_x_h[1]), $img_src, $target, $mode);

  $this->_info['width'] = $gd->getInfo('width');
  $this->_info['height'] = $gd->getInfo('height');

  FSEntry::chmod($target, 0666);
  return $target;
}


/**
 * Replace "-rotate exif" with correct rotate command.
 * 
 * @param string $cmd
 * @paran string $img_src
 * @return string
 */
private function _rotate_exif($cmd, $img_src) {

	if (strpos($cmd, '-rotate exif') === false) {
		return $cmd;
  }
  
  $rotate = ''; 
  	
  if (exif_imagetype($img_src) && ($exif = @exif_read_data($img_src, 'IFD0')) !== FALSE) {
		if ($exif['Orientation'] == 8) {
			$rotate = '-rotate -90';
		}
		else if ($exif['Orientation'] == 6) {
			$rotate = '-rotate 90';
		}
		else if ($exif['Orientation'] == 3) {
			$rotate = '-rotate 180';
		}
		else if ($exif['Orientation'] == 1) {
			// slant
			$rotate = '-rotate -90';
		}
  }
  	
  return str_replace('-rotate exif', $rotate, $cmd);
}


/**
 * Scale img_src to target. Replace {:=source}, {:=target} and {:=resize}
 * tags in conf[convert.resize].
 * 
 * @param string $img_src
 * @param string $target
 * @param string $resize
 * @return string
 */
private function _scale($img_src, $target, $resize) {

  $exec_param = array('_colorspace' => $this->_conf['convert.colorspace'], 'resize' => $resize, 
		'source' => $img_src, 'target' => $target);
  $cmd = $this->_rotate_exif($this->_conf['convert.resize'], $img_src);

  lib_exec($cmd, $exec_param);

  if (!FSEntry::isFile($target, false)) {
    lib_warn("resize [$resize] of [$img_src] to [$target] failed");
  }
  else {
    FSEntry::chmod($target, 0666);
  }

  $img_info = File::imageInfo($target);
  $this->_info['width'] = $img_info['width'];
  $this->_info['height'] = $img_info['height'];

  return $target;
}


/**
 * Compose resized image with overlay mask.
 * 
 * @param hash $p
 * @return string
 */
private function _imagic_cmd($p) {

	$cmd = $this->_rotate_exif($p['cmd'], $p['source']);
	unset($p['cmd']);
	
  lib_exec($cmd, $p);

  if (!FSEntry::isFile($p['target'], false)) {
    lib_warn('composite ['.$p['resize'].' of ['.$p['source'].'] to ['.$p['target'].'] failed');
  }
  else {
    FSEntry::chmod($p['target'], 0666);
  }

  $img_info = File::imageInfo($p['target']);
  $this->_info['width'] = $img_info['width'];
  $this->_info['height'] = $img_info['height'];

  return $p['target'];
}


}
?>
