<?php

namespace rkphplib;

require_once(__DIR__.'/FSEntry.class.php');


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
		throw new Exception("Empty filename");
	}

	if ($file == 'STDIN') {
		$file = 'php://stdin';
	}

	if (self::$USE_FLOCK) {
		return self::_lload($file, $offset);
	}

	if (($data = file_get_contents($file, false, null, $offset)) === false) {
		throw new Exception("Failed to load file", $file);
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
			throw new Exception("fseek $offset failed", $file);
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
			throw new Exception('exclusive lock on "'.$file.'" failed');
		}
	}
	else if ($lock_mode == LOCK_SH) {
		if (!flock($fh, LOCK_SH)) {
			throw new Exception('shared lock on "'.$file.'" failed');
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
		throw new Exception("filesize failed", $file);
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
		throw new Exception("Empty filename");
	}

	if ($file == 'STDOUT') {
		$file = 'php://stdout';
	}

	if (self::$USE_FLOCK) {
		$flag = $flag & LOCK_EX;
	}

	if (($bytes = file_put_contents($file, $data, $flag)) === false) {
		throw new Exception("Failed to save data to file", $file);
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
		throw new Exception('remove file "'.$file.'" failed');
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
		throw new Exception('Invalid file open mode', 'mode=['.$mode.'] use rb|wb|ab');
  }

  if (!($fh = fopen($file, $mode))) {
		throw new Exception('Open file failed', 'file=['.$file.']');
  }

  if ($read_utf8_bom) {
    $bom = fread($fh, 3);
    if ($bom != $utf8_bom) {
      fseek($fh, 0);
    }
  }

  if ($write_utf8_bom) {
    if (!fwrite($fh, $utf8_bom)) {
			throw new Exception('Could not write utf-8 bom', 'file='.$file);
		}
  }

  return $fh;
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
		throw new Exception('Could not write csv line', 'fh=['.$fh.'] data=['.mb_substr(join('|', $data), 0, 40).' ... ]');
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
		throw new Exception('Could not write data', 'fh=['.$fh.'] data=['.mb_substr($data, 0, 40).' ... ]');
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
		throw new Exception('error reading csv from filehandle');
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


}
