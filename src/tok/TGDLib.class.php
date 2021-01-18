<?php

namespace rkphplib\tok;

require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/../lib/split_str.php';

use rkphplib\Exception;
use function rkphplib\lib\split_str;


/**
 * GDLib Plugin
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020 Roland Kujundzic
 */
class TGDLib implements TokPlugin {

private $im = null;

private $conf = [];


/**
 * @plugin eval:math|logic
 */
public function getPlugins(Tokenizer $tok) : array {
  $plugin = [];
  $plugin['gdlib:print'] = TokPlugin::REQUIRE_PARAM | TokPlugin::PARAM_CSLIST;
  $plugin['gdlib:font'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['gdlib:init'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
  $plugin['gdlib:load'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
  $plugin['gdlib:new'] = TokPlugin::NO_PARAM | TokPlugin::CSLIST_BODY;
	$plubin['gdlib:create'] = TokPlugin::NO_PARAM | TokPlugin::KV_BODY;
  $plugin['gdlib'] = 0;
  return $plugin;
}


/**
 * @tok {gdlib:create}mime=image/jpeg{:gdlib}
 * @tok {gdlib:save_as}
 */
public function tok_gdlib_create(array $p) : void {
	$save_as = empty($p['save_as']) ? $this->conf['save_as'] : $p['save_as'];

	if (empty($save_as)) {
		$mime = empty($p['mime']) ? $this->conf['mime'] : $p['mime'];
		list ($type, $suffix) = explode('/', $mime);
		header('Content-type: '.$mime);
		$save_as = null;
	}
	else {
		list ($type, $suffix) = explode('/', File::mime($save_as)); 
	}

	${'image'.$suffix}($this->im);
	imagedestroy($this->im);
}


/**
 * Print text. If $p[3] = fontname is empty use font_default
 * @tok {gdlib:print:80,30,Arial14}Hello{:gdlib}
 */
public function tok_gdlib_print(string $p, string $txt) : void {
	if ($txt == '') {
		return;
	}

	$fname = empty($p[3]) ? 'default' : $p[3];
	$font = $this->getFont($fname);
	$x = intval($p[0]);
	$y = intval($p[1]);

	// \rkphplib\lib\log_debug("TGDLib.tok_gdlib_print:77> at $x|$y: '$text'");
	imagettftext($this->im, $font['size'], 0, $x, $y, $font['color'], $font['file'], $txt);
}


/**
 * Return conf.font_$name with allocated font.color
 */
private function getFont(string $name) : array {
	$fkey = 'font_'.$name.'.';
	$font = [];

	$font['file'] = $this->conf[$fkey.'file'];
	$font['size'] =	$this->conf[$fkey.'size'];

	list ($r, $g, $b) = $this->getColor($this->conf['font_'.$name.'.color']);
	$font['color'] = imagecolorallocate($this->im, $r, $g, $b);

	// \rkphplib\lib\log_debug([ "TGDLib.getFont:95> $name = <1>", $font ]);
	return $font;
}


/**
 * Return rgb color (decimal values)
 */
private function getColor(string $color) : array {
	if (preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)$/', $color, $match)) {
		$rgb = [ hexdec($match[1]), hexdec($match[2]), hexdec($match[3]) ];
	}
	else if (preg_match('/^#([a-z0-9]+)([a-z0-9]+)([a-z0-9]+)$/i', $color, $match)) {
		$rgb = [ hexdec($match[1]), hexdec($match[2]), hexdec($match[3]) ];
	}
	else {
		throw new Exception("invalid rgb color [$color]");
	}

	// \rkphplib\lib\log_debug([ "TGDLib.getColor:114> <1>", $rgb ]);
	return $rgb;
}


/**
 * Set font $name
 * @tok {gdlib:font:default}Arial.ttf, 14, #000000{:gdlib}
 */
public function tok_gdlib_font(string $name, array $p) : void {
	$fkey = empty($name) ? 'font_default_' : 'font_'.$name.'_';
	$default = [ '', $this->conf[$fkey.'size'], $this->conf[$fkey.'color'] ];
	$p = array_merge($default, $p);

	File::exists($this->conf['font_directory'].'/'.$p[0], true);

	$fsize = intval($p[1]);
	if ($fsize < 4 || $fsize > 100) {
		throw new Exception('invalid font size '.$fsize);
	}

	// \rkphplib\lib\log_debug([ "TGDLib.tok_gdlib_font:135> $name = <2>", $p ]);
	foreach ($p as $name => $value) {
		$this->conf[$fkey.$name] = $value;
	}
}


/**
 * Initialize.
 *
 * @tok {gdlib:init}new=300,100,255,0,0{:gdlib}
 * @tok …
 * {gdlib:init}
 * font_directory= fonts|#|
 * font_default.size= 14|#|
 * font_default.color= #000000|#|
 * save_as= |#|
 * load= background.jpg|#|
 * {:gdlib}
 * @eol
 */
public function tok_gdlib_init(array $p) : void {
	$default = [ 'font_directory' => 'fonts', 'save_as' => '',
		'font_default.size' => 14, 'font_default.color' => '#000000' ];

	$this->conf = array_merge($default, $p);

	$font_dir = realpath($this->conf['font_directory']);
	Dir::exists($font_dir, true);
	putenv('GDFONTPATH='.$font_dir);

	// \rkphplib\lib\log_debug([ "TGDLib.tok_gdlib_init:166> <1>", $this->conf ]);	
	if (!empty($p['load'])) {
		$this->tok_gdlib_load($p['load']);
	}
	else if (!empty($p['new'])) {
		$this->tok_gdlib_new(split_str(',', $p['new']));
	}
	else {
		throw new Exception('missing required parameter load or new', print_r($p, true));
	}
}


/**
 * @tok {gdlib:new} = 300,150,#ffffff
 * @tok {gdlib:new}200,50,255.255.255{:gdlib}
 */
private function tok_gdlib_new(array $p = []) : void {
	// \rkphplib\lib\log_debug([ "TGDLib.tok_gdlib_new:184> <1>", $p ]);	
	$p = array_merge([ '300', '150', '#ffffff' ], $p); 
	$this->im = imagecreatetruecolor($p[0], $p[1]);
	list ($r, $g, $b) = $this->getColor($p[2]);
	$bgc = imagecolorallocate($this->im, $r, $g, $b);
	imagefilledrectangle($im, 0, 0, $p[0], $p[1], $bgc);
}


/**
 * @tok {gdlib:load}background.jpg{:gdlib}
 */
private function tok_gdlib_load(string $path) : void {
	File::exists($path, true);
	$suffix = File::suffix($path);
	$call = [ 'jpg' => 'imagecreatefromjpeg', 'jpeg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefromjpeg' ];

	if (!isset($call[$suffix])) {
		throw new Exception('invalid suffix '.$suffix, $path);
	}

	// \rkphplib\lib\log_debug("TGDLib.tok_gdlib_load:205> {$call[$suffix]}($path)");	
	$this->im = @$call[$suffix]($path);
}


}

