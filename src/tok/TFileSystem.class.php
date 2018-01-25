<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once(__DIR__.'/TokHelper.trait.php');
require_once($parent_dir.'/File.class.php');
require_once($parent_dir.'/Dir.class.php');

use \rkphplib\Exception;
use \rkphplib\File;
use \rkphplib\Dir;
use \rkphplib\FSEntry;



/**
 * File System plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class TFileSystem implements TokPlugin {
use TokHelper;


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
  $plugin['directory:is'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['directory'] = 0;
	$plugin['file:size'] = TokPlugin::REQUIRE_BODY;
	$plugin['file:copy'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['file:exists'] = TokPlugin::REQUIRE_BODY;
	$plugin['file'] = 0;

  return $plugin;
}



/**
 * Return error html if directory is not writeable|readable|existing.
 * If error return $p.error and set _REQUEST[error_directory_is]=1,
 * otherwise return $p.success (or '' if not set).
 *
 * @tok {directory:is:writeable}directory=data|#|error=1{:directory} 
 * If directory does not exists, try to create it and return error (=1) if failure.
 * Otherwise check if directory is writeable (and readable + executable).
 *
 * @tok {directory:is:existing}directory=data|#|error=<div class="error">No such directory: data</div>{:directory}
 * Return error (<div class="error">No such directory: data</div>) if directory does not exist.
 * 
 * @tok {directory:is_writeable}directory=data|#|
 * ajax=ajax/scan_directory|#|
 * ajax_callback=setup.isDirectoryResult|#|
 * error= Make directory writeable with: <tt>chmod -R 777 data</tt>|#|
 * wait= Scanning directory data{:directory}
 * return: <div class="wait">Scanning directory data ... <span id="ID"><img src="wait.png"></span></div>
 *
 * @throws
 * @param string $check value writeable|readable|existing
 * @param map $p keys directory, error
 * @return string
 */
public function tok_directory_is($param, $p) {
	$this->checkMap('directory:is:'.$param, $p, [ 'directory!', 'error!' ]);
	$is_readable_dir = FSEntry::isDir($p['directory'], false, true); 
	$ok = true;

	if (!empty($p['ajax']) && !empty($p['wait'])) {
		$p['ajax_output_id'] = 'wait_'.md5("tok_directory_is:$param:".$p['directory']);
		return $this->_directory_is_ajax($p);
	}

	if ($param == 'writeable') {
		if (!$is_readable_dir) {
			if (is_dir($p['directory'])) {
				return false; // not readable
			}

			// directory does not exist ... try Dir::create
			try {
				Dir::create($p['directory'], 0, true);
			}
			catch (\Exception $e) {
				$ok = false;
			}
		}
		else {
			$ok = Dir::check($p['directory'], 'writeable');
		}
	}
	else if ($param == 'existing') {
		$ok = $is_readable_dir;
	}
	else if ($param == 'readable') {
		$ok = $is_readable_dir && Dir::check($p['directory'], 'readable');
	}

	$res = '';

	if (!$ok) {
		$_REQUEST['error_directory_is'] = 1;
		$res = $p['error'];
	}
	else if (!empty($p['success'])) {
		$res = $p['success'];
	}

	return $res;
}


/**
 * Return ajax call html. Implement p.ajax_callback(id, data).
 *
 * @param map $p
 * @return string
 */
private function _directory_is_ajax($p) {
	$id = $p['ajax_output_id'];
	$ajax_url = $p['ajax'];
	$ajax_callback = $p['ajax_callback'];
	$wait_img = 'img/animated/ajax_wait_small.gif';

	File::exists($wait_img, true);

	$wait_html = '<div class="wait">'.$p['wait'].'<span id="'.$id.'" class="status"><img src="'.
		$wait_img.'" /></span></div>';

	$ajax_js = <<<END
<script>
$(function() {
	$.get('{$ajax_url}', function(data) {
		{$ajax_callback}('{$id}', data);
		$('#{$id}').html(data);
	});
});
</script>
END;

	return $wait_html."\n".$ajax_js;
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
 * Copy file from source to target.
 *
 * @throws
 * @tok_log
 * @param vector $p [ $source, $target ]
 * @return ''
 */
public function tok_file_copy($p) {
	File::copy($p[0], $p[1]);
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
