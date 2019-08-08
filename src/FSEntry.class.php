<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');


if (umask() > 0) {
	umask(0);
}


/**
 * Filesystem operations for files and directories. Set umask to 0.
 *
 * All methods are static.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2016 Roland Kujundzic
 *
 */
class FSEntry {

/** @var bool $CHMOD_ABORT abort if chmod failed (CHMOD is only possible if you are owner or root) */
public static $CHMOD_ABORT = true;



/**
 * Create symlink. If is ok if target does not exist. Link directory must exist. 
 */
public static function link(string $target, string $link, bool $target_basename = false) : void {

	if (is_link($link) && $target == readlink($link)) {
		// already exists
		return;
	}

	if (FSEntry::isFile($link, false)) {
		throw new Exception('remove existing file', "link=$link target=$target");
	}

	if (FSEntry::isDir($link, false)) {
		throw new Exception('remove existing directory', "link=$link target=$target");
	}

	if ($target_basename) {
		if (!@symlink(basename($target), $link)) {
			throw new Exception('Could not create symlink', "link=$link target=".basename($target));
		}
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
public static function path(string $path, int $opt = '') {
	return pathinfo($path, $opt);
}


/**
 * Change $path mode. Privileges $mode are octal. Return true if change was successfull or 
 * change is not possible but privileges are already sufficient.
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
	$res = false;

	if ($has_priv != decoct($mode)) {
		if (posix_getuid() != $stat['uid']) {
			// we can not chmod ...
			if ("$has_priv" == '0777' || "$has_priv" == '0666') {
				return true;
			}
		}

		$res = true;
		try {
			$res = chmod($entry, $mode);
		}
		catch(\Exception $e) {
			$res = false;
		}

		if (!$res) {
			if (!self::$CHMOD_ABORT) {
				return;
			}

			throw new Exception('chmod failed', "$entry to $mode");
		}
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


}
