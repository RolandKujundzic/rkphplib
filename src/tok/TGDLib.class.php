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
  $plugin['gdlib'] = 0;
  return $plugin;
}


/**
 * Print text. If $p[3] = fontname is empty use font_default
 * @tok {gdlib:print:80,30,Arial14}Hello{:gdlib}
 */
public function tok_print(string $p, string $txt) : void {
	if ($txt == '') {
		return;
	}

	$fname = empty($p[3]) ? 'default' : $p[3];
	$font = $this->getFont($fname);
	$x = intval($p[0]);
	$y = intval($p[1]);

	imagettftext($jpg_image, $font['size'], 0, $x, $y, $font['color'], $font['file'], $txt);
}


/**
 * Return conf.font_$name with allocated font.color
 */
private function getFont(string $name) : array {
	$font = $this->conf['font_'.$name];
	$font['color'] = imagecolorallocate($this->im, $font['rgb'][0], $font['rgb'][1], $font['rgb'][2]);
	return $font;
}


/**
 * Set font $name
 * @tok {gdlib:font:default}Arial.ttf, 14, #ff0000{:gdlib}
 */
public function tok_gdlib_font(string $name, array $p) : void {
	File::exists($this->conf['font_directory'].'/'.$p['file'], true);

	if (preg_match('/^#([a-z0-9]+)([a-z0-9]+)([a-z0-9]+)$/', $p['rgb'], $match)) {
		$p['rgb'] = [ hexdec($match[1]), hexdec($match[2]), hexdec($match[3]) ];
	}
	else {
		throw new Exception('invalid rgb color in font '.$name, print_r($p, true));
	}

	$fname = 'font_'.$name.'.';
	foreach ($p as $key => $value) {
		$this->conf[$fname.$key] = $value;
	}
}


/**
 * Initialize.
 *
 * @tok {gdlib:init}new=300,100,255,0,0{:gdlib}
 * @tok …
 * {gdlib:init}
 * font_directory= fonts|#|
 * save_as= |#|
 * load= background.jpg|#|
 * {:gdlib}
 * @eol
 */
public function tok_gdlib_init(array $p) : void {
	$default = [ 'font_directory' => 'fonts', 'save_as' => '' ];

	$this->conf = array_merge($default, $p);
	$this->setFont($this->conf);

	putenv('GDFONTPATH=' . realpath($this->conf['font_directory']));

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
	$p = array_merge([ '300', '150', '#ffffff' ], $p); 
	$this->im = imagecreatetruecolor($p[0], $p[1]);
	$c = $this->getColor($p[2]);
	$bgc = imagecolorallocate($this->im, $c[0], $c[1], $c[2]);
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

	$this->im = @$call[$suffix]($path);
}


}

