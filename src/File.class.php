<?php

namespace rkphplib;

require_once(__DIR__.'/FSEntry.class.php');
require_once(__DIR__.'/lib/execute.php');


/**
 * File access wrapper.
 * 
 * All methods are static.
 * By default file locking is disabled (enable with self::$USE_FLOCK = true).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class File {

/** @var bool don't use file locking by default (BEWARE: locking will not work on NFS) */
public static $USE_FLOCK = false;

/** @var bool default file creation mode */
public static $DEFAULT_MODE = 0666;



/**
 * Search file from subdir _REQUEST[dir] up to document root.
 * 
 * Return found filepath. Throw exception if _REQUES[dir] is not relative or filename has ../ or \.
 * 
 * @param string $file
 * @param string $dir (default = '', if empty use _REQUEST[dir])
 * @return string
 */
public static function find($file, $dir = '') {

	if (empty($dir)) {
		$dir = empty($_REQUEST[SETTINGS_REQ_DIR]) ? '.' : './'.$_REQUEST[SETTINGS_REQ_DIR];

		if (mb_substr($dir, 0, 3) == './/') {
			throw new Exception('path is not relative', "dir=[$dir]");
		}
	}

	$path = $dir.'/'.$file;

	if (mb_strpos($path, '../') !== false) {
		throw new Exception('../ is forbidden in path', "dir=[$dir] file=[$file]");
	}

	if (mb_strpos($path, '\\') !== false) {
		throw new Exception('backslash is forbidden in path', "dir=[$dir] file=[$file]");
	}

	$res = '';

	while (!$res && mb_strlen($dir) > 0) {
		$path = $dir.'/'.$file;

		if (file_exists($path) && is_readable($path)) {
			$res = $path;
		}

		if (($pos = mb_strrpos($dir, '/')) > 0) {
			$dir = mb_substr($dir, 0, $pos);
		}
		else {
			$dir = '';
		}
	}

	if (mb_substr($res, 0, 2) == './') {
		$res = mb_substr($res, 2);
	}

	return $res;
}


/**
 * Return filecontent loaded from url.
 * 
 * @param string $url
 * @param boolean $required (default = true, abort if zero size)
 * @return string
 */
public static function fromURL($url, $required = true) {

	if (empty($url)) {
		throw new Exception('empty url');
	}

	$cu = curl_init();
	curl_setopt($cu, CURLOPT_URL, $url);
	curl_setopt($cu, CURLOPT_BINARYTRANSFER, true);
	curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($cu, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($cu, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($cu, CURLOPT_FOLLOWLOCATION, true);

	$res = curl_exec($cu);
	$status = curl_getinfo($cu);

	if ($status['http_code'] != 200) {
		throw new Exception('failed to retrieve file', "status=".$status['http_code']." url=$url");
	}

	curl_close($cu);

	if (trim($res) == '' && $required) {
		throw new Exception('empty file', $url);
	}

	return $res;
}


/**
 * Return content of file $file.
 *
 * Start reading at byte offset if offset is set (default = -1).
 * Use flock if self::USE_FLOCK is true.
 *
 * @param string $file
 * @param int $offset (default = -1, start reading at byte offset)
 * @return string
 */
public static function load($file, $offset = -1) {

	if (empty($file)) {
		throw new Exception("empty filename");
	}

	if ($file == 'STDIN') {
		$file = 'php://stdin';
	}

	if (self::$USE_FLOCK) {
		return self::_lload($file, $offset);
	}

	if (($data = file_get_contents($file, false, null, $offset)) === false) {
		throw new Exception("failed to load file", $file);
	}

	return $data;
}


/**
 * Return file content. 
 *
 * Apply file locking. If $file = STDIN return self::stdin().
 *
 * @param string $file
 * @param int $offset (default = -1, start reading at byte offset)
 * @return string
 */
private static function _lload($file, $offset = -1) {

	$fsize = self::size($file);
	$res = '';

	if ($fsize > 0) {
		$fh = self::_open_lock($file, LOCK_SH, 'rb');

		if ($offset > -1 && fseek($fh, $offset) == -1) {
			throw new Exception("fseek failed", "file=$file offset=$offset");
		}

		if (($res = fread($fh, $fsize)) === false) {
			throw new Exception("file read failed", $file);
		}

		if (flock($fh, LOCK_UN) === false || fclose($fh) === false) {
			throw new Exception("close file failed", $file);
		}
	}

	return $res;
}


/**
 * Resize source image and save as target. If wxh is empty you can convert from one image type to another. 
 * Abort if w or h is greater than 2 * original size. Requires convert from ImageMagic.
 *
 * @param string $wxh (e.g. 140x140 or 140x or x140)
 * @param string $source
 * @param string $target (if empty overwrite source)
 */
public static function resizeImage($wxh, $source, $target = '') {

	$info = File::imageInfo($source);
	$resize = '';

	if (!empty($wxh)) {
		list ($w, $h) = explode('x', $wxh);

		if ($w < 0 || $w > 2 * $info['width']) {
			throw new Exception('invalid resize width', "$w not in ]0, 2 * ".$info['width']."]");
		}

		if ($h < 0 || $h > 2 * $info['height']) {
			throw new Exception('invalid resize height', "$h not in ]0, 2 * ".$info['height']."]");
		}

		$resize = "-resize '$wxh'";
	}

	if (empty($target)) {
		$suffix = File::suffix($source, true);
		$base = File::basename($source, true);
		$temp = dirname($source).'/'.$base.'_'.$wxh.$suffix;

		if (File::exists($temp)) {
			throw new Exception('already resizing or resize failed', $temp);
		}

		\rkphplib\lib\execute("convert $resize '$wxh' '$source' '$temp'");
		File::move($temp, $source);
		$target = $source;
	}
	else {
		\rkphplib\lib\execute("convert $resize '$wxh' '$source' '$target'");
	}

	File::exists($target, true);
}


/**
 * Return image info hash. If image is not detected and abort=false, return
 * width=height=0 and suffix=mime=file=''.
 * 
 * @param string $file
 * @param boolean $abort
 * @return map (width, height, mime, suffix, file)
 */
public static function imageInfo($file, $abort = true) {

	FSEntry::isFile($file);
	$info = getimagesize($file);
	$res = array('width' => 0, 'height' => 0, 'mime' => '', 'suffix' => '', 'file' => '');

	if ($info === false || empty($info[0]) || empty($info[1]) || empty($info[2]) || empty($info['mime'])) {
		if ($abort) {
			throw new Exception('invalid image', $file.': '.print_r($info, true));
		}
		else {
			return $res;
		}
	}

	if ($info[0] < 1 || $info[0] > 40000) {
		throw new Exception('invalid image width', $file.': '.$info[0]);
	}

	if ($info[1] < 1 || $info[1] > 40000) {
		throw new Exception('invalid image heigth', $file.': '.$info[1]);
	}

	$res['file'] = $file;
	$res['width'] = $info[0];
	$res['height'] = $info[1];
	$res['mime'] = $info['mime'];

	$mime_suffix = array('image/png' => '.png', 'image/jpeg' => '.jpg', 'image/gif' => '.gif', 'image/tiff' => '.tif',
		'image/jp2' => '.jp2', 'image/jpx' => '.jpx', 'image/x-ms-bmp' => '.bmp', 'image/x-photoshop' => '.psd',
		'image/x-xbitmap' => '.xbm');
	
	$suffix_map = array(1 => '.gif', 2 => '.jpg', 3 => '.png', 4 => '.swf',
		5 => '.psd', 6 => '.bmp', 7 => '.tif', 8 => '.tif', 9 => '.jpc',
		10 => '.jp2', 11 => '.jpx', 12 => '.jb2', 13 => '.swc', 14 => '.iff',
		15 => '.bmp', 16 => '.xbm', 17 => '.ico');

	if (isset($mime_suffix[$info['mime']])) {
		$res['suffix'] = $mime_suffix[$info['mime']];
	}
	else if (isset($suffix_map[$info[2]])) {
		$res['suffix'] = $suffix_map[$info[2]];
	}
	else {
		$suffix = File::suffix($file, true);

		if ($suffix == '.jpeg') {
			$suffix = '.jpg';
		}
		else if ($suffix == '.tiff') {
			$suffix = '.tif';
		}

		$res['suffix'] = $suffix;
	}

	return $res;
}


/**
 * Open and lock file (or pipe if $file = STDOUT|STDIN).
 *
 * @param string file path
 * @param int lock mode (LOCK_EX or LOCK_SH)
 * @param string open mode (see File::open)
 * @return filehandle
 */
private static function _open_lock($file, $lock_mode, $open_mode) {

	$map = array('STDIN' => 'php://stdin', 'STDOUT' => 'php://stdout');
	if (!empty($map[$file])) {
		$file = $map[$file];
	}

	$fh = self::open($file, $open_mode);

	if ($lock_mode == LOCK_EX) {
		if (!flock($fh, LOCK_EX)) {
			throw new Exception('exclusive file lock failed', $file);
		}
	}
	else if ($lock_mode == LOCK_SH) {
		if (!flock($fh, LOCK_SH)) {
			throw new Exception('shared file lock failed', $file);
		}
	}

	return $fh;
}


/**
 * Return filesize in byte (or formattet via File::formatSize if $as_text is true).
 *
 * @param string $file
 * @param bool $as_text (default = false)
 * @return int
 */
public static function size($file, $as_text = false) {

	FSEntry::isFile($file);

	if (($res = filesize($file)) === false) {
		throw new Exception('filesize failed', $file);
	}

	if ($as_text) {
		$res = self::formatSize($res);
	}

	return $res;
}


/**
 * Return formated size (N MB, N KB or N Byte).
 *
 * @param int $bytes
 * @return string
 */
public static function formatSize($bytes) {
	$res = $bytes;

	if ($bytes > 1024 * 1024) {
		$res = round($bytes / (1024 * 1024), 2).' MB';
	}
	else if ($bytes > 1024) {
		$res = round($bytes / 1024, 2).' KB';
	}
	else {
		$res = $bytes.' Byte';
	}

	return $res;
}


/**
 * Check if file exists.
 *
 * @param string $file
 * @param bool $required (default = true) 
 * @return bool
 */
public static function exists($file, $required = false) {
  return FSEntry::isFile($file, $required);
}


/**
 * Return file md5 checksum.
 * 
 * @param string $file
 * @return string
 */
public static function md5($file) {
	FSEntry::isFile($file);
	return md5_file($file);
}


/**
 * True if file was modified.
 * 
 * @param string $file
 * @param string $md5_log
 * @return bool
 */
public static function hasChanged($file, $md5_log) {
  $md5 = File::md5($file);

	if (!FSEntry::isFile($md5_log, false)) {
		File::save($md5_log, $md5);
		return true;
	}

	$old_md5 = trim(File::load($md5_log));
	$res = ($old_md5 == $md5);

	if (!$res) {
		File::save($md5_log, $md5);
	}

	return !$res;
}


/**
 * Return true if file changes within $watch seconds.
 *
 * @param string $file
 * @param int $watch (default = 15, max = 300 seconds)
 */
public static function isChanging($file, $watch = 15) {
	$md5_old = File::md5($file);
	sleep(min($watch, 300));
	$md5_new = File::md5($file);
	return $md5_old != $md5_new;
}


/**
 * Change file permissions.
 *
 * @param string $file
 * @param octal $mode (self::$DEFAULT_MODE)
 */
public static function chmod($file, $mode = 0) {

	if (!$mode) {
		$mode = self::$DEFAULT_MODE;
	}

	FSEntry::isFile($file, true);
	FSEntry::chmod($file, $mode);
}


/**
 * Save $data to $file. 
 *
 * @param string $file
 * @param string $data
 * @param string $flag (default = 0, FILE_APPEND)
 */
public static function save($file, $data, $flag = 0) {

	if (empty($file)) {
		throw new Exception('empty filename');
	}

	if ($file == 'STDOUT') {
		$file = 'php://stdout';
	}

	if (self::$USE_FLOCK) {
		$flag = $flag & LOCK_EX;
	}

	if (($bytes = file_put_contents($file, $data, $flag)) === false) {
		throw new Exception('failed to save data to file', $file);
	}
}


/**
 * Save $data to $file and modify privileges to $mode.
 *
 * @param string $file
 * @param string $data
 * @param octal $mode (default = 0 = self::DEFAULT_MODE)
 */
public static function save_rw($file, $data, $mode = 0) {
	File::save($file, $data);

	if (!$mode) {
		$mode = self::$DEFAULT_MODE;
	}

	FSEntry::chmod($file, $mode);
}


/**
 * Delete file.
 *
 * @param string $file
 * @param bool $must_exist (default = true)
 */
public static function remove($file, $must_exist = true) {

	if (!FSEntry::isFile($file, $must_exist)) {
		return;
	}

	if (!unlink($file)) {
		throw new Exception('file removal failed', $file);
  }
}


/**
 * Append data to file.
 * 
 * @param string $file
 * @param string $data
 */
public static function append($file, $data) {
	self::save($file, $data, FILE_APPEND);
}


/**
 * Open File for reading. 
 *
 * Use mode = rb|ab|wb|ru|wu. Always use ru for reading and wu for writing UTF-8 text files.
 * 
 * @param string $file
 * @param string $mode
 * @return filehandle
 */
public static function open($file, $mode = 'rb') {

	$write_utf8_bom = false;
	$read_utf8_bom = false;
	$utf8_bom = chr(239).chr(187).chr(191); // byte order mark == b"\xEF\xBB\xBF"

	if ($mode == 'ru') {
		$read_utf8_bom = true;
		$mode = 'rb';
	}

	if ($mode == 'wu') {
		$write_utf8_bom = true;
		$mode = 'wb';
	}

	if ($mode != 'rb' && $mode != 'wb' && $mode != 'ab') {
		throw new Exception('invalid file open mode', 'mode=['.$mode.'] use rb|wb|ab');
	}

	if (!($fh = fopen($file, $mode))) {
		throw new Exception('open file failed', "file=[$file]");
	}

	if ($read_utf8_bom) {
		$bom = fread($fh, 3);
		if ($bom != $utf8_bom) {
			fseek($fh, 0);
		}
	}

	if ($write_utf8_bom) {
		if (!fwrite($fh, $utf8_bom)) {
			throw new Exception('could not write utf-8 bom', $file);
		}
	}

	return $fh;
}


/**
 * Load file content into array.
 *
 * @param string
 * @param flags (FILE_IGNORE_NEW_LINES, FILE_SKIP_EMPTY_LINES)
 */
public static function loadLines($file, $flags = 0) {
	$lines = array();

	if (File::size($file) > 0) {
		$lines = file($file, $flags);
	}

	return $lines;
}




/**
 * Write CSV line to file.
 *
 * @param array $data
 * @param char $delimiter (default = ',')
 * @param char $enclosure (default = '"')
 * @param char $escape (default = '\\')
 */
public static function writeCSV($fh, $data, $delimiter = ',', $enclosure = '"', $escape = '\\') {

	if (!$fh) {
		throw new Exception('invalid file handle');
	}

	if (fputcsv($fh, $data, $delimiter, $enclosure, $escape) === false) {
		throw new Exception('could not write csv line', 'fh=['.$fh.'] data=['.mb_substr(join('|', $data), 0, 40).' ... ]');
	}
}


/**
 * Write to data file.
 *
 * @param filehandle $fh
 * @param string $data
 */
public static function write($fh, $data) {

	if (!$data) {
		return;
	}

	if (!$fh) {
		throw new Exception('invalid file handle');
	}

	if (fwrite($fh, $data) === false) {
		throw new Exception('could not write data', 'fh=['.$fh.'] data=['.mb_substr($data, 0, 40).' ... ]');
	}
}


/**
 * Read up to length bytes from file.
 *
 * @param filehandle $fh
 * @param int $length (default = 8192)
 * @return string|false
 */
public static function read($fh, $length = 8192) {

	if (!$fh) {
		throw new Exception('invalid file handle');
	}

	if ($length < 1) {
		throw new Exception('invalid byte length');
	}

	if (($res = fread($fh, $length)) === false) {
		throw new Exception('error reading from filehandle');
	}

	return $res;
}


/**
 * Return true if filehandle is at eof.
 * 
 * @param filehandle $fh
 * @return bool
 */
public static function end($fh) {
	return feof($fh);
}


/**
 * Read line from filehandle (until \n or maxlen is reached).
 *
 * @param filehandle $fh
 * @param int $maxlen (default = 8192)
 * @return string|false
 */
public static function readLine($fh, $maxlen = 8192) {
	$res = fgets($fh, $maxlen);

	if (mb_strlen($res) === $maxlen - 1 && !feof($fh)) {
		throw new Exception('line too long', $res);
	}

	return $res;
}


/**
 * Save serialized data in file.
 *
 * @param string $file
 * @param any $data
 */
public static function serialize($file, $data) {
	File::save($file, serialize($data));
}


/**
 * Return unserialized file content.
 * 
 * @param string $file
 * @return string 
 */
public static function unserialize($file) {
	return unserialize(File::load($file));
}


/**
 * Read CSV line from filehandle.
 *
 * @param filehandle $fh
 * @param char $delimiter (default = ',')
 * @param char $enclose (default = '"')
 * @param char $escape (default = '\\')
 * @return array|null
 */
public static function readCSV($fh, $delimiter = ',', $enclosure = '"', $escape = '\\') {

	if (!$fh) {
		throw new Exception('invalid file handle');
	}

	if (($res = fgetcsv($fh, 0, $delimiter, $enclosure, $escape)) === false) {
		if (feof($fh)) {
			return null;
		}
		else {
			throw new Exception('error reading csv from filehandle');
		}
	}

	if (is_array($res) && count($res) === 1 && is_null($res[0])) {
		$res = null;
	}

	return $res;
}


/**
 * Close file.
 *
 * @param filehandle &$fh
 */
public static function close(&$fh) {

	if (!$fh) {
		throw new Exception('invalid file handle');
	}

	if (fclose($fh) === false) {
		throw new Exception('close filehandle failed');
	}

	$fh = 0;
}


/**
 * Return file mime type.
 * You might need to enable fileinfo extension (e.g. extension=php_fileinfo.dll).
 *
 * @param string $file
 * @param bool $use_only_suffix (default = false)
 * @return string
 */
public static function mime($file, $use_only_suffix = false) {
	$res = '';

	if (!$use_only_suffix && file_exists($file) && !is_dir($file) && is_readable($file)) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$res = finfo_file($finfo, $file);
	}
	else {
		$pi = pathinfo($file);
		if (!empty($pi['extension'])) {
			$s = $pi['extension'];

			$suffix2mime = [ 'png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'tif' => 'image/tiff', 
				'psd' => 'image/x-photoshop', 'txt' => 'text/plain', 'xml' => 'application/xml', 
				'xsd' => 'application/xml' ];

			if (isset($suffix2mime[$s])) {
				$res = $suffix2mime[$s];
			}
		}
	}

	return $res;
}


/**
 * Return lowercase file name suffix (without dot - unless $keep_dot= true).
 * 
 * @param string $file
 * @param bool $keep_dot (default = false)
 * @return string
 */
public static function suffix($file, $keep_dot = false) {
	$res = '';

	if (($pos = mb_strrpos($file, '.')) !== false) {
		if ($keep_dot) {
			$res = mb_strtolower(mb_substr($file, $pos));
		}
		else {
			$res = mb_strtolower(mb_substr($file, $pos + 1));
		}

 		if (mb_strpos($res, '/') !== false) {
			// ignore invalid suffix
			$res = '';
		}
	}

	return $res;
}


/**
 * Return file basename. 
 *
 * If remove_suffix is true remove .xxxx.
 * If rsuffix is set remove everything after rsuffix rpos (apply after remove_suffix). 
 *
 * @param string $file
 * @param bool (default = false)
 * @param string (default = empty)
 * @return string
 */
public static function basename($file, $remove_suffix = false, $rsuffix = '') {

	$res = basename($file);

	if ($remove_suffix && ($pos = mb_strrpos($res, '.')) !== false) {
		$res = mb_substr($res, 0, $pos);
	}

	if ($rsuffix && ($pos = mb_strrpos($res, $rsuffix)) !== false) {
		$res = mb_substr($res, 0, $pos);
	}

	return $res;
}


/**
 * Transform filename array into hash.
 *
 * @see File::basename(path, remove_suffix, rsuffix)
 * @param array $list
 * @param bool remove_suffix (default = true)
 * @param string rsuffix (default = '_')
 * @return hash {base1: [file1, file2, ...], ... }
 */
public static function basename_collect($list, $remove_suffix = true, $rsuffix = '_') {

	$res = array();

	foreach ($list as $val) {
		$base = self::basename($val, $remove_suffix, $rsuffix);

		if (!isset($res[$base])) {
			$res[$base] = array();
		}

		array_push($res[$base], $val);
	}

	return $res;
}


/**
 * Copy file.
 *
 * @param string $source
 * @param string $target
 * @param string $mode (default = 0 = self::$DEFAULT_MODE)
 */
public static function copy($source, $target, $mode = 0) {

	if (!$mode) {
		$mode = self::$DEFAULT_MODE;
	}

	if (!copy($source, $target)) {
		throw new Exception("Filecopy failed", "$source -> $target");
	}

	FSEntry::chmod($target, $mode);
}


/**
 * Move file.
 *
 * @param string $source
 * @param string $target
 * @param bool $mode (default = 0 = self::$DEFAULT_MODE)
 */
public static function move($source, $target, $mode = 0666) {

	$rp_target = realpath($target);

	if ($rp_target && realpath($source) == $rp_target) {
		throw new Exception('same source and target', "mv [$source] to [$target]");
	}

	File::copy($source, $target, $mode);
	File::remove($source);
}


/**
 * Return last modified. 
 *
 * Return Y-m-d H:i:s instead of unix timestamp if $sql_ts is true.
 *
 * @param string $file
 * @param bool $sql_ts (default = false) 
 * @return int|string
 */
public static function lastModified($path, $sql_ts = false) {

	FSEntry::isFile($path);

	if (($res = filemtime($path)) === false) {
		throw new Exception('file last modified failed', $file);
	}

	if ($sql_ts) {
		$res = date('Y-m-d H:i:s', $res);
	}

	return $res;
}


/**
 * Send file as http download. Use disposition = 'inline' for in browser display.
 * Exit after send. If file does not exist send 404 not found.
 *
 * @param string $file
 * @param string|bool $disposition (default = attachment = true)
 * 
 */
public static function httpSend($file, $disposition = 'attachment') {
	
	if (!FSEntry::isFile($file, false)) {
		header("HTTP/1.0 404 Not Found");
		exit;
	}

	$mime_type = File::mime($file);
	$content_type = empty($mime_type) ? 'application/force-download' : $mime_type;

	// send header
	header('HTTP/1.1 200 OK');

	// IE is the shit ... needs cache control headers ...
	header('Pragma: public');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Cache-Control: private', false);

	$fsize = filesize($file);

	header('Date: '.date("D M j G:i:s T Y"));
	header('Last-Modified: '.date("D M j G:i:s T Y"));
	header('Content-Type: '.$content_type);
	header('Content-Length: '.$fsize);
	header('Content-Transfer-Encoding: binary');

	if ($disposition === true) {
		// keep compatibility to old version
		$disposition = 'attachment';
	}

	if ($disposition === 'attachment' || $disposition === 'inline') {
		$fname = basename($file);
		header('Content-Description: File Transfer');
		header('Content-Disposition: '.$disposition.'; filename="'.$fname.'"');
  	}

	if ($fsize  > 1048576 * 50) {
		// 50 MB+ ... flush the file ... otherwise the download dialog doesn't show up ...
 		$fp = fopen($file, 'rb');

		while (!feof($fp)) {
			print fread($fp, 65536);
 			flush();
 		}

		fclose($fp);
	}
	else {
		// send file content
		readfile($file);
	}

	exit;
}


}

