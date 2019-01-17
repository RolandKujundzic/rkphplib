<?php

namespace rkphplib\tok;

require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/../Dir.class.php');
require_once(__DIR__.'/../File.class.php');
require_once(__DIR__.'/../lib/execute.php');
require_once(__DIR__.'/../lib/split_str.php');

use \rkphplib\Exception;
use \rkphplib\FSEntry;
use \rkphplib\File;
use \rkphplib\Dir;


/**
 * Server side picture handling plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TPicture implements TokPlugin {

/** @var Tokenizer $tok */
protected $tok = null;

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
	$this->tok = $tok;

  $plugin = [];
  $plugin['picture:init'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['picture:src'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY; 
	$plugin['picture:list'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['picture'] = 0;
  return $plugin;
}


/**
 * Set configuration hash. Default parameter:
 *
 * picture_dir: data/picture/upload
 * preview_dir: data/picture/preview
 * convert.resize: convert -colorspace sRGB -geometry {:=resize} {:=source} {:=target}
 * convert.resize^: convert -colorspace sRGB -geometry {:=resize}^ -gravity center -extent {:=resize} {:=source} {:=target}
 * module: convert (=default) | gdlib
 * use_cache: 1 | dimension:300x300
 * ignore_missing: 1
 * default: default.jpg
 * reset: 1 
 *
 * @param array[string]string $p
 * @return ''
 */
public function tok_picture_init($p) {

	if (!isset($p['reset'])) {
		$p['reset'] = 1;
	}

	$tok = is_null($this->tok) ? Tokenizer::$site : $this->tok;
	if (is_null($tok)) {
		$tok = new Tokenizer();
	}

	if (count($this->conf) == 0 || !empty($p['reset'])) {
	  $default['picture_dir'] = 'data/picture/upload';
  	$default['preview_dir'] = 'data/picture/preview';
 		$default['convert.resize'] = 'convert -colorspace sRGB -geometry '.
			$tok->getTag('resize').' '.$tok->getTag('source').' '.$tok->getTag('target');
		$default['convert.resize^'] = 'convert -colorspace sRGB -geometry '.
			$tok->getTag('resize').'^ -gravity center -extent '.$tok->getTag('resize').' '.
			$tok->getTag('source').' '.$tok->getTag('target');
	  $default['default'] = 'default.jpg';
		$default['ignore_missing'] = 1;
		$default['module'] = 'convert';
		$default['use_cache'] = 1;
		$default['resize'] = '';
		$default['reset'] = 1;

		$this->conf = $default;
	}

	foreach ($p as $key => $value) {
		$this->conf[$key] = $value;
	}

	// \rkphplib\lib\log_debug('tok_picture_init> this.conf: '.print_r($this->conf, true));
}


/**
 * Check conf keys. Reset source and target. Add values from $p.
 *
 * @throws
 * @param hash $p
 */
private function checkConf($p) {
	$this->conf['source'] = '';
	$this->conf['target'] = '';

	foreach ($p as $key => $value) {
		$this->conf[$key] = $value;
	}

	$required = [ 'default' ];

	foreach ($required as $key) {
		if (empty($this->conf[$key])) {
			throw new Exception('missing picture plugin parameter '.$key, print_r($this->conf, true));
		}
	}
}

/**
 * Return picture list as comma separted list.
 *
 * @tok {picture:list}names={:=names}{:picture} - apply {picture:src}names={:=names}|#|num=N{:picture}
 *
 * @see tok_picture_src
 * @param hash $p
 * @return string
 */
public function tok_picture_list($p) {
	if (empty($p['names'])) {
		throw new Exception('missing parameter names');
	}

	$picture_list = \rkphplib\lib\split_str(',', $p['names']);
	unset($p['names']);
	$res = [];

	for ($i = 0; !empty($picture_list[$i]) && $i < count($picture_list); $i++) {
		$p['name'] = $picture_list[$i];
		$picture = $this->tok_picture_src($p);

		if (!empty($picture)) {
			array_push($res, str_replace(',', '\\,', $picture));
		}
	}

	return join(',', $res);
}


/**
 * Return picture source path. Apply conversion if configured. Parameter:
 *
 *  source: auto-compute
 *  target: auto-compute
 *  name: e.g. 01.jpg (set source = picture_dir/01.jpg)
 *  names: e.g. 01.jpg, 02.jpg, 03.jpg (set source = picture_dir/01.jpg - use num=2 for picture_dir/03.jpg)
 *  num: use with names = pic[0], pic[1], ...
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

	$this->checkConf($p);

	if (!empty($this->conf['target_dir']) && !empty($this->conf['name']) && !empty($this->conf['use_cache'])) {
		return $this->conf['target_dir'].'/'.$this->conf['name'];
	}

	$this->computeImgSource();

	// \rkphplib\lib\log_debug('tok_picture_src> this.conf: '.print_r($this->conf, true));
	if (empty($this->conf['source'])) {
		return '';
	}

	if (!empty($this->conf['resize'])) {
		if (empty($this->conf['target']) && !empty($this->conf['picture_dir'])) {
			$this->conf['target'] = str_replace($this->conf['picture_dir'], $this->conf['preview_dir'], $this->conf['source']);
		}

		$this->resize();
	}
	else {
		$this->conf['target'] = $this->conf['source'];
	}

	$img = $this->conf['target'];

	if (!empty($this->conf['abs_path']) && !empty($this->conf['rel_path'])) {
		$img = str_replace($this->conf['abs_path'], $this->conf['rel_path'], $img);
	}

	$this->conf = $conf; 
	return dirname($img).'/'.rawurlencode(basename($img));
}
    

/**
 * Set conf.source. 
 *
 */
private function computeImgSource() {

	if (!empty($this->conf['names'])) {
		$list = \rkphplib\lib\split_str(',', $this->conf['names']);

		if (empty($list[0]) && count($list) > 1) {
			// fix ",01.jpg, 02.jpg, ..." into "01.jpg, 02.jpg, ..."
			array_shift($list);
		}

		$num = empty($this->conf['num']) ? 0 : intval($this->conf['num']);
		$this->conf['source'] = !empty($list[$num]) ? $this->conf['picture_dir'].'/'.$list[$num] : '';
	}
	else if (!empty($this->conf['name'])) {
		$this->conf['source'] = $this->conf['picture_dir'].'/'.$this->conf['name'];
	}

	if (empty($this->conf['source']) && !empty($this->conf['examples'])) {
		$list = \rkphplib\lib\split_str(',', $this->conf['examples']);
		$n = rand(0, count($list) - 1);
		$this->conf['source'] = $this->conf['picture_dir'].'/'.$list[$n];
	}

	if (empty($this->conf['source']) || !File::exists($this->conf['source'])) {
		$default = (basename($this->conf['default']) == $this->conf['default']) ?
			$this->conf['picture_dir'].'/'.$this->conf['default'] : $this->conf['default'];

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
 * Resize conf.source to conf.target according to conf.resize. Use trailing ^ for exact resize
 * (center and clip image). 
 *
 * @see http://www.imagemagick.org/script/convert.php
 * @see http://www.imagemagick.org/script/command-line-processing.php#geometry
 * @return string (=conf.target)
 */
public function resize() {
	$resize = $this->conf['resize'];
  $resize_dir = str_replace([ '>', '<', '!', '^' ], [ 'g', 'l', 'x', '' ], $resize);

	if (basename(dirname($this->conf['target'])) != $resize_dir) {
		$target_dir = dirname($this->conf['target']).'/'.$resize_dir;
		$this->conf['target'] = $target_dir.'/'.basename($this->conf['target']);
	}

	// \rkphplib\lib\log_debug('resize> this.conf: '.print_r($this->conf, true));
	if (File::exists($this->conf['target']) && !empty($this->conf['use_cache'])) {
		if (mb_substr($this->conf['use_cache'], 0, 10) == 'dimension:') {
			$wxh = mb_substr($this->conf['use_cache'], 10);
			$ii = File::imageInfo($this->conf['target']);
			if ($ii['width'].'x'.$ii['height'] == $wxh) {
				return $this->conf['target'];
			}
		}
		else {
			return $this->conf['target'];
		}
  }

  Dir::create(dirname($this->conf['target']), 0777, true);

	if ($this->conf['module'] == 'convert') {
		if (substr($resize, -1) == '^') {
			// resize to exact extend, center and clip image
			$resize = substr($resize, 0, -1);
			$r = [ 'resize' => $resize, 'source' => $this->conf['source'], 'target' => $this->conf['target'] ];
			// \rkphplib\lib\log_debug('resize> '.$this->conf['convert.resize^'].' r: '.print_r($r, true));
			\rkphplib\lib\execute($this->conf['convert.resize^'], $r); 
		}
		else {
			$r = [ 'resize' => $resize, 'source' => $this->conf['source'], 'target' => $this->conf['target'] ];
			// \rkphplib\lib\log_debug('resize> '.$this->conf['convert.resize'].' r: '.print_r($r, true));
			\rkphplib\lib\execute($this->conf['convert.resize'], $r);
		}
	}
	else if ($this->conf['module'] == 'gdlib') {
		throw new Exception('todo');
	}

 	FSEntry::chmod($this->conf['target'], 0666);

	return $this->conf['target'];
}


}
