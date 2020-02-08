<?php

namespace rkphplib;

require_once __DIR__.'/FSEntry.class.php';
require_once __DIR__.'/File.class.php';


// @const int DIR_DEFAULT_MODE octal, default directory creation mode, 0777 (uid < 1000) or 0755
if (!defined('DIR_DEFAULT_MODE')) {
	if (posix_getuid() < 1000) {
		define('DIR_DEFAULT_MODE', 0777);
	}
	else {
		define('DIR_DEFAULT_MODE', 0755);
	}
}



/**
 * Directory access wrapper. All methods are static.
 * Use DIR_DEFAULT_MODE = 0777 if uid < 1000 otherwise use 0755. 
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
class Dir {

// @const int CREATE_TARGET_PATH
const CREATE_TARGET_PATH = 1;

// @const int REMOVE_EXISTING
const REMOVE_EXISTING = 2;

// @var bool $SKIP_UNREADABLE directory copy behaviour
public static $SKIP_UNREADABLE = false;


/**
 * Return true if directory exists.
 */
public static function exists(?string $path, bool $required = false) : bool {
	$error = '';
	$pl = mb_strlen($path);

	if ($pl == 0) {
		$error = "empty directory path";
	}
	else if ($pl > 4096) {
		$error = "directory path too long ($pl)";
		$path = mb_substr($path, 0, 40)." ... ".mb_substr($path, -40);
	}
	else if (!is_dir($path)) {
		$error = "no such directory";
	}
	else if (!is_readable($path)) {
		$error = "directory is not readable";
	}

	if ($error) {
		if ($required) {
			throw new Exception($error, $path);
		}

		return false;
	}

	return true;
}


/**
 * Return last modified (int or string). Return Y-m-d H:i:s instead of unix timestamp if $sql_ts is true.
 */
public static function lastModified(string $path, bool $sql_ts = false) {

	FSEntry::isDir($path);

	if (($res = filemtime($path)) === false) {
		throw new Exception('dir last modified failed', $path);
	}

	if ($sql_ts) {
		$res = date('Y-m-d H:i:s', $res);
	}

	return $res;
}


/**
 * Remove directory.
 */
public static function remove(string $path, bool $must_exist = true) : void {

	if ($path != trim($path)) {
		throw new Exception('no leading or trailing whitespace allowed in path', $path);
	}
 
	if (substr($path, -1) == '/') {
		throw new Exception('no trailing / allowed in path', $path);
	}

	if (!$must_exist && !FSEntry::isDir($path, false)) {
		return;
	}

	if (FSEntry::isLink($path, false)) {
		FSEntry::unlink($path);
		return;
	}

	$entries = Dir::entries($path);

	foreach ($entries as $entry) {
		if (FSEntry::isFile($entry, false)) {
			File::remove($entry);
		}
		else {
			Dir::remove($entry);
		}
	}

	if (!rmdir($path) || FSEntry::isDir($path, false)) {
		throw new Exception('remove directory failed', $path);
	}
}


/**
 * Create directory. True if directory was created. False if already existed. 
 * Use $recursive = true to create parent directories. Priviles variable $mode is octal, 
 * default value is DIR_DEFAULT_MODE.
 */
public static function create(string $path, int $mode = 0, bool $recursive = false) : bool {

	if (!$mode) {
		$mode = DIR_DEFAULT_MODE;
	}

	if ($path == '.' || FSEntry::isDir($path, false)) {
		return false;
	}

	if (empty($path)) {
		throw new Exception("Empty directory path");
	}

	if ($path != trim($path)) {
		throw new Exception("No leading or trailing whitespace allowed",  $path);
	}

	if (mb_substr($path, -1) == '/') {
		throw new Exception("No trailing / allowed", $path);
	}

	if (empty($mode)) {
		throw new Exception("Empty create mode");
	}

	if (@mkdir($path, $mode, $recursive) === false) {
		if (($pos = mb_strpos($path, '/../')) !== false) {
			// try to fix "/../" in non-existing path problem
			while (($pos = mb_strpos($path, '/../')) !== false) {
				$path = dirname(mb_substr($path, 0, $pos)).'/'.mb_substr($path, $pos + 4);
			}

			if (!empty($path)) {
				return Dir::create($path, $mode, $recursive);
			}
		}

		throw new Exception("Failed to create directory", $path);
	}
	else if (class_exists('\\rkphplib\\tok\\Tokenizer', false)) {
		\rkphplib\tok\Tokenizer::log([ 'label' => 'create directory', 'message' => $path ], 'log.file_system');
	}

	return true;
}


/**
 * Move directory. Options flag is 2^n, e.g. Dir::CREATE_TARGET_PATH|Dir::REMOVE_EXISTING.
 */
public static function move(string $old_dir, string $new_dir, int $opt = 0) : void {
	FSEntry::isDir($old_dir);

	if (realpath($old_dir) == realpath($new_dir)) {
		throw new Exception('source and target directory are same', "mv [$old_dir] to [$new_dir]");
	}

	if ($opt & Dir::REMOVE_EXISTING && Dir::exists($new_dir)) {
		Dir::remove($new_dir);
	}

	if ($opt & Dir::CREATE_TARGET_PATH) {
		Dir::create(dirname($new_dir), 0, true);
	}

	if (!rename($old_dir, $new_dir)) {
		// rename is fast but works only on same device
		Dir::copy($old_dir, $new_dir);
		Dir::remove($old_dir);
	}

	FSEntry::isDir($new_dir);
}


/**
 * Copy directory. Skip unreadable entries. Preserve links if sub-path of source_dir is
 * defined as $link_root (e.g. $link_root = getcwd()).
 */
public static function copy(string $source_dir, string $target_dir, string $link_root = '') : void {

	if (empty($source_dir)) {
		throw new Exception("Source directory is empty");
	}

	if (empty($target_dir)) {
		throw new Exception("Target directory is empty");
	}

	$s = FSEntry::stat($source_dir);

	if (!FSEntry::isDir($target_dir, false)) {
		Dir::create($target_dir, $s['perms']['octal'], true);
	}

	$entries = Dir::entries($source_dir);
	foreach ($entries as $entry) {
		$s = FSEntry::stat($entry);

		if ($s['filetype']['is_link'] && $link_root) {
			$link_target = $s['file']['realpath'];
			$rpsd = realpath($source_dir);

			if (mb_strpos($rpsd, $link_root) === 0 && ($pos = mb_strpos($link_target, $link_root)) === 0) {
				if (mb_strpos($link_target, $rpsd) === 0) {
					$link_target = str_replace($rpsd.'/', '', $link_target);
				}
				else {
					$ppath = explode('/', str_replace($link_root.'/', '', $rpsd));
					$link_target = str_repeat('../', count($ppath)).str_replace($link_root.'/', '', $link_target);
				}
			}

			symlink($link_target, $target_dir.'/'.basename($entry));

			continue;
		}

		if (!$s['filetype']['is_readable']) {
			if (self::$SKIP_UNREADABLE) {
				continue;
			}

			throw new Exception("Entry is unreadable", $entry);
		}

		if ($s['filetype']['is_dir']) {
			$target_subdir = $target_dir.'/'.basename($entry);
			Dir::create($target_subdir, $s['perms']['octal'], true);
			Dir::copy($entry, $target_subdir, $link_root);
		}
		else if ($s['filetype']['is_file']) {
			File::copy($entry, $target_dir.'/'.basename($entry), $s['perms']['octal']);
		}
	}
}


/**
 * Return directory entries (with full path). Parameter $type is 0=any (default), 1=files, 2=directories.
 */
public static function entries(string $path, int $type = 0) : array {

	if (mb_substr($path, -1) == '/') {
		$path = mb_substr($path, 0, -1);
	}

	FSEntry::isDir($path);

	if (!($dh = opendir($path))) {
		throw new Exception('directory scan failed', $path);
	}

	$res = array();

	while (false !== ($entry = readdir($dh))) {

		if ($entry == '.' || $entry == '..') {
			continue;
		}

		if ($type > 0) {
			if (FSEntry::isDir($path.'/'.$entry, false)) {
				if ($type == 2) {
					array_push($res, $path.'/'.$entry);
				}
			}
			else if ($type == 1) {
				array_push($res, $path.'/'.$entry);
			}
		}
		else {
			array_push($res, $path.'/'.$entry);
		}
	}

	closedir($dh);

	return $res;
}


/**
 * Return directory entries (relative filepath, non-recursive). Parameter $sort is 2^n, e.g.
 * SCANDIR_SORT_ASCENDING=default|SCANDIR_SORT_DESCENDING|SCANDIR_SORT_NONE.
 */
public static function scan(string $path, int $sort = SCANDIR_SORT_ASCENDING) : array {
	return array_diff(scandir($path, $sort), [ '.', '..' ]);
}


/**
 * Remove leading dot [.] from suffix list entries and change to lower. Variable $suffix_list is changed.
 */
public static function fixSuffixList(array &$suffix_list) : void {
  for ($i = 0; $i < count($suffix_list); $i++) {
		$s = $suffix_list[$i];

		if (mb_substr($s, 0, 1) == '.') {
			$s = mb_substr($s, 1);
		}

		$suffix_list[$i] = mb_strtolower($s);
	}
}


/**
 * True if $file suffix is in suffix list. True if $suffix_list is empty.
 * Suffix comparsion is context insensitive.
 */
private static function _has_suffix(string $file, array $suffix_list) : bool {

	if (count($suffix_list) == 0) {
		return true;
	}

	$file = mb_strtolower($file);
	$l = mb_strlen($file);

	foreach ($suffix_list as $suffix) {
		$suffix = mb_strtolower($suffix);

		if (($pos = mb_strrpos($file, $suffix)) !== false && ($l - $pos == mb_strlen($suffix))) {
			return true;
		}
	}

	return false;
}


/**
 * Return files in directory with suffix in suffix_list (e.g [jpg, png] or [.jpg, .png]). 
 * If $rel_dir is set remove $rel_dir in every entry.
 */
public static function scanDir(string $path, array $suffix_list = array(), string $rel_dir = '') : array {

	self::fixSuffixList($suffix_list);

	$entries = Dir::entries($path);
	$found = array();

	foreach ($entries as $entry) {
		if (FSEntry::isFile($entry, false) && self::_has_suffix($entry, $suffix_list)) {
			if ($rel_dir) {
				array_push($found, str_replace($rel_dir, '', $entry));
			}
			else {
				array_push($found, $entry);
			}
		}
	}

	return $found;
}


/**
 * Return files from directory tree with suffix in suffix_list ([jpg, png] or [.jpg, .png]). 
 * Exclude directories found in exclude_dir list (use relative path).
 */
public static function scanTree(string $path, array $suffix_list = array(), array $exclude_dir = array(), bool $_recursion = false) : array {

	if (!$_recursion) {
		self::fixSuffixList($suffix_list);

		// prepend path to exclude_dir ...
		for ($i = 0; $i < count($exclude_dir); $i++) {
			if (($pos = mb_strpos($exclude_dir[$i], $path)) === false || $pos != 0) {
				$exclude_dir[$i] = $path.'/'.$exclude_dir[$i];
			}
		}
	}

	$entries = Dir::entries($path);
	$tree = array();

	foreach ($entries as $entry) {
		if (FSEntry::isDir($entry, false)) {
			$scan_subdir = true;

			for ($i = 0; $scan_subdir && $i < count($exclude_dir); $i++) {
				if (($pos = mb_strpos($entry, $exclude_dir[$i])) !== false && $pos == 0) {
					$scan_subdir = false;
				}
			}

			if ($scan_subdir) {
				$tree = array_merge($tree, Dir::scanTree($entry, $suffix_list, $exclude_dir, true));
			}
		}
		else if (FSEntry::isFile($entry, false) && self::_has_suffix($entry, $suffix_list)) {
			array_push($tree, $entry);
		}
	}

	return $tree;
}


/**
 * Return directory size (= sum of all filesizes in directory tree).
 */
public static function size(string $path) : int {

	$entries = Dir::entries($path);
	$size = 0;

	foreach ($entries as $entry) {
		if (FSEntry::isDir($entry, false)) {
			$size += Dir::size($entry);
		}
		else if (FSEntry::isFile($entry, false)) {
			$size += File::size($entry);
		}
	}

	return $size;
}


/**
 * Return true if all directory entries are $action (readable|writable).
 */
public static function check(string $path, string $action) : int {

	if ($action != 'readable' && $action != 'writeable') {
		throw new Exception("invalid action [$action] use check(PATH, readable|writeable)", "path=[$path] action=[$action]");
	}

	if (!FSEntry::isDir($path, false)) {
		return false;
	}

	$check_write = $action == 'writeable';
	$entries = Dir::entries($path);

	foreach ($entries as $entry) {
		if (FSEntry::isFile($entry, false)) {
			if ($check_write && !is_writable($entry)) {
				return false;
			}
		}
		else if (FSEntry::isDir($entry, false)) {
			if ($check_write && !is_writable($entry)) {
				return false;
			}

			Dir::check($entry, $action);
		}
		else {
			return false;
		}
	}

	$res = is_readable($path);

	if ($res && $check_write && !is_writable($path)) {
		$res = false;
	}

	return $res;
}


}
