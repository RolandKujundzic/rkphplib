<?php

namespace rkphplib;

require_once __DIR__.'/FSEntry.class.php';
require_once __DIR__.'/JSON.class.php';
require_once __DIR__.'/lib/execute.php';




/** @const FILE_DEFAULT_MODE = 0666 (UID < 1000) or 0644 (UID >= 1000) */
if (!defined('FILE_DEFAULT_MODE')) {
	if (posix_getuid() < 1000) {
		define('FILE_DEFAULT_MODE', 0666);
	}
	else {
		define('FILE_DEFAULT_MODE', 0644);
	}
}


/**
 * File access wrapper.
 * 
 * All methods are static. If uid >= 1000 use FILE_DEFAULT_MODE = 0666 otherwise
 * use 0644 (unless FILE_DEFAULT_MODE is already set).
 * By default file locking is disabled (enable with self::$USE_FLOCK = true).
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
class File {

/** @var bool $USE_FLOCK don't use file locking by default (BEWARE: locking will not work on NFS) */
public static $USE_FLOCK = false;



/**
 * Load table data. If uri is file|http|https|binary://. Example options:
 * 
 *  File::loadTable('csv:file://test.csv', [ ',', '"' ])
 *  File::loadTable('unserialize:file://test.ser')
 *  File::loadTable('json:https://download.here/test.json')
 *  File::loadTable('split:string://a|&|b|@|c|&|d', [ '|&|', '|@|' ]) 
 *  File::loadTable('split:string://a=1|&|b=2|@|c=3|&|d=4', [ '|&|', '|@|', '=' ]) 
 */
public static function loadTable($uri, $options = []) {

	if (!preg_match('#^(csv|unserialize|json|split)\:(file|string|https?)\://#', substr($uri, 0, 20), $match)) {
		throw new Exception('invalid uri', $uri);
	}

	$prefix = $match[1].':'.$match[2].'://';
	$uri = substr($uri, strlen($prefix));
	$type = $match[1];
	$data = '';

	if ($match[1] == 'csv') {
		if (!isset($options[0])) {
			$options[0] = ',';
		}

		if (!isset($options[1])) {
			$options[1] = '"';
		}

		$table = File::loadCSV($uri, $options[0], $options[1]);
	}
	else if ($match[2] == 'file') {
		$data = File::load($uri);
	}
	else if ($match[2] == 'string') {
		$data = $uri;
	}
	else {
		$data = File::fromURL($uri);
		$type = $match[1];
	}

	if ($type == 'unserialize') {
		$table = unserialize($data);
	}
	else if ($type == 'json') {
		$table = JSON::decode($data);
	}
	else if ($type == 'split') {
		require_once __DIR__.'/lib/split_table.php';

		if (empty($options[0])) {
			$options[0] = '|&|';
		}

		if (empty($options[1])) {
			$options[1] = '|@|';
		}

		if (!isset($options[2])) {
			$options[2] = '';
		}

		$table = \rkphplib\lib\split_table($data, $options[0], $options[1], $options[2]);
	}
	else if ($type != 'csv') {
		throw new Exception('invalid type', "uri=$uri type=$type");
	}

	return $table;
}


/**
 * Return file info. Autocreate/Update json file $file.nfo. 
 * Keys for comparison are size, md5, width, height other keys
 * are file, name (basename without suffix), mime and 
 * suffix (with leading dot).
 */
public static function nfo($file, $source = '') {
	File::exists($file, true);

	$nfo = $file.'.nfo';

	if (File::exists($nfo) && File::lastModified($file) <= File::lastModified($nfo)) {
		$json = JSON::decode(File::load($nfo));

		if (!empty($source) && (empty($json['source_file']) || $json['source_file'] != $source)) {
			$source_json = File::nfo($source);
			foreach ($source_json as $key => $value) {
				$json['source_'.$key] = $value;
			}
		}
	}
	else {
		$json = [];
		$json['file'] = $file;
		$json['name'] = File::basename($file, true);
		$json['size'] = File::size($file);

		if ($json['size'] == 0) {
			throw new Exception($file.' has zero size');
		}

		$json['mime'] = File::mime($file);
		$json['suffix'] = File::suffix($file, true);
		$json['lastModified'] = File::lastModified($file);
		$json['md5'] = File::md5($file);
	
		if (substr($json['mime'], 0, 6) == 'image/') {
			$ii = File::imageInfo($file);
			$json = array_merge($json, $ii);
		}

		if (!empty($source)) {
			$source_json = File::nfo($source);
			foreach ($source_json as $key => $value) {
				$json['source_'.$key] = $value;
			}
		}

		File::save_rw($nfo, JSON::encode($json));
	}

	return $json;
}


/**
 * Return true if source and target files are equal. Autocreate $target.nfo file.
 */
public static function equal($source, $target, $check_source_nfo = false) {
	File::exists($source, true);

	if ($source == $target || !File::exists($target)) {
		return false;
	}

	$ti = $check_source_nfo ? File::nfo($source) : File::nfo($target);

	if ($ti['size'] != File::size($source)) {
		return false;
	}

	if ($ti['width'] > 0) {
		$si = File::imageInfo($source);

		if ($si['width'] != $ti['width'] || $si['height'] != $ti['height']) {
			return false;
		}
	}

	if ($ti['md5'] != File::md5($source)) {
		return false;
	}

	return true;
}


/**
 * Load csv file. Ignore empty lines. If last parameter is callback function instead
 * of bool do $trim($row). If callback returns row push row to table.
 * Last parameter $trim is either boolean or callable.
 */
public static function loadCSV($file, $delimiter = ',', $quote = '"', $trim = true) {
	$fh = File::open($file, 'rb');
	$table = [];

	$callback = (!is_bool($trim) && is_callable($trim)) ? $trim : null;

	while (($row = File::readCSV($fh, $delimiter, $quote))) {
		if (count($row) == 0 || (count($row) == 1 && strlen(trim($row[0])) == 0)) {
			continue;
		}

		if (!is_null($callback)) {
			$res = $callback($row);

			if ($res) {
				array_push($table, $row);
			}

			continue;
		}

		for ($i = 0; $trim && $i < count($row); $i++) {
			$row[$i] = trim($row[$i]);
		}

		array_push($table, $row);
	}

	File::close($fh);
	return $table;
}


/**
 * Return filecontent loaded from url.
 * If required (default) abort if result has zero size.
 * Use pseudo header [return_html_body] = [1|true|y] = NOT_EMPTY
 * to return body content only.
 *
 * You might need to rawurlencode url. If you do not and download failed with
 * basename($url) != rawurlencode(basename($url)) the function will automatically 
 * retry with modified url.
 */
public static function fromURL($url, $required = true, $header = []) {

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

	$return_html_body = false;
	if (!empty($header['return_html_body'])) {
		$return_html_body = true;
		unset($header['return_html_body']);
	}

	if (count($header) > 0) {
		$header_lines = [];

		foreach ($header as $key => $value) {
			array_push($header_lines, $key.': '.$value);
		}

		curl_setopt($cu, CURLOPT_HTTPHEADER, $header_lines);
	}

	$res = curl_exec($cu);
	$info = curl_getinfo($cu);
	$status = intval($info['http_code']);

	if ($status < 200 || $status >= 300) {
		if (basename($url) != rawurlencode(basename($url)) && mb_strpos(basename($url), '%') === false) {
			return self::fromURL(dirname($url).'/'.rawurlencode(basename($url)), $required, $header);
		}
		else { 
			throw new Exception('failed to retrieve file', "status=$status url=$url");
		}
	}

	curl_close($cu);

	if ($return_html_body) {
		if (preg_match('/<body.*?>(.+?)<\/body>/si', $res, $match)) {
			$res = trim($match[1]);
		}
		else {
			throw new Exception('no html body found', $res);
		}
	}

	if (trim($res) == '' && $required) {
		throw new Exception('empty file', $url);
	}

	return $res;
}


/**
 * Return content of file $file.
 *
 * Start reading at byte offset if offset is set (default = 0).
 * Use flock if self::USE_FLOCK is true. Negative file offset
 * works only from php 7.2.x upwards. If offset > 0 seek byte position first,
 * (-n = seek n bytes from end).
 */
public static function load($file, $offset = 0) {

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
 * Return file content. Apply file locking. If $file = STDIN return self::stdin().
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
 * width=height=0 and suffix=mime=file=''. Return (width, height, mime, suffix, file).
 */
public static function imageInfo($file, $abort = true) {

	if (!FSEntry::isFile($file, $abort)) {
		return false;
	}

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
 * Return file handle (resource). Use either LOCK_EX or LOCK_SH as $lock_mode.
 * 
 * @return resource
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
 */
public static function formatSize($bytes) {

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
 */
public static function exists($file, $required = false) {
  return FSEntry::isFile($file, $required);
}


/**
 * Return file md5 checksum.
 */
public static function md5($file) {
	FSEntry::isFile($file);
	return md5_file($file);
}


/**
 * True if file was modified.
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
 * Return true if file changes within $watch seconds (default = 15 sec, max = 300 sec).
 */
public static function isChanging($file, $watch = 15) {
	$md5_old = File::md5($file);
	sleep(min($watch, 300));
	$md5_new = File::md5($file);
	return $md5_old != $md5_new;
}


/**
 * Change file permissions. Use octal value for file priviles $mode (0 = default = FILE_DEFAULT_MODE).
 */
public static function chmod($file, $mode = 0) {

	if (!$mode) {
		$mode = FILE_DEFAULT_MODE;
	}

	FSEntry::isFile($file, true);
	FSEntry::chmod($file, $mode);
}


/**
 * Save $data to $file. Apply chmod($file, FILE_DEFAULT_MODE).
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

	if (class_exists('\\rkphplib\\tok\\Tokenizer', false)) {
		\rkphplib\tok\Tokenizer::log([ 'label' => 'create file', 'message' => $file ], 'log.file_system');
	}

	FSEntry::chmod($file, FILE_DEFAULT_MODE);
}


/**
 * Save $data to $file and modify privileges to $mode.
 */
public static function save_rw($file, $data, $mode = 0) {
	File::save($file, $data);

	if (!$mode) {
		$mode = FILE_DEFAULT_MODE;
	}

	FSEntry::chmod($file, $mode);
}


/**
 * Delete file. Abort if file does not exists (unless must_exist = false).
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
 */
public static function append($file, $data) {
	self::save($file, $data, FILE_APPEND);
}


/**
 * Open File for reading. Return file handle (resource). Use mode = rb|ab|wb|ru|wu. 
 * Always use ru for reading and wu for writing UTF-8 text files.
 * 
 * @return resource
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
 * Load file content into array. Use $flags: FILE_IGNORE_NEW_LINES, FILE_SKIP_EMPTY_LINES.
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
 * @param resource $fh
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
 * @param resource $fh
 */
public static function write($fh, $data) {
	$data_len = strlen($data);

	if ($data_len == 0) {
		return;
	}

	if (!$fh) {
		throw new Exception('invalid file handle');
	}

	if (($byte = fwrite($fh, $data)) === false) {
		throw new Exception('could not write data', "fh=[$fh] byte=[$byte] len=[$data_len]");
	}

	// beware: writing to network stream or pipe might end before whole string is written
	if ($data_len > $byte) {
		// retry 10x
		$n = 0;

		while ($n < 10) {
			usleep(100000 + $n * 30000);

			$prev_len = $data_len;
			$data_len = $data_len - $byte;
			$byte = fwrite($fh, substr($data, $byte));
			$msg = ($data_len > 80) ? substr($data, 0, 40).' ... '.substr($data, -40) : $data;
			// \rkphplib\lib\log_debug("File::write:790> retry write: n=[$n] prev_len=[$prev_len] data_len=[$data_len] byte=[$byte] data=[$msg]");

			if ($byte === false) {
				throw new Exception('could not write data', "retry=[$n] fh=[$fh] byte=[$byte] len=[$data_len] prev_len=[$prev_len]");
			}
			else if ($byte == $data_len) {
				$n = 10;
			}

			$n++;
		}

		if ($data_len > $byte) {
			$msg = ($data_len > 80) ? substr($data, 0, 40).' ... '.substr($data, -40) : $data;
			throw new Exception('retry write data failed', "data.length=[$data_len] byte=[$byte] data=[$msg] n=[$n]");
		}
	}
}


/**
 * Read up to length bytes from file.
 *
 * @param resource $fh
 * @return mixed string|false
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
 * @param resource $fh
 */
public static function end($fh) {
	return feof($fh);
}


/**
 * Read line from filehandle (until \n or EOF is reached).
 *
 * @param resource $fh
 * @return mixed string|bool
 */
public static function readLine($fh) {

	if (!$fh) {
		throw new Exception('invalid file handle');
	}

	if (($res = fgets($fh)) === false && !feof($fh)) {
		throw new Exception('error reading line');
	}

	return $res;
}


/**
 * Save data as json in file. If json conversion fails, save
 * print_r(data, true) as $base.dump and serialized version
 * as $base.ser. Return true if successfull.
 */
public static function saveJSON($file, $data) {
  $json = json_encode($data, 448);

  if (($err_no = json_last_error()) || strlen($json) == 0) {
		$base = dirname($file).'/'.File::basename($file, true);				
		File::save($base.'.dump', print_r($data, true));
		File::save($base.'.ser', serialize($data));
		return false;
	}
	else {
		File::save($file, $json);
		return true;
	}
}


/**
 * Return file content converted from json.
 */
public static function loadJSON($file) {
	return JSON::decode(File::load($file));
}


/**
 * Save serialized data in file.
 */
public static function serialize($file, $data) {
	File::save($file, serialize($data));
}


/**
 * Return unserialized file content.
 */
public static function unserialize($file) {
	$res = unserialize(File::load($file));

	if ($res === false && File::size($file) > 100) {
		throw new Exception('error unserialize file '.$file);
	}

	return $res;
}


/**
 * Read CSV line from filehandle.
 *
 * @param resource $fh
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
 * @param resource &$fh
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

			$suffix2mime = [ 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 
				'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'psd' => 'image/x-photoshop', 'txt' => 'text/plain', 
				'csv' => 'text/csv', 'xml' => 'application/xml', 'xsd' => 'application/xml', 'pdf' => 'application/pdf' ];

			if (isset($suffix2mime[$s])) {
				$res = $suffix2mime[$s];
			}
		}
	}

	return $res;
}


/**
 * Return lowercase file name suffix (without dot - unless $keep_dot= true).
 */
public static function suffix($file, $keep_dot = false) {
	$res = '';

	$file = basename($file);

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
 * Transform filename array into hash. Default options:
 *
 * - remove_suffix: true
 * - remove_prefix: ''
 * - rsuffix: _
 */
public static function basename_collect($list, $options = []) {

	$default_options = [ 'remove_suffix' => true, 'remove_prefix' => '', 'rsuffix' => '_' ];

	foreach ($default_options as $key => $value) {
		if (!isset($options[$key])) {
			$options[$key] = $default_options[$key];
		}
	}

	$res = array();

	foreach ($list as $val) {
		$base = self::basename($val, $options['remove_suffix'], $options['rsuffix']);

		if (!isset($res[$base])) {
			$res[$base] = array();
		}

		array_push($res[$base], $val);
	}

	foreach ($res as $key => $img_list) {
		if ($options['remove_prefix']) {
			$rpl = mb_strlen($options['remove_prefix']);

			for ($i = 0; $i < count($img_list); $i++) {
				if (mb_substr($img_list[$i], 0, $rpl) == $options['remove_prefix']) {
					$img_list[$i] = mb_substr($img_list[$i], $rpl);
				}
			}
		}

		sort($img_list);
		$res[$key] = $img_list;
	}

	return $res;
}


/**
 * Copy file.
 *
 * @throws
 * @param string $source
 * @param string $target
 * @param int $mode default = 0 = source file mode
 */
public static function copy($source, $target, $mode = 0) {

	if (!$mode) {
		$stat = FSEntry::stat($source);
		$mode = $stat['perms']['octal'];
	}

	if (!copy($source, $target)) {
		throw new Exception("Filecopy failed", "$source to $target");
	}

	if (class_exists('\\rkphplib\\tok\\Tokenizer', false)) {
		\rkphplib\tok\Tokenizer::log([ 'label' => 'copy file', 'message' => $source.' '.$target ], 'log.file_system');
	}

	FSEntry::chmod($target, $mode);
}


/**
 * Move file. Use default $mode = 0 = source file mode.
 */
public static function move($source, $target, $mode = 0) {

	$rp_target = realpath($target);

	if ($rp_target && realpath($source) == $rp_target) {
		throw new Exception('same source and target', "mv [$source] to [$target]");
	}

	File::copy($source, $target, $mode);
	File::remove($source);
}


/**
 * Return last modified. Return Y-m-d H:i:s instead of unix timestamp if $sql_ts is true.
 */
public static function lastModified($path, $sql_ts = false) {

	FSEntry::isFile($path);

	if (($res = filemtime($path)) === false) {
		throw new Exception('file last modified failed', $path);
	}

	if ($sql_ts) {
		$res = date('Y-m-d H:i:s', $res);
	}

	return $res;
}


/**
 * Send file as http download. Use disposition = 'inline' for in browser display.
 * Exit after send. If file does not exist send 404 not found.
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
