<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';
require_once __DIR__.'/Dir.class.php';


/**
 * GDLib wrapper
 *
 * @author Roland Kujundzic <roland@inkoeln.com>
 * @copyright 2021 Roland Kujundzic
 */
class GDLib {

private $_conf = array();
private $_info = array();
private $_im;


/**
 * Set configuration. Parameter are: 
 * Save directory is created. Either save_as or save_in must be set.
 * If open is set load image. If open and width or height is set resize image.
 * If only width + height is set create empty image.
 * Either open or width + height are required.
 * 
 * 	- save_in (no caching if set) use md5 hash as filename
 * 	- save_as
 * 	- format jpg|gif|png (autodetected from save_as if empty)
 * 	- jpeg_quality (default is 95)
 * 	- font.color (default '#000000')
 *	- font.size (10) 
 *	- font.ttf ('data/font/arial.ttf')
 *	- open image file path
 *	- width
 *	- height
 *	- bgcolor
 *
 * @param hash $p
 */
public function doInit($p) {

  if (!empty($p['save_in'])) {
    // cache is disabled ...
    Dir::create($p['save_in']);
    $p['cache'] = '';
  }
  else if (!empty($p['save_as'])) {
    // cache=yes is default
    if ((empty($p['cache']) || $p['cache'] != 'no') && File::exists($p['save_as'])) {
      $p['cache'] = $p['save_as'];
    }
    else {
      Dir::create(dirname($p['save_as']));
      $p['cache'] = '';
    }

    if (empty($p['format'])) {
      $p['format'] = strtolower(substr($p['save_as'], -3));
    }
  }
  else {
    lib_abort("either parameter save_in or save_as must be set");
  }

  if (empty($p['format']) && !empty($p['save_as'])) {
    $p['format'] = strtolower(substr($p['save_as'], -3));
  }

  if (empty($p['format']) || !in_array($p['format'], array('jpg', 'gif', 'png'))) {
    lib_abort("invalid format [{$p['format']}] use jpg, png or gif");
  }

  $p['md5'] = '';
  $p['jpeg_quality'] = empty($p['jpeg_quality']) ? 95 : $p['jpeg_quality'];

  $this->_conf = $p;

  $default = array('font.color' => '#000000',
    'font.size' => 10, 
    'font.ttf' => 'data/font/arial.ttf');

  foreach ($default as $key => $value) {
    if (!isset($this->_conf[$key])) {
      $this->_conf[$key] = $value;
    }
  }

  if ($p['cache']) {
    return;
  }

  if (!empty($p['open'])) {

    if (empty($p['width']) && empty($p['height'])) {
      $this->_im = $this->_load_img($p['open']);
    }
    else {
      $p['resize_mode'] = empty($p['resize_mode']) ? '' : $p['resize_mode'];
      $p['width'] = empty($p['width']) ? 0 : $p['width'];
      $p['height'] = empty($p['height']) ? 0 : $p['height'];

      $this->resize($p['width'], $p['height'], $p['open'], '', $p['resize_mode']);
    }

    $this->_conf['width'] = $this->_info['width'];
    $this->_conf['height'] = $this->_info['height'];
  }
  else if (!empty($p['width']) && !empty($p['height'])) {
    $this->_im = $this->_new_img($p['width'], $p['height'], null, true);
  }
  else {
    lib_abort("either parameter width+height or open is required");
  }
}


//-----------------------------------------------------------------------------
public function doPrintln($p) {

  if (!isset($p['text']) || strlen($p['text']) == 0) {
    return;
  }

  if (empty($p['font.size'])) {
    $p['font.size'] = $this->_conf['font.size'];
  }

  if (empty($p['font.ttf'])) {
    $p['font.ttf'] = $this->_conf['font.ttf'];
  }

  $pl = $p;

  $lines = preg_split("/\r?\n/", $p['text']);
  
  $p['font.height'] = $this->fontHeight($p['font.ttf'], $p['font.size']);

  if (empty($p['line_height'])) {
    $p['line_height'] = round(1.4 * $p['font.size'], 2);
  }
  else if (substr($p['line_height'], -1) == '%') {
    $p['line_height'] = round(intval(substr($p['line_height'], 0, -1)) / 100 * $p['font.size'], 2);
  }

  if (!empty($p['text_box'])) {
    // x,y = upper left corner of textbox
    list($tbx, $tby) = explode('x', $p['text_box']);
    $text_height = $p['font.size'] + (count($lines) - 1) * $p['line_height'];

    if ($text_height > $tby) {
      lib_abort("text height $text_height > $tby (= text_box height)");
    }

    $pl['text_box'] = $tbx.'x'.($p['font.size'] * 2);

    if (count($lines) > 1 && !empty($p['font.minsize'])) {
      
      foreach ($lines as $line) {
        if (strlen($line) > 0) {
          $pl['text'] = $line;
          $pl['font.size'] = min($pl['font.size'], $this->autoresize($pl));
        }
      }

      $p['font.size'] = $pl['font.size'];
      $pl['font.minsize'] = '';

      if (empty($p['line_height'])) {
        $p['line_height'] = round(1.4 * $p['font.size'], 2);
      }
      else if (substr($p['line_height'], -1) == '%') {
        $p['line_height'] = round(intval(substr($p['line_height'], 0, -1)) / 100 * $p['font.size'], 2);
      }

      $text_height = $p['font.size'] + (count($lines) - 1) * $p['line_height'];
    }
    
    if (empty($p['valign']) || $p['valign'] == 'top') {
      $pl['y'] -= $p['font.size'];

      foreach ($lines as $line) {
        if (strlen($line) > 0) {
          $pl['text'] = $line;
          $this->doPrint($pl);
        }

        $pl['y'] -= $p['line_height'];
      }
    }
    else if ($p['valign'] == 'center') {
      $pl['y'] -= $p['font.size'] + round(($tby - $text_height) / 2, 0);
      
      foreach ($lines as $line) {
        if (strlen($line) > 0) {
          $pl['text'] = $line;
          $this->doPrint($pl);
        }

        $pl['y'] -= $p['line_height'];
      }
    }
    else if ($p['valign'] == 'bottom') {
      $pl['y'] -= $tby;

      for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (strlen($lines[$i]) > 0) {
          $pl['text'] = $lines[$i];
          $this->doPrint($pl);
        }

        $pl['y'] += $p['line_height'];
      }
    }
  }
  else {
    // x,y = lower left corner of first line
    foreach ($lines as $line) {
      if (strlen($line) > 0) {
        $pl['text'] = $line;
        $this->doPrint($pl);
      }

      $pl['y'] -= $p['line_height'];
    }
  }
}


//-----------------------------------------------------------------------------
public function autoresize($p) {

  $fsize = empty($p['font.size']) ? $this->_conf['font.size'] : $p['font.size'];
  $fname = empty($p['font.ttf']) ? $this->_conf['font.ttf'] : $p['font.ttf'];

  if (empty($p['font.minsize'])) {
    return $fsize;
  }

  // autoresize if text doesn't fit
  $text = $this->_fix_text($p['text']);
  $fsize += 0.5;

  if (empty($p['text_box'])) {
    if (!empty($p['align']) && $p['align'] != 'left') {
      lib_abort("define text_box=WxH");
    }

    $width = $this->_conf['width'] - $p['x'];
    $height = $this->_conf['height'] - $p['y'];
  }
  else {
    list($width, $height) = explode('x', $p['text_box']);
  }

  do {
    $fsize -= 0.5;

    $box = imagettfbbox($fsize, 0, $fname, $text);

    $bwidth = $box[2] - $box[0];
    $bheight = $box[1] - $box[7];
  } while ($fsize >= $p['font.minsize'] && ($bwidth > $width || $bheight > $height));

  return $fsize;
}


/**
 * Print text into picture. Parameter:
 * 
 * - text: text to print (if empty do nothing) use §Fontname:§ for font change.
 * - font.*: overwrite default font. 
 * - x: x-position (default = 5)
 * - y: y-position (default = 5)
 * 
 * @param hash $p
 */
public function doPrint($p) {

  if (!isset($p['text']) || strlen($p['text']) == 0) {
    return;
  }

  if (($pos1 = strpos($p['text'], '§')) !== false && 
  		($pos2 = strpos($p['text'], ':§', $pos1 + 1)) !== false) { 
		$use_font = substr($p['text'], $pos1 + 1, $pos2 - $pos1 - 1);
		$p['text'] = substr($p['text'], $pos2 + 2);
		$p['font.ttf'] = $p['font.ttf.'.$use_font]; 
  }
  		
  $this->_conf['md5'] .= md5($p['text']);

  $fcolor = empty($p['font.color']) ? $this->_conf['font.color'] : $p['font.color'];
  $fsize = empty($p['font.size']) ? $this->_conf['font.size'] : $p['font.size'];
  $fname = empty($p['font.ttf']) ? $this->_conf['font.ttf'] : $p['font.ttf'];

  list($r, $g, $b) = $this->_rgb($fcolor);
  $color = imagecolorallocate($this->_im, $r, $g, $b);

  $x = isset($p['x']) ? $p['x'] : 5;
  $y = isset($p['y']) ? $p['y'] : 5;
  $text = $this->_fix_text($p['text']);

  if (empty($p['text_box'])) {
    if (!empty($p['align']) && $p['align'] != 'left') {
      lib_abort("define text_box=WxH");
    }

    $width = $this->_conf['width'] - $p['x'];
    $height = $this->_conf['height'] - $p['y'];
  }
  else {
    list($width, $height) = explode('x', $p['text_box']);
  }

  if (!empty($p['font.minsize'])) {
    // autoresize if text doesn't fit
    $fsize += 0.5;

    do {
      $fsize -= 0.5;

      $box = imagettfbbox($fsize, 0, $fname, $text);
      $bwidth = $box[2] - $box[0];
      $bheight = $box[1] - $box[7];
    } while ($fsize >= $p['font.minsize'] && ($bwidth > $width || $bheight > $height));
  }

  if (!empty($p['align']) && $p['align'] != 'left') {
    $box = imagettfbbox($fsize, 0, $fname, $text);
    $txt_width = $box[2] - $box[0]; 

    if ($p['align'] == 'right') {
      $x -= $txt_width;
    }
    else if ($p['align'] == 'center') {
      $x -= $txt_width / 2;
    }
  }
 
  $box = imagettftext($this->_im, $fsize, 0, $x, $this->_conf['height'] - $y, $color, $fname, $text);
  // ??? $box2 = imageftbbox($fsize, 0, $fname, $text);
  
  // box width (= $box[2] - $box[0]) is not correct !!! Real width is smaller 
  if ($box[2] - $box[0] - 1 > $width) {
    lib_abort("[$text] width exceeded by ".($box[2] - $box[0] - 1 - $width)."px", print_r($p, true));
  }

  // box height (= $box[1] - $box[7]) too is not correct !!! Real height is smaller 
  if ($fsize > $height) {
    lib_abort("[$text] height exceeded by ".($fsize - $height)."px", print_r($p, true)); 
  }
}


//-----------------------------------------------------------------------------
public function doSave() {

  if ($this->_conf['cache']) {
    return $this->_conf['cache'];
  }

  if (!empty($this->_conf['save_as'])) {
    $save_as = $this->_conf['save_as'];
  }
  else if (!empty($this->_conf['save_in'])) {
    $save_as = $this->_conf['save_in'].'/'.md5($this->_conf['md5']).'.'.$this->_conf['format'];
  }

  if ($this->_conf['format'] == 'gif') {
    imagegif($this->_im, $save_as);
  }
  else if ($this->_conf['format'] == 'png') {
    imagepng($this->_im, $save_as);
  }
  else if ($this->_conf['format'] == 'jpg') {
    imagejpeg($this->_im, $save_as, $this->_conf['jpeg_quality']);
  }

  imagedestroy($this->_im);

  File::chmod($save_as);

  return $save_as;
}


/**
 * Return image info. Parameter:
 * 
 *  - width:
 *  - height:
 *  
 * @param string $key
 * @return string
 */
public function getInfo($key) {
  if (!isset($this->_info[$key])) {
    lib_abort("no such info [$key]");
  }

  $res = $this->_info[$key];
  return $res;
}


/**
 * Load jpg|png|gif image. 
 * 
 * @param string $file
 * @return image_handle GDLib handle
 */
private function _load_img($file) {
	
  $gis = getimagesize($file);
 
  if (!is_array($gis) || $gis[0] < 1 || $gis[1] < 1) {
    return $this->_error_img(basename($file), $file);
  }

  $type = $gis[2];
  $alpha = false;
  $im = null;

  switch($type) {
    case 1:
      $im = imagecreatefromgif($file);
      $alpha = true;
      break;
    case 2:
      $im = imagecreatefromjpeg($file);
      break;
    case 3:
      $im = imagecreatefrompng($file); 
      $alpha = true;
      break;
    default:
      $im = imagecreatefromjpeg($file);
  }

  if (!$im) {
    $im = $this->_error_img(basename($file), $file);
  }
  else if ($alpha) {
    // preserve transparency ...
    imagealphablending($im, true);
    imagesavealpha($im, true);
  }

  $this->_info['width'] = $gis[0];
  $this->_info['height'] = $gis[1];

  return $im;
}


//-----------------------------------------------------------------------------
private function _error_img($error_msg, $log_msg = '') {

  lib_warn($log_msg);

  $w = 400;
  $h = 20;
  
  $im = ImageCreate ($w, $h);
  $bgc = ImageColorAllocate ($im, 255, 255, 255);
  $tc = ImageColorAllocate ($im, 255, 0, 0);
  ImageFilledRectangle ($im, 0, 0, $w, $h, $bgc);
  ImageString($im, 1, 5, 5, 'ERROR: '.$error_msg, $tc);

  return $im;
}


/**
 * Save image to file. If file is empty print out. 
 * 
 * @param image_handle $im
 * @param string $file
 */
private function _save_img($im, $file) {

  $res = false;

  $suffix = strtolower(substr($file, -3));

  if ($suffix == 'jpg' || $suffix == 'peg') {
    $res = imagejpeg($im, $file, 100);
  }
  else if ($suffix == 'gif') {
    $res = imagegif($im, $file);
  }
  else if ($suffix == 'png') {
  	$res = imagepng($im, $file);
  }
  else {
    lib_abort("invalid output file [$file] use [.gif|.png|.jpg|.jpeg]");
  }

  if (!$res) {
    lib_abort("save [$file] failed");
  }

  imagedestroy($im);
}


/**
 * Create new image. If im_old isset copy existing image. 
 * 
 * @param int $w image width
 * @param int $h image height
 * @param image_handle $im_old
 * @param bool $set_bg
 */
private function _new_img($w, $h, $im_old = null, $set_bg = false) {

  $im = imagecreatetruecolor($w, $h);

  if (!$im) {
    lib_abort("Couldn't create image", "width=[$w] height=[$h]");
  }

  if ($im_old) {
    if ($set_bg) {

      $old_w = imagesx($im_old);
      $old_h = imagesy($im_old);

      if (!empty($this->_conf['bgcolor'])) {
        list($r, $g, $b) = $this->_rgb($this->_conf['bgcolor']);
      }
      else {
        list ($r, $g, $b) = imagecolorsforindex($im, imagecolorat($im, 0, 0));
      }

      $color = imagecolorallocate($im, $r, $g, $b);
      imagefilledrectangle($im, 0, 0, $w, $h, $color);
    }
    else {
      // keep transparency from old image ...
      $transparent = imagecolortransparent($im_old);

      if  ($transparent >= 0 && $transparent < imagecolorstotal($im_old)) {
        $tc = imagecolorsforindex($im_old, $transparent);
        $new_tc = imagecolorallocate($im, $tc['red'], $tc['green'], $tc['blue']);
        imagefill($im, 0, 0, $new_tc);
        imagecolortransparent($im, $new_tc);
      }
    }
  }
  else if ($set_bg && !empty($this->_conf['bgcolor'])) {
    list($r, $g, $b) = $this->_rgb($this->_conf['bgcolor']);
    $color = imagecolorallocate($im, $r, $g, $b);
    imagefilledrectangle($im, 0, 0, $w, $h, $color);
  }

  return $im;
}


/**
 * Extract $width x $height part of image at top,left = $x,$y.
 * 
 * @param int $x
 * @param int $y
 * @param int $width
 * @param int $height
 * @param string $input if empty use current image
 * @param string $output if empty overwrite current image with croped image 
 */
public function crop($x, $y, $width, $height, $input = '', $output = '') {

  $im = (empty($input) && $this->_im) ? $this->_im : $this->_load_img($input);
  
  $img_w = imagesx($im);
  $img_h = imagesy($im);
	
  // fix crop parameter
  if ($x < 0) {
  	$x = 0;
  }

  if ($y < 0) {
  	$y = 0;
  }

  if ($width > $img_w) {
  	$width = $img_w;
  }
  
  if ($height > $img_h) {
  	$height = $img_h;
  }
  
	$crop_im = $this->_new_img($width, $height);
	imagecopy($crop_im, $im, 0, 0, $x, $y, $width, $height);

  if (empty($output)) {
    $this->_im = $crop_im;
  }
  else {
    $this->_save_img($crop_im, $output);
  }

  imagedestroy($im);
}


//-----------------------------------------------------------------------------
/**
 * Resize image. 
 * 
 * @param int $max_w
 * @param int $max_h
 * @param string $input if empty use current image
 * @param string $output if empty overwrite current image with croped image
 * @param string $mode empty or center|box
 */
public function resize($max_w, $max_h, $input = '', $output = '', $mode = '') {

  if ($max_w < 1 && $max_h < 1) {
    lib_abort("invalid resize [$max_w] x [$max_h]");
  }

  $im = (empty($input) && $this->_im) ? $this->_im : $this->_load_img($input);
  $w = imagesx($im);
  $h = imagesy($im);

  if ($max_w < 1) {
    $max_w = $w;
  }

  if ($max_h < 1) {
    $max_h = $h;
  }

  if ($mode == 'center') {
    $new_w = $max_w;
    $new_h = $max_h;

    if ($w > 0 && $h > 0 && $new_w > 0 && $new_h > 0) {
      $f = min($w/$new_w, $h/$new_h);
      $w_max = intval($new_w * $f);
      $h_max = intval($new_h * $f);
      $x = intval(($w - $w_max) / 2);
      $y = intval(($h - $h_max) / 2);

      $w = $w_max;
      $h = $h_max;
    }
    else {
      lib_abort("invalid picture size $input", "resize $w x $h to $new_w x $new_h");
    }
  }
  else {
    // apply same scaling factor to width and height
    $sx = $max_w / $w;
    $sy = $max_h / $h;
    $sf = 0;

    if ($max_w > 0 && $max_h > 0) {
      $sf = ($sx < $sy) ? $sx : $sy;
    }
    else if ($max_w > 0) {
      $sf = $sx;
    }
    else if ($max_h > 0) {
      $sf = $sy;
    }

    $x = 0;
    $y = 0;

    $new_w = $sf * $w;
    $new_h = $sf * $h;
  }

  $this->_info['width'] = $new_w;
  $this->_info['height'] = $new_h;

  $im_n = $this->_new_img($new_w, $new_h, $im);
  imagecopyresampled($im_n, $im, 0, 0, $x, $y, $new_w, $new_h, $w, $h);

  if ($mode == 'box') {
    $im_bg = $this->_new_img($max_w, $max_h, $im_n, true);

    $x = floor(($max_w - $new_w) / 2);
    $y = floor(($max_h - $new_h) / 2);

    imagecopymerge($im_bg, $im_n, $x, $y, 0, 0, $max_w, $max_h, 100);
    imagedestroy($im_n);

    $im_n = $im_bg;
  }

  if (empty($output)) {
    $this->_im = $im_n;
  }
  else {
    $this->_save_img($im_n, $output);
  }

  imagedestroy($im);
}


//-----------------------------------------------------------------------------
private function _rgb($rgb) {

  if (!preg_match('/#[0-9a-fA-F]{6}/', $rgb)) {
    lib_abort("invalid RGB value [$rgb]");
  }

  $res = sscanf($rgb, '#%2x%2x%2x');
  return $res;
}


//-----------------------------------------------------------------------------
private function _merge($p) {

  $required = array('width', 'height');

  $p['opacity'] = empty($p['opacity']) ? 100 : intval($p['opacity']);

  $im_bg = $this->_load_img($bg_img);
  $im_fg = $this->_load_img($fg_img);

  $fg_w = imagesx($im_fg);
  $fg_h = imagesy($im_fg);
  $bg_w = imagesx($im_bg);
  $bg_h = imagesy($im_bg);

  if  ($fg_w > $bg_w || $fg_h > $bg_h) {
    if ($fg_w > $fg_h) {
      $w = $bg_w;
      $h = $bg_w * $fg_h / $fg_w;
    } 
    else {
      $h = $bg_h;
      $w = $bg_h * $fg_w / $fg_h;
    }
  }
  else {
    $w = $bg_w;
    $h = $bg_h;
  }

  $x = floor(($bg_w - $w) / 2);
  $y = floor(($bg_h - $h) / 2);

  $im = $this->_new_img($w, $h, $im_fg);
  imagecopyresampled($im, $im_fg, 0, 0, 0, 0, $w, $h, $fg_w, $fg_h);
  imagecopymerge($im_bg, $im, $x, $y, 0, 0, imagesx($im_bg), imagesy($im_bg), $fill);
  $this->_save_img($im_bg, $output);
  imagedestroy($im_fg);
}


//-----------------------------------------------------------------------------
private function _fix_text($txt) {
  $res = $txt;

  $res = str_replace('&euro;', '&#8364;', $res);

  return $res;
}


//-----------------------------------------------------------------------------
private function _show() {

  $mime = array('gif' => 'image/gif', 'png' => 'image/png', 'jpg' => 'image/jpeg');
  header('Content-type: '.$mime[$this->_conf['format']]);

  if ($this->_conf['cache']) {
    File::cat($this->_conf['cache']);
    exit;
  }
  else {

    switch ($this->_conf['format']) {
      case 'gif':
        imagegif($this->_im);
        break;
      case 'png':
        imagepng($this->_im);
        break;
      case 'jpg':
        imagejpeg($this->_im, '', $this->_conf['jpeg_quality']);
        break;
    }

    imagedestroy($this->_im);
  }
}


//-----------------------------------------------------------------------------
public function merge($bg_img, $fg_img, $output, $fill = 100) {

  $im_bg = $this->_load_img($bg_img);
  $im_fg = $this->_load_img($fg_img);

  $fg_w = imagesx($im_fg);
  $fg_h = imagesy($im_fg);
  $bg_w = imagesx($im_bg);
  $bg_h = imagesy($im_bg);

  if  ($fg_w > $bg_w || $fg_h > $bg_h) {
    if ($fg_w > $fg_h) {
      $w = $bg_w;
      $h = $bg_w * $fg_h / $fg_w;
    } 
    else {
      $h = $bg_h;
      $w = $bg_h * $fg_w / $fg_h;
    }
  }
  else {
    $w = $bg_w;
    $h = $bg_h;
  }

  $x = floor(($bg_w - $w) / 2);
  $y = floor(($bg_h - $h) / 2);

  $im = $this->_new_img($w, $h, $im_fg);
  imagecopyresampled($im, $im_fg, 0, 0, 0, 0, $w, $h, $fg_w, $fg_h);
  imagecopymerge($im_bg, $im, $x, $y, 0, 0, imagesx($im_bg), imagesy($im_bg), $fill);
  $this->_save_img($im_bg, $output);
  imagedestroy($im_fg);
}


//-----------------------------------------------------------------------------
public function fontHeight($fontname, $fontsize, $text = 'XHTjyg') {
  $box = imagettfbbox($fontsize, 0, $fontname, $text);
  return $box[1] - $box[7];
}


//-----------------------------------------------------------------------------
public function fontWidth($fontname, $fontsize, $text = 'X') {
  $box = imagettfbbox($fsize, 0, $fname, $text);
  return $box[2] - $box[0];
}

}

