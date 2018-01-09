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
  $plugin['picture'] = 0;
  return $plugin;
}


/**
 * Set configuration hash. Default parameter:
 *
 * picture_dir: data/picture/upload
 * preview_dir: data/picture/preview
 * convert.resize: convert -colorspace sRGB -geometry {:=resize} {:=source} {:=target}
 * module: convert (=default) | gdlib
 * use_cache: 1
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

	if (count($this->conf) == 0 || !empty($p['reset'])) {
	  $default['picture_dir'] = 'data/picture/upload';
  	$default['preview_dir'] = 'data/picture/preview';
 		$default['convert.resize'] = 'convert -colorspace sRGB -geometry '.
			$this->tok->getTag('resize').' '.$this->tok->getTag('source').' '.$this->tok->getTag('target');
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
	$this->conf['source'] = '';
	$this->conf['target'] = '';

	$conf = $this->conf;

	foreach ($p as $key => $value) {
		$this->conf[$key] = $value;
	}

	$this->computeImgSource();

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
 
	return $img;
}
    

/**
 * Set conf.source. 
 *
 */
private function computeImgSource() {

	if (!empty($this->conf['names'])) {
		$list = \rkphplib\lib\split_str(',', $this->conf['names']);
		$this->conf['source'] = $this->conf['picture_dir'].'/'.$list[0];
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
 * Resize conf.source to conf.target according to conf.resize. 
 *
 * @see http://www.imagemagick.org/script/convert.php
 */
public function resize() {
	$resize = $this->conf['resize'];

	if (!preg_match('/^([0-9]+)x([0-9]+)[\<\>\!]?$/', $resize)) {
		throw new Exception('invalid resize '.$resize);
	}

  $resize_dir = str_replace([ '>', '<', '!' ], [ 'g', 'l', 'x' ], $resize);

	if (basename(dirname($this->conf['target'])) != $resize_dir) {
		$target_dir = dirname($this->conf['target']).'/'.$resize_dir;
		$this->conf['target'] = $target_dir.'/'.basename($this->conf['target']);
	}

	if (File::exists($this->conf['target']) && !empty($this->conf['use_cache'])) {
		return;
  }

  Dir::create(dirname($this->conf['target']), 0777, true);

	if ($this->conf['module'] == 'convert') {
		$r = [ 'resize' => $resize, 'source' => $this->conf['source'], 'target' => $this->conf['target'] ];
		\rkphplib\lib\execute($this->conf['convert.resize'], $r); 
	}
	else if ($this->conf['module'] == 'gdlib') {
		throw new Exception('todo');
	}

 	FSEntry::chmod($this->conf['target'], 0666);
}


}
