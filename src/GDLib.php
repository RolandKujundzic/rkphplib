<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Dir.class.php';

use \rkphplib\Exception;


/**
 * GDLib wrapper
 *
 * @author Roland Kujundzic <roland@inkoeln.com>
 * @copyright 2017-2021 Roland Kujundzic
 */
class GDLib {

// @var resource $im
private $im = null;

// @var array $env
private $env = [];


/**
 *
 */
public function __destruct() {
	$this->reset();
}


/**
 * Create new $w x $h pixel image with white background
 */
public function create(int $w, int $h, string $rgb_bg = '#ffffff') : void {
	$this->reset();

	if (!($this->im = imagecreatetruecolor($w, $h))) {
		throw new Exception("create $w * $h pixel image");
	}

	$this->env['width'] = $w;
	$this->env['height'] = $h;

	if ($rgb_bg) {
		$this->bgcolor($rgb_bg);
	}
}


/**
 * Set image background. Use #rrggbbaa for alpha aa = 0 (=opaque) ¿ 7f (=127=transparent)
 */
public function bgcolor(string $rgba) : void {
	list($r, $g, $b, $a) = self::rgba($rgba);

	if ($a > 0) {
		imagealphablending($this->im, false);
		imagesavealpha($this->im, true);
		$color = imagecolorallocatealpha($this->im, $r, $g, $b, $a);
	}
	else {
		$color = imagecolorallocate($this->im, $r, $g, $b);
	}

	imagefilledrectangle($this->im, 0, 0, $this->env['width'], $this->env['height'], $color);
	$this->env['bgcolor'] = $rgba;
}


/**
 * Return im|with|height|file value.
 * @return string|int|resource
 */
public function __get(string $key) {
	if ($key == 'im') {
		return $this->im;
	}
	else if (!isset($this->env[$key])) {
		throw new Exception('invalid name '.$key, 'try: '.join('|', array_keys($this->env)));
	}

	return $this->env[$key];
}


/**
 * Return true if transparent pixel exists
 */
public function hasAlpha() : bool {
	for ($i = 0; $i < $this->env['width']; $i++) {
		for ($j = 0; $j < $this->env['height']; $j++) {
			$rgba = imagecolorat($this->im, $i, $j);
			if (($rgba & 0x7F000000) >> 24) {
				return true;
			}
		}
	}
 
	return false;
}


/**
 * Save image. Save quality for *.jpg can be defined via
 * $jpeg_quality (default = -1 = 75). If $save_as is empty
 * use env.file.
 */
public function save(string $save_as = '', int $jpeg_quality = -1) : void {
	if ($save_as == '') {
		$save_as = $this->env['file'];
	}

	if (empty($save_as)) {
		throw new Exception('empty filename');
	}

	$suffix = File::suffix($save_as);

	if ($suffix == 'gif') {
		$res = imagegif($this->im, $save_as);
	}
	else if ($suffix == 'png') {
		$res = imagepng($this->im, $save_as);
	}
	else if ($suffix == 'jpg' || $suffix == 'jpeg') {
		$res = imagejpeg($this->im, $save_as, $jpeg_quality);
	}
	else {
		throw new Exception('invalid image filename', $save_as);
	}

  if (!$res) {
		throw new Exception('save image failed', $save_as);
	}
}


/**
 * Return [ r, g, b, a ] from e.g. #eeaabb and #0000007f for transparent.
 * You can Use 0,0,255 instead of #0000ff.
 */
public static function rgba(string $rgba) : array {
	if (preg_match('/^#[0-9a-fA-F]{6}$/', $rgba)) {
		$res = sscanf($rgba, '#%2x%2x%2x');
		$res[3] = 0;
	}
	else if (preg_match('/^#[0-9a-fA-F]{8}$/', $rgba)) {
		$res = sscanf($rgba, '#%2x%2x%2x%2x');
	}
	else if (strpos($rgba, ',') > 0) {
		$res = explode(',', $rgba);
		if (count($res) == 3) {
			$res[3] = 0;
		}
	}
	else {
		throw new Exception("invalid RGBA hex value '$rgb' use e.g. #eeaa00, #0000007f or '0,0,255'");
	}

	return $res;
}


/**
 * Load j[e]pg|png|gif image.
 */
public function load(string $file) : void {
	$gis = getimagesize($file);
 
	if (!is_array($gis) || $gis[0] < 1 || $gis[1] < 1) {
		throw new Exception('load image', $file);
	}

	$type = $gis[2];
	$alpha = false;

	$this->reset();

	switch($type) {
		case 1:
			$this->im = imagecreatefromgif($file);
			$alpha = true;
			break;
		case 2:
			$this->im = imagecreatefromjpeg($file);
			break;
		case 3:
			$this->im = imagecreatefrompng($file); 
			$alpha = true;
			break;
		default:
			throw new Exception('image detection failed');
	}

	if ($alpha) {
		// preserve transparency ...
		imagealphablending($this->im, true);
		imagesavealpha($this->im, true);
	}

	$this->env['file'] = $file;
	$this->env['width'] = $gis[0];
	$this->env['height'] = $gis[1];
}


/**
 * Add layer to image
 *
 * @hash $p …
 * opacity: 100
 * fit: [center|left|right]-[middle|top|bottom]|cover|contain|none, default = center-middle
 * x: 0, only if fill=none
 * y: 0, only if fill=none
 * @eol
 */
public function loadLayer(string $file, array $p = []) : void {
  $p = array_merge([
		'opacity' => 100,
		'fit'  => 'center-middle'
	], $p);

	$layer = new GDLib();
	$layer->load($file);

	$w = $this->env['width'];
	$h = $this->env['height'];
	$lw = $layer->width;
	$lh = $layer->height;

	if ($p['fit'] == 'center-middle') {
		if ($lw > $w || $lh > $h) {
			throw new Exception('ToDo …');
		}

		$x = floor(($w - $lw) / 2);
		$y = floor(($h - $lh) / 2);
	}
	else {
		throw new Exception('ToDo …', print_r($p, true));
	}

	if ($p['opacity'] < 100) {
		// ToDo: transparency broken !!!
		imagecopymerge($this->im, $layer->im, $x, $y, 0, 0, $lw, $lh, $p['opacity']);
	}
	else {
		imagecopy($this->im, $layer->im, $x, $y, 0, 0, $lw, $lh);
	}
}


/**
 *
 */
private function reset() : void {  
	$this->env['bgcolor'] = '';
	$this->env['file'] = '';
	$this->env['width'] = 0;
	$this->env['height'] = 0;

	if (!is_null($this->im)) {
		imagedestroy($this->im);
		$this->im = null;
	}
}

}

