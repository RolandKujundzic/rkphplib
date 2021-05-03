<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';


if (umask() > 0) {
	umask(0);
}


/**
 * Filesystem operations for files and directories. Set umask to 0.
 *
 * All methods are static.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class FSEntry {

// @var bool $CHMOD_ABORT abort if chmod failed (CHMOD is only possible if you are owner or root)
public static $CHMOD_ABORT = true;


/**
 * Return advanced suffix search. Use leading '!' to ignore suffix.
 * Use leading '~' for reqular expression match and '!~' for inverse match.
 * Throw exception if simple suffix list has no leading dot.
 *
 * @example …
 * $suffix = fixSuffixList([ '!Interface.php', '.php', '!~^A[A-Z]' ]);  
 * // $suffix = [ 'ignore' => [ 'Interface.php' ], 'unlike' => [ '^A[A-Z]' ] , 'like' => [], 'require' => [ '.php' ] ]
 * @EOL
 *
 * @example …
 * $suffix = fixSuffixList([ '.jpg', '.jpeg', '.png' ]);  
 * // $suffix = [ '.jpg', '.jpeg', '.png' ]
 * @EOL
 *
 */
public static function fixSuffixList(array $suffix_list) : array {
	$ignore = [];
	$like = [];
	$unlike = [];
	$require = [];
	$simple = true;
	$no_dot = '';

  for ($i = 0; $i < count($suffix_list); $i++) {
		$s = $suffix_list[$i];

		if (mb_substr($s, 0, 1) == '!') {
			array_push($ignore, mb_substr($s, 1));
			$simple = false;
		}
		else if (mb_substr($s, 0, 1) == '~') {
			array_push($like, mb_substr($s, 1));
			$simple = false;
		}
		else if (mb_substr($s, 0, 2) == '!~') {
			array_push($unlike, mb_substr($s, 2));
			$simple = false;
		}
		else {
			if (mb_substr($s, 0, 1) != '.') {
				$no_dot = $s;
			}

			array_push($require, $s);
		}
	}

	if ($simple) {
		if ($no_dot != '') {
			throw new Exception("use .$no_dot (leading dot missing)");
		}
	}
	
	return $simple ? $require : [ 'ignore' => $ignore, 'like' => $like, 'unlike' => $unlike, 'require' => $require ];
}


/**
 * True if $file suffix matches suffix_list entry.
 * If suffix_list = fixSuffixList([ ... ]) advanced matching is possible.
 * True if suffix_list is empty. Match is case insensitive.
 *
 * @example FSEntry::hasSuffix('file.inc.php', FSEntry::fixSuffixList([ '.php', '!inc.php' ]); // false
 * @example FSEntry::hasSuffix('pic.jpeg', FSEntry::fixSuffixList([ '~jpe?g$' ])); // true
 * @example FSEntry::hasSuffix('pic.jpeg', [ '.gif', '.png', '.jpg' ])); // false
 * @example FSEntry::hasSuffix('pic.jpeg', [ '.gif', '.png', '.jpg', '.jpeg' ])); // true
 * @see fixSuffixList
 */
public static function hasSuffix(string $file, array $suffix_list) : bool {
	$l = mb_strlen($file);

	if (!isset($suffix_list['require']) || count($suffix_list) != 4) {
		// simple match ...
		if (count($suffix_list) == 0) {
			return true;
		}

		foreach ($suffix_list as $suffix) {
			if (($pos = mb_strripos($file, $suffix)) !== false && ($l - $pos == mb_strlen($suffix))) {
				return true;
			}
		}
	
		return false;	
	}

	for ($i = 0; $i < count($suffix_list['ignore']); $i++) {
		$suffix = $suffix_list['ignore'][$i];
		if (($pos = mb_strripos($file, $suffix)) !== false && ($l - $pos == mb_strlen($suffix))) {
			return false;
		}
	}

	for ($i = 0; $i < count($suffix_list['unlike']); $i++) {
		$suffix = $suffix_list['unlike'][$i];
		if (preg_match('/'.$suffix.'/i', $file)) {
			return false;
		}
	}

	for ($i = 0; $i < count($suffix_list['like']); $i++) {
		$suffix = $suffix_list['like'][$i];
		if (stripos($file, $suffix) !== false) {
			return true;
		}
	}

	for ($i = 0; $i < count($suffix_list['require']); $i++) {
		$suffix = $suffix_list['require'][$i];
		if (($pos = mb_strripos($file, $suffix)) !== false && ($l - $pos == mb_strlen($suffix))) {
			return true;
		}
	}

	return false;
}


/**
 * Create symlink. It is ok if target does not exist. Link directory must exist. 
 * If $flag is 2^0: symlink to basename($target)
 * If $flag is 2^1: cd dirname($link); ln -s relpath($target) basename($link); cd back 
 */
public static function link(string $target, string $link, int $flag = 0) : void {

	$rp_target = realpath($target);
	if (is_link($link) && ($rp_target == realpath($link) || $rp_target == realpath(readlink($link)))) {
		// already exists
		return;
	}

	if (FSEntry::isFile($link, false)) {
		throw new Exception('remove existing file', "link=$link target=$target");
	}

	if (FSEntry::isDir($link, false)) {
		throw new Exception('remove existing directory', "link=$link target=$target");
	}

	if (!FSEntry::isDir(dirname($link))) {
		throw new Exception('link directory does not exist', "link=$link target=$target");
	}

	if (1 == ($flag & 1)) {
		if (!@symlink(basename($target), $link)) {
			throw new Exception('Could not create symlink', "link=$link target=".basename($target));
		}
	}
	else if (2 == ($flag & 2) && basename($link) != $link) {
		$rp_link = realpath(dirname($link));
		$rp_target = realpath($target);

		$curr_dir = getcwd();
		chdir(dirname($rp_link));

		if (mb_strpos($rp_target, $rp_link.'/') === 0) {
			$target = mb_substr($rp_target, mb_strlen($rp_link) + 1);
		}

		if (!@symlink($target, $link)) {
			throw new Exception('Could not create symlink', "link=$link target=$target");
		}

		chdir($curr_dir);
	}
	else {
		if (!@symlink($target, $link)) {
			throw new Exception('Could not create symlink', "link=$link target=$target");
		}
	}
}


/**
 * Remove link.
 */
public static function unlink(string $link) : void {

	if (!is_link($link)) {
		throw new Exception('no such link', "link=[$link]");
	}

	unlink($link);
}


/**
 * Return path info. Wrapper to pathinfo(). Optional options is
 * PATHINFO_DIRNAME | PATHINFO_BASENAME | PATHINFO_EXTENSION | PATHINFO_FILENAME.
 * If $opt = '' (default) return hash otherwise string.
 */
public static function path(string $path, int $opt = 0) {
	return pathinfo($path, $opt);
}


/**
 * Change $path mode. Privileges $mode are octal. Return true if change was successfull or 
 * change is not possible but privileges are already rw(x).
 */
public static function chmod(string $path, int $mode) : bool {

	if (mb_strlen(trim($path)) == 0) {
		throw new Exception('empty path');
	}

	if (empty($mode)) {
		throw new Exception('empty mode');
	}

	if (($entry = realpath($path)) === false) {
		throw new Exception('chmod on non-existing path', $path);
	}

	if (($stat = stat($entry)) === false) {
		throw new Exception('stat failed', $entry);
	}

	$has_priv = sprintf("0%o", 0777 & $stat['mode']);
	$res = true;

	if ($has_priv === decoct($mode)) {
		// no change necessary
	}
	else if (posix_getuid() != $stat['uid']) {
		// we can not chmod ...
		$perm = self::getPermission('', $stat['mode']); // e.g. drwxrw----
		$res = false;

		// perm[0] == 'l' is not possible because $stat = stat(realpath($path))
		if (($perm[0] == '-' && substr($perm, 7, 2) == 'rw') || ($perm[0] == 'd' && substr($perm, 7, 3) == 'rwx')) {
			$res = true;
		}
		else {
			// check group privileges
			$mygid = getmygid();

			if ($stat['gid'] == $mygid) {
				if (($perm[0] == '-' && substr($perm, 4, 2) == 'rw') || ($perm[0] == 'd' && substr($perm, 4, 3) == 'rwx')) {
					$res = true;
				}
			}
			else {
				$g = posix_getgrgid($stat['gid']);
				$pw = posix_getpwuid($mygid);

				if (in_array($pw['name'], $g['members'])) {
					if (($perm[0] == '-' && substr($perm, 4, 2) == 'rw') || ($perm[0] == 'd' && substr($perm, 4, 3) == 'rwx')) {
						$res = true;
					}
				}
			}
		}
	}
	else {
		try {
			$res = chmod($entry, $mode);
		}
		catch(\Exception $e) {
			$res = false;
		}
	}

	if (!$res) {
		if (!self::$CHMOD_ABORT) {
			return false;
		}

		throw new Exception('chmod failed', "$entry to $mode");
	}

	return $res;
}


/**
 * True if $path is link. Default is to throw exception if path is not link. 
 */
public static function isLink(string $path, bool $abort = true) : bool {

	if (empty($path)) {
		if ($abort) {
			throw new Exception('empty file path');
		}

		return false;
	}

	if (is_link($path) && readlink($path)) {
		return true;
	}
	else if ($abort) {
		throw new Exception('invalid link', $path);
	}

	return false;
}


/**
 * Return true if file exists. If $abort is true throw exception (default) otherwise return false.
 */
public static function isFile(string $path, bool $abort = true, bool $is_readable = true) : bool {

	if (empty($path)) {
		if ($abort) {
			throw new Exception('empty file path');
		}

		return false;
	}

	if (file_exists($path) && !is_dir($path) && (!$is_readable || is_readable($path))) {
		return true;
	}

	if ($abort && !is_link($path)) {
		throw new Exception('invalid file path', $path);
	}

	return false;
}


/**
 * Throw exception if prefix (default = DOCROOT) is not in path.
 * Throw exception if filename is not in allowed suffix list (if $allow_suffix != [] = default).
 * Return (real)path.
 */
public static function checkPath(string $path, string $prefix = '', array $allow_suffix = []) : string {
	if (empty($prefix)) {
		if (!defined('DOCROOT')) {
			throw new Exception('DOCROOT is undefined');
		}

		$prefix = DOCROOT;
	}

	if (substr($path, 0, 1) == '/') {
		$path = $prefix.$path;
	}

	$real_path = realpath($path);
	$real_prefix = realpath($prefix);

	if (!empty($real_path) && !empty($real_prefix)) {
		if (mb_strpos($real_path, $real_prefix) !== 0) {
			throw new Exception('invalid path', $real_path.' not in '.$real_prefix);
		}
	}
	else if (mb_strpos($path, $prefix) !== 0 || mb_strpos($path, '../') !== false) {
		throw new Exception('invalid path', $path.' not in '.$prefix);
	}

	if (empty($real_path)) {
		$real_path = $path;
	}

	$suffix_ok = count($allow_suffix) > 0 ? false : true;

	for ($i = 0; !$suffix_ok && $i < count($allow_suffix); $i++) {
		$suffix_ok = mb_substr($real_path, -1 *  mb_strlen($allow_suffix[$i])) === $allow_suffix[$i];
	}

	if (!$suffix_ok) {
		throw new Exception('invalid suffix', $real_path.' suffix not in '.join('|', $allow_suffix));
	}

	return $real_path;
}


/**
 * Return true if directory exists. If $abort is true throw exception (default) otherwise return false.
 */
public static function isDir(string $path, bool $abort = true, bool $is_readable = true) : bool {

	if (empty($path)) {
		if ($abort) {
			throw new Exception('empty directory path');
		}

		return false;
	}

	if (is_dir($path) && (!$is_readable || is_readable($path))) {
		return true;
	}

	if ($abort) {
		$msg = is_link($path) ? 'broken link' : 'invalid directory path';
		throw new Exception($msg, $path);
	}

	return false;
}


/**
 * Return file or directory stats. Return stat hash keys:
 * perms.[umask|human|octal|octal1|octal2|decimal|fileperms|mode1|mode2],
 * owner.[fileowner|filegroup|owner|group], file.[filename|realpath|dirname|basename],
 * filetype.[type|type_octal|is_file|is_dir|is_link|is_readable|is_writable],
 * device.[device|device_number|inode|link_count|link_to], size.[size|blocks|block_size],
 * time.[stime|mtime|atime|ctime|since|accessed|modified|created].
 */
public static function stat(string $path, bool $clearcache = false) : array {

	if ($clearcache) {
		clearstatcache();
	}

	if (($ss = stat($path)) === false) {
		throw new Exception("stat failed", $path);
	}

	$ts= array(
		0140000 =>'ssocket',
		0120000 =>'llink',
		0100000 =>'-file',
		0060000 =>'bblock',
		0040000 =>'ddir',
		0020000 =>'cchar',
		0010000 =>'pfifo');

	$p = $ss['mode'];
	$t = decoct($ss['mode'] & 0170000);

	$str  = (array_key_exists(octdec($t),$ts)) ? $ts[octdec($t)]{0} : 'u';
	$str .= (($p & 0x0100) ? 'r' : '-').(($p & 0x0080) ? 'w' : '-');
	$str .= (($p & 0x0040) ? (($p & 0x0800) ? 's' : 'x') : (($p & 0x0800) ? 'S' : '-'));
	$str .= (($p & 0x0020) ? 'r' : '-').(($p & 0x0010) ? 'w' : '-');
	$str .= (($p & 0x0008) ? (($p & 0x0400) ? 's' : 'x') : (($p & 0x0400) ? 'S' : '-'));
	$str .= (($p & 0x0004) ? 'r' : '-').(($p & 0x0002) ? 'w' : '-');
	$str .= (($p & 0x0001) ? (($p & 0x0200) ? 't' : 'x') : (($p & 0x0200) ? 'T' : '-'));

	$s = array();
	$s['perms'] = array(
		'umask' => sprintf("%04o", umask()),
		'human' => $str,
		'octal' => 0777 & $p,
		'octal1' => sprintf("%o", ($ss['mode'] & 000777)),
		'octal2' => sprintf("0%o", 0777 & $p),
		'decimal' => sprintf("%04o", $p),
		'fileperms' => fileperms($path),
		'mode1' => $p,
		'mode2' => $ss['mode']);

	$s['owner'] = array(
		'fileowner'=> $ss['uid'],
		'filegroup' => $ss['gid'],
		'owner' => (function_exists('posix_getpwuid')) ? posix_getpwuid($ss['uid']) : '',
		'group'=> (function_exists('posix_getgrgid')) ? posix_getgrgid($ss['gid']) : '');

	$s['file'] = array(
		'filename' => $path,
		'realpath' => (realpath($path) != $path) ? realpath($path) : '',
		'dirname' => dirname($path),
		'basename' => basename($path));

	$s['filetype'] = array(
		'type' => mb_substr($ts[octdec($t)],1),
		'type_octal' => sprintf("%07o", octdec($t)),
		'is_file' => is_file($path),
		'is_dir' => is_dir($path),
		'is_link' => is_link($path),
		'is_readable' => is_readable($path),
		'is_writable' => is_writable($path));

	$s['device'] = array(
		'device' => $ss['dev'], //Device
		'device_number' => $ss['rdev'], //Device number, if device.
		'inode' => $ss['ino'], //File serial number
		'link_count' => $ss['nlink'], //link count
		'link_to' => ($s['filetype']['is_link'] == 'link') ? readlink($path) : '');

	$s['size'] = array(
		'size' => $ss['size'], //Size of file, in bytes.
		'blocks' => $ss['blocks'], //Number 512-byte blocks allocated
		'block_size' => $ss['blksize'] //Optimal block size for I/O.
		);

	$since = min($ss['mtime'], $ss['atime'], $ss['ctime']);

	$s['time'] = array(
		'stime' => $since,
		'mtime' => $ss['mtime'], //Time of last modification
		'atime' => $ss['atime'], //Time of last access.
		'ctime' => $ss['ctime'], //Time of last status change
		'since' => date('Y-m-d H:i:s',$since),
		'accessed' => date('Y-m-d H:i:s',$ss['atime']),
		'modified' => date('Y-m-d H:i:s',$ss['mtime']),
		'created' => date('Y-m-d H:i:s',$ss['ctime']));

	return $s;
}


/**
 * Return permission in human readable form. Ignore path if perms > 0. Return 
 * 'u---------' for non existing $path.
 */
public static function getPermission(string $path, int $perms = 0) : string {
	if ($perms === 0) {
		if (($perms = @fileperms($path)) === false) {
			throw new Exception('invalid file path '.$path);
		}
	}

	switch ($perms & 0xF000) {
		case 0xC000: // Socket
			$info = 's';
			break;
		case 0xA000: // Symbolic Link
			$info = 'l';
			break;
		case 0x8000: // Regular File
			$info = '-';
			break;
		case 0x6000: // Block special
			$info = 'b';
			break;
		case 0x4000: // Directory
			$info = 'd';
			break;
		case 0x2000: // Character special
			$info = 'c';
			break;
		case 0x1000: // FIFO pipe
			$info = 'p';
			break;
		default: // unknown
			$info = 'u';
	}

	// owner
	$info .= (($perms & 0x0100) ? 'r' : '-');
	$info .= (($perms & 0x0080) ? 'w' : '-');
	$info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-'));

	// group
	$info .= (($perms & 0x0020) ? 'r' : '-');
	$info .= (($perms & 0x0010) ? 'w' : '-');
	$info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-'));

	// other
	$info .= (($perms & 0x0004) ? 'r' : '-');
	$info .= (($perms & 0x0002) ? 'w' : '-');
	$info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-'));

	return $info;
}


}

