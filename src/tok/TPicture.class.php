<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../Dir.class.php';
require_once __DIR__.'/../File.class.php';
require_once __DIR__.'/../lib/execute.php';
require_once __DIR__.'/../lib/split_str.php';

use rkphplib\Exception;
use rkphplib\FSEntry;
use rkphplib\File;
use rkphplib\Dir;

use function rkphplib\lib\execute;
use function rkphplib\lib\split_str;



/**
 * Server side picture handling plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TPicture implements TokPlugin {

// @var Tokenizer $tok
protected $tok = null;

// @var array[string]string $conf
protected $conf = [];



/**
 * Return {picture:src|init|list}
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

  $plugin = [];
  $plugin['picture:init'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['picture:src'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY; 
	$plugin['picture:list'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['picture:tbn'] = TokPlugin::CSLIST_BODY;
  $plugin['picture'] = 0;
  return $plugin;
}


/**
 * @tok {picture:tbn}1.jpg,2.jpg{:picture} = data/shop/tbn/1.jpg
 * @tok …
 * {picture:tbn:strip}1.jpg,2.jpg,3.jpg{:picture}
 * <div class="thumbnail_strip">
 *   <img src="data/shop/tbn/1.jpg">
 *   <img src="data/shop/tbn/2.jpg">
 *   <img src="data/shop/tbn/3.jpg">
 * </div>
 * @EOL
 */
public function tok_picture_tbn(string $param, array $images) : string {
	$tbn_dir = 'data/shop/tbn';
	$res = '';

	if (empty($param)) {
		$res = count($images) == 0 || empty($images[0]) ? $tbn_dir.'/default.jpg' : $tbn_dir.'/'.$images[0];
	}
	else if ($param == 'strip') {
		$res = '<div class="thumbnail_strip">';

		for ($i = 0;$i < count($images); $i++) {
			$res .= "\n".'<img src="'.$tbn_dir.'/'.$images[$i].'">';
		}
	
		$res .= "\n</div>";
	}

	return $res;
}


/**
 * Set configuration hash. Default parameter:
 *
 * picture_dir: data/picture/upload
 * preview_dir: data/picture/preview 
 * convert.resize: _input,_sRGB,_geometry,_target
 * convert.center: _input,_sRGB,_geometry,_box,_target
 * convert.box: _input,_sRGB,_strip,_trim,_thumbnail,_boxw,_target
 * convert.box_png: _input,_sRGB,_strip,_trim,_thumbnail,_boxt,_target_png
 * convert.cover: _input,_sRGB,_geometry,_crop,_target
 * convert._input: convert {:=source}
 * convert._target: {:=target}
 * convert._target: {:=target_png}
 * convert._resize: -resize {:=resize}
 * convert._thumbnail: -thumbnail {:=resize}
 * convert._geometry: -geometry {:=resize}
 * convert._crop: -crop {:=crop} -extend {:=WxH}
 * convert._sRGB: -colorspace sRGB
 * convert._strip: -strip -quality 85
 * convert._trim: -trim +repage
 * convert._boxw: -gravity center -background white -extent {:=WxH}
 * convert._boxt: -gravity center -background transparent -extent {:=WxH}
 * convert._box: -gravity center -extent {:=WxH}
 * convert: convert.resize (or convert.OTHER or "convert ..." - if resize=WxH^ use convert.center)
 * use_cache: 1 | check_time
 * ignore_missing: 1
 * transparent_tbn: 1
 * default: default.jpg
 * save_as: path/to/target[.jpg] (append suffix, trailing / = use source path)
 * reset: 0
 *
 * @param array[string]string $p
 */
public function tok_picture_init(array $p) : void {
	if (!class_exists('Tokenizer')) {
		require_once __DIR__.'/Tokenizer.class.php';
	}

	$tok = is_null($this->tok) ? Tokenizer::$site : $this->tok;
	if (is_null($tok)) {
		$tok = new Tokenizer();
	}

	if (count($this->conf) == 0 || !empty($p['reset'])) {
		$default['picture_dir'] = 'data/picture/upload';
		$default['preview_dir'] = 'data/picture/preview';
		$default['convert.resize'] = '_input,_sRGB,_geometry,_target';
		$default['convert.center'] = '_input,_sRGB,_geometry,_box,_target';
		$default['convert.box'] = '_input,_sRGB,_strip,_trim,_thumbnail,_boxw,_target';
		$default['convert.box_png'] = '_input,_sRGB,_strip,_trim,_thumbnail,_boxt,_target_png';
		$default['convert.cover'] = '_input,_sRGB,_crop,_geometry,_target';
		$default['convert._input'] = 'convert '.$tok->getTag('source');
		$default['convert._resize'] = '-resize '.$tok->getTag('resize');
		$default['convert._target'] = $tok->getTag('target');
		$default['convert._target_png'] = $tok->getTag('target_png');
		$default['convert._thumbnail'] = '-thumbnail '.$tok->getTag('resize');
		$default['convert._trim'] = '-trim +repage';
		$default['convert._geometry'] = '-geometry '.$tok->getTag('resize');
		$default['convert._crop'] = '-crop '.$tok->getTag('crop');
		$default['convert._sRGB'] = '-colorspace sRGB';
		$default['convert._strip'] = '-strip -quality 85';
		$default['convert._box'] = '-gravity center -extent '.$tok->getTag('WxH');
		$default['convert._boxw'] = '-gravity center -background white -extent '.$tok->getTag('WxH');
		$default['convert._boxt'] = '-gravity center -background transparent -extent '.$tok->getTag('WxH'); // force _target_png
		$default['convert'] = 'convert.resize'; 
		$default['default'] = 'default.jpg';
		$default['ignore_missing'] = 1;
		$default['use_cache'] = 1;
		$default['resize'] = '';
		$default['transparent_tbn'] = 1;

		$this->conf = $default;
	}

	foreach ($p as $key => $value) {
		$this->conf[$key] = $value;
	}

	// \rkphplib\lib\log_debug([ "TPicture.tok_picture_init:163> this.conf: <1>\n<2>", $this->conf, $p ]);
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

	$required = [];

	if (empty($this->conf['ignore_missing'])) {
		array_push($required, 'default');
	}

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
 */
public function tok_picture_list(array $p) : string {
	if (empty($p['names'])) {
		throw new Exception('missing parameter names');
	}

	$picture_list = split_str(',', $p['names']);
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
 */
public function tok_picture_src(array $p) : string {
	$conf = $this->conf;

	$this->checkConf($p);
	$this->computeImgSource();

	// \rkphplib\lib\log_debug('TPicture.tok_picture_src:244> this.conf: '.print_r($this->conf, true));
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
		$list = split_str(',', $this->conf['names']);

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
		$list = split_str(',', $this->conf['examples']);
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
 * Cover resize area with picture.
 * 
 * @param hash $exec_param
 * @return hash
 */
private function convertCover($exec_param) {

	$tmp = explode('x', $exec_param['WxH']);
	$w_n = intval($tmp[0]);
	$h_n = intval($tmp[1]);

	$ii = File::imageInfo($exec_param['source']);
	$w = $ii['width'];
	$h = $ii['height'];

	if ($w > 0 && $h > 0 && $w_n > 0 && $h_n > 0) {
		$f = min($w/$w_n, $h/$h_n);
		$w_max = intval($w_n * $f);
		$h_max = intval($h_n * $f);
		$x_off = intval(($w - $w_max) / 2);
		$y_off = intval(($h - $h_max) / 2);

		$exec_param['crop'] = $w_max.'x'.$h_max.'+'.$x_off.'+'.$y_off;
		$exec_param['cmd'] = 'cover';
	}
	else {
		throw new Exception('invalid image', print_r($exec_param, true));
	}

	return $exec_param;
}


/**
 * Run convert command $p['cmd'] with parameters from $p inserted.
 * @param hash $p
 */
private function runConvertCmd($p) {
	if (empty($p['cmd']) || empty($this->conf['convert.'.$p['cmd']])) {
		throw new Exception('invalid convert command', print_r($p, true));
	}

	$cmd_parts = explode(',', $this->conf['convert.'.$p['cmd']]);
	$jlen = strlen($p['resize_dir']);
	$resize_dir = $p['resize_dir'].'m';
	$cmd = '';

	$map = [ '_input' => '', '_resize' => '', '_target' => '', '_target_png' => '',
		'_thumbnail' => '', '_trim' => 1, '_geometry' => '', '_crop' => 2, '_sRGB' => '', 
		'_strip' => 3, '_boxw' => 4, '_boxt' => 5, '_box' => 6 ];

	$is_png = false;

	foreach ($cmd_parts as $key) {
		if (empty($this->conf['convert.'.$key])) {
			throw new Exception('no such convert command part '.$key, $cmd);
		}

		if ($key == '_target_png') {
			$is_png = true;
		}

		$resize_dir .= $map[$key];

		if (empty($cmd)) {
			$cmd .= $this->conf['convert.'.$key];
		}
		else {
			$cmd .= ' '.$this->conf['convert.'.$key];
		}
	}

	if (!empty($this->conf['save_as'])) {
		$save_as = $this->conf['save_as'];
		if (substr($save_as, -1) == '/') {
			$save_as .= str_replace($this->conf['picture_dir'].'/', '', $p['source']);
		}

		$target_dir = dirname($save_as);
		$suffix = File::suffix($p['source'], true);
		$this->conf['target'] = empty($suffix) ? $save_as.$suffix : $save_as;
		$p['target'] = $this->conf['target'];
	}
	else {
		$target_dir = dirname($this->conf['target']).'/'.$resize_dir;
		$this->conf['target'] = $target_dir.'/'.basename($this->conf['target']);
		$p['target'] = $this->conf['target'];

		if ($is_png) {
			$p['target_png'] = $target_dir.'/'.File::basename($p['target'], true).'.png';
			$this->conf['target'] = $p['target_png'];
			$p['target'] = $p['target_png'];
		}
	}

  Dir::create($target_dir, 0777, true);

	// \rkphplib\lib\log_debug([ "TPicture.runConvertCmd:413> <1>", $p ]);
	if (File::exists($this->conf['target']) && !empty($this->conf['use_cache'])) {
		if ($this->conf['use_cache'] == 'check_time') {
			if (File::lastModified($this->conf['source']) <= File::lastModified($this->conf['target'])) {
				// \rkphplib\lib\log_debug("TPicture.runConvertCmd:417> check_time - use cache: ".$this->conf['target']);
				return $this->conf['target'];
			}
		}
		else {
			// \rkphplib\lib\log_debug("TPicture.runConvertCmd:422> use cache: ".$this->conf['target']);
			return $this->conf['target'];
		}
	}

	// \rkphplib\lib\log_debug("TPicture.runConvertCmd:427> $cmd");
	execute($cmd.' 2>/dev/null', $p); 

	if (!FSEntry::isFile($p['target'], false)) {
		throw new Exception('convert '.$p['source'].' to '.$p['target'].' failed', "cmd: $cmd\np: ".print_r($p, true));
	}

 	FSEntry::chmod($this->conf['target'], 0666);
}


/**
 * Apply multiple resize operations at once (conf.resize_list).
 * 
 * @param string $resize_clist
 */
public function resize_list($resize_clist) {
	$list = explode(',', $resize_clist);
	$resize_cmd = [];

	foreach ($list as $resize) {
		$target = escapeshellarg('ToDo');
		$resize = escapeshellarg(trim($resize));
		$cmd = "\\( +clone -resize '$resize' -write '$target' +delete \\)";
		array_push($resize_cmd, $cmd);
	}

	$source = escapeshellarg($this->conf['source']);
	execute("convert '$source' ".join(' ', $resize_cmd));
}


/**
 * Resize conf.source to conf.target according to conf.resize. Use trailing ^ for center mode.
 * Use trailing > to avoid unecessary resize.
 *
 * @see http://www.imagemagick.org/script/convert.php
 * @see http://www.imagemagick.org/script/command-line-processing.php#geometry
 * @return string (=conf.target)
 */
public function resize() {
	$resize = $this->conf['resize'];

	$exec_param = [ 'resize' => $resize, 'source' => $this->conf['source'], 'target' => $this->conf['target' ] ];

	$exec_param['resize_dir'] = str_replace([ '>', '<', '!', '^' ], [ 'g', 'l', 'x', 'z' ], $resize);

	if (preg_match('/^([0-9]+)x([0-9]+).*$/', $resize, $match)) {
		$exec_param['WxH'] = $match[1].'x'.$match[2];
	}

	if (empty($this->conf['convert']) || substr($resize, -1) == '^') {
		$exec_param['cmd'] = (substr($resize, -1) == '^') ? 'center' : 'resize';
	}
	else if ($this->conf['convert'] == 'cover') {
		$exec_param = $this->convertCover($exec_param);
	}
	else if (substr($this->conf['convert'], 0, 8) == 'convert.') {
		$exec_param['cmd'] = substr($this->conf['convert'], 8);
	}
	else if (in_array($this->conf['convert'], [ 'resize', 'box', 'center', 'box_png' ])) {
		$exec_param['cmd'] = $this->conf['convert'];
	}
	else {
		throw new Exception('ToDo: run custom convert command', $this->conf['convert']);
	}

	$this->runConvertCmd($exec_param);

	return $this->conf['target'];
}


}
