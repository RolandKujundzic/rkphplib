<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once($parent_dir.'/File.class.php');
require_once($parent_dir.'/Dir.class.php');

use \rkphplib\Exception;
use \rkphplib\File;
use \rkphplib\Dir;


/**
 * File System plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TFileSystem implements TokPlugin {


/**
 *
 */
public function getPlugins($tok) {
  $plugin = [];
  $plugin['directory:copy'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
  $plugin['directory:move'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
  $plugin['directory:create'] = TokPlugin::REQUIRE_BODY;
  $plugin['directory:exists'] = TokPlugin::REQUIRE_BODY;
  $plugin['directory:entries'] = TokPlugin::REQUIRE_BODY;
	$plugin['directory'] = 0;
	$plugin['file:size'] = TokPlugin::REQUIRE_BODY;
	$plugin['file:exists'] = TokPlugin::REQUIRE_BODY;
	$plugin['file'] = 0;

  return $plugin;
}


/**
 * Copy p[0] recursive to p[1].
 *
 * @throws
 * @param vector $p
 * @return ''
 */
public function tok_directory_copy($p) {
	Dir::copy($p[0], $p[1]);
	return '';
}


/**
 * Remove directory path.
 *
 * @throws if directory does not exist
 * @param string $path
 * @return ''
 */
public function tok_directory_remove($path) {
	Dir::remove(trim($path), true);
	return '';
}


/**
 * Move p[0] recursive to p[1].
 *
 * @throws
 * @param vector $p
 * @return ''
 */
public function tok_directory_move($p) {
	Dir::move($p[0], $p[1]);
	return '';
}


/**
 * Return 1|'' if directory (does not) exist.
 *
 * @throws if required and directory does not exist
 * @param string $param (required or empty = default)
 * @param string $path
 * @return 1|''
 */
public function tok_directory_exists($param, $path) {
	$required = $param == 'required';
	return Dir::exists(trim($path), $required) ? 1 : '';
}


/**
 * Return directory entries. If param is file|directory return
 * only files|subdirectories. Return comma separated list.
 *
 * @throws if directory does not exist
 * @param string $param (file|directory, empty = default = any)
 * @param string $path
 * @return string
 */
public function tok_directory_entries($param, $path) {
	if ($param == 'file') {
		$type = 1;
	}
	else if ($param == 'directory') {
		$type = 2;
	}
	else if (!$param) {
		$type = '';
	} 
	else {
		throw new Exception("invalid parameter [$param] use file|directory");
	}

	$entries = Dir::entries(trim($path), $type);
	sort($entries);
	return $entries;
}


/**
 * Create directory path (recursive).
 *
 * @tok {directory:create}a/b/c{:directory} = create directory a/b/c in docroot
 * @tok {directory:create:htaccess_protected}test{:directory} = create directory test, create test/.htaccess (no browser access)
 *
 * @throws
 * @param string $param
 * @param string $path
 * @return ''
 */
public function tok_directory_create($param, $path) {
	Dir::create($path, 0, true);

	if ($param == 'htaccess_protected') {
		File::save($path.'/.htaccess', 'Require all denied');
	}

	return '';
}


/**
 * Return File::size(path). if path does not exists return ''.
 *
 * @param string $param (format|not_empty|byte, empty = default = byte)
 * @param string $path
 * @return string
 */
public function tok_file_size($param, $path) {

	if (!File::exists($path)) {
		return '';
	}

  if ($param == 'format') {
    $res = File::formatSize($path, true);
  }
  else if ($param == 'not_empty') {
    $res = (File::size($path) > 0) ? 1 : 0;
  }
  else {
    $res = File::size($path);
  }

	return $res;
}


/**
 * Return 1|'' if file (does not) exist.
 *
 * @throws if required and file does not exist
 * @param string $param (required or empty = default)
 * @param string $path
 * @return 1|''
 */
public function tok_file_exists($param, $path) {
	$required = $param == 'required';
	return File::exists(trim($path), $required) ? 1 : '';
}


}
