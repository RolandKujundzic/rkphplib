<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/TokHelper.trait.php';
require_once $parent_dir.'/File.php';
require_once $parent_dir.'/Dir.php';

use rkphplib\Exception;
use rkphplib\File;
use rkphplib\Dir;
use rkphplib\FSEntry;



/**
 * File System plugin.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class FileSystem implements TokPlugin {
use TokHelper;

// @var array $csv
private $csv = [ 'ignore' => 0, 'file' => '', 'fv' => null, 'escape_crlf' => ' ', 'delimiter' => ';' ];


/**
 *
 */
public function getPlugins(Tokenizer $tok) : array {
	$this->tok = $tok;

	$plugin = [];
	$plugin['directory'] = 0;
	$plugin['directory:copy'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['directory:move'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['directory:create'] = TokPlugin::REQUIRE_BODY;
	$plugin['directory:exists'] = TokPlugin::REQUIRE_BODY;
	$plugin['directory:entries'] = TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['directory:is'] = TokPlugin::REQUIRE_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['file'] = 0;
	$plugin['file:info'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['file:size'] = TokPlugin::REQUIRE_BODY;
	$plugin['file:copy'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['file:download'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['file:exists'] = TokPlugin::REQUIRE_BODY;
	$plugin['csv_file'] = 0;
	$plugin['csv_file:conf'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::KV_BODY;
	$plugin['csv_file:append'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY | TokPlugin::LIST_BODY;
	$plugin['csv_file:open'] = TokPlugin::NO_PARAM | TokPlugin::REQUIRE_BODY;
	$plugin['csv_file:close'] = TokPlugin::NO_PARAM | TokPlugin::NO_BODY;
	$plugin['dirname'] = 0;
	$plugin['basename'] = 0;

	return $plugin;
}


/**
 * @tok {file:info}path/to/file.csv{:file} {var:file.since}
*  @tok {file:info}file.csv|#|{:=name} {:=since}{:file}
 * @see File::info
 */
public function tok_file_info(array $p) : string {
	$info = File::info($p[0]);
	$res = '';

	if (!count($info)) {
		return $res;
	}

	if (!isset($p[1])) {
		foreach ($info as $key => $value) {
			$this->tok->setVar('file.'.$key, $value);
		}
	}
	else {
		$res = $this->tok->replaceTags($p[1], $info);
	}
	
	return $res;
}


/**
 * Close csv file.
 */
public function tok_csv_file_close() : void {
	if ($this->csv['fh']) {
		File::close($this->csv['fh']);
		File::chmod($this->csv['file']);
	}
}


/**
 * @tok_alias {csv_file:conf}file=$path{:csv_file}
 */
public function tok_csv_file_open(string $path) : void {
	$this->tok_csv_file_conf([ 'file' => $path ]);
}


/**
 * Set csv configuration. Parameter:
 *  - file = open filename for writing (required - or use {csv_file:open} instead)
 *  - escape_crlf = ' ' (default = escape \r and \n with space)
 *  - delimiter = ; (default = use semicolon)
 *  - if = (optional if send and empty no action)
 */
public function tok_csv_file_conf(array $conf) : void {
	if (!empty($conf['ignore'])) {
		$this->csv = [ 'ignore' => '1' ];
		return;
	}

	$this->checkMap('csv_file:conf', $conf, [ 'file!' ]);
	$this->csv = array_merge([ 'ignore' => '', 'file' => '', 'escape_crlf' => ' ', 'delimiter' => ';' ], $conf);
	$this->csv['file'] = FSEntry::checkPath($this->csv['file']);
	$this->csv['fh'] = File::open($this->csv['file'], 'wb');

	if ($this->csv['delimiter'] == 'tab') {
		$this->csv['delimiter'] = "\t";
	}
}


/**
 * Add csv row. 
 */
public function tok_csv_file_append(array $cols) : void {
	if (!empty($conf['ignore'])) {
		return;
	}

	if (!$this->csv['fh']) {
		$this->tokError('call {csv_file:conf|open} before {:=ref}', [ 'csv_file:append' ]);
	}

	for ($i = 0; $i < count($cols); $i++) {
		$txt = str_replace('"', '""', $cols[$i]);
		$txt = preg_replace("/[\r\n]+/", $this->csv['escape_crlf'], $txt);
		$cols[$i] = '"'.$txt.'"';
	}

	$csv_line = join($this->csv['delimiter'], $cols)."\r\n";
	File::write($this->csv['fh'], $csv_line);
}


/**
 * Return basename($arg).
 *
 * @tok {basename:}a/b/c.gif{:basename} = c.gif
 * @tok {basename:a/b}a/b/c/d{:basename} = c/d
 */
public static function tok_basename(string $param, string $arg) : string {
	$res = basename(trim($arg));

	if ($param == 'no_suffix' && ($pos = mb_strrpos($res, '.')) !== false) {
		$res = mb_substr($res, 0, $pos);
	}
	else if (mb_strpos($param, '/') !== false && ($pos = mb_strpos($arg, $param)) !== false) {
		$res = mb_substr($arg, $pos + mb_strlen($param));
	}

	return $res;
}


/**
 * Return dirname(arg). Trim arg. If dirname(path) is empty return ".".
 *
 * @tok {dirname:}a/b/c{:dirname} = {dirname:1}a/b/c{:dirname} = a/b
 * @tok {dirname:2}a/b/c{:dirname} = {dirname:-1}a/b/c{:dirname} = a
 * @tok {dirname:NAME} (and _REQUEST[NAME]=x/y) = x
 */
public static function tok_dirname(string $param, string $arg) : string {
	$arg = trim($arg);
	$path = explode('/', $arg);
	$n = intval($param);
	$res = '';

	if ($n > 1) {
		for ($i = 0, $res = $arg; $res && $i < $n && $i < 50; $i++) {
			$res = dirname($res);
		}
	}
	else if ($n < 0) {
		for ($i = 1, $res = $path[0], $pos = $n * -1; $i < $pos; $i++) {
			$res .= '/'.$path[$i];
		}
	}
	else if (!empty($param) && !empty($_REQUEST[$param])) {
		$res = dirname($_REQUEST[$param]);
	}
	else {
		$res = dirname($arg);
	}

	if (!strlen($res)) {
		$res = '.';
	}

	return $res;
}


/**
 * Return error html if directory is not writeable|readable|existing (= $param).
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
 */
public function tok_directory_is(string $param, array $p) : string {
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
 */
private function _directory_is_ajax(array $p) : string {
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
 */
public function tok_directory_copy(array $p) : void {
	Dir::copy($p[0], $p[1]);
}


/**
 * Remove directory path.
 */
public function tok_directory_remove(string $path) : void {
	Dir::remove(trim($path), true);
}


/**
 * Move p[0] recursive to p[1].
 */
public function tok_directory_move(array $p) : void {
	Dir::move($p[0], $p[1]);
}


/**
 * Return 1|'' if directory (does not) exist. Throw exception if
 * $param == required and directory does not exist.
 */
public function tok_directory_exists(string $param, string $path) : string {
	$required = $param == 'required';
	return Dir::exists(trim($path), $required) ? 1 : '';
}


/**
 * Return directory entries. If $param is file|directory return
 * only files|subdirectories. Return comma separated list.
 * 
 * @tok {directory:entries:directory}.{:directory} - subdir,...
 * @tok {directory:entries:directory}dir=.|#|has_file=info.json|#|return_basename=1{:directory} - subdir,... (if subdir/info.json exists)
 */
public function tok_directory_entries(string $param, array $p) : string {
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

	if (!empty($p[0])) {
		$entries = Dir::entries(trim($path), $type);
	}
	else if (!empty($p['dir'])) {
		$entries = Dir::entries($p['dir'], $type);

		if (!empty($p['has_file'])) {
			$el = count($entries); 

			for ($i = 0; $i < $el; $i++) {
				if (!File::exists($entries[$i].'/'.$p['has_file'])) {
					unset($entries[$i]);
				}
			}

			$entries = array_values($entries);
		}
	}
	else {
		throw new Exception('invalid argument', print_r($p, true));
	}

	if (!empty($p['return_basename'])) {
		$el = count($entries); 
		for ($i = 0; $i < $el; $i++) {
			$entries[$i] = basename($entries[$i]);
		}
	}

	sort($entries);
	return join(',', $entries);
}


/**
 * Create directory path (recursive). Create .htaccess file if param is htaccess_protected|htaccess_deny
 * or htaccess_no_php.
 *
 * @tok {directory:create}a/b/c{:directory} = create directory a/b/c in docroot
 * @tok {directory:create:htaccess_protected}test{:directory} = create directory test, create test/.htaccess (no browser access)
 * @tok {directory:create:htaccess_deny}test{:directory} = same as above
 * @tok {directory:create:htaccess_no_php}data{:directory} = disable php execution in data/ via .htaccess (php_flag engine off)
 *
 * @see self::createDirectory()
 */
public function tok_directory_create(string $param, string $path) : void {
	self::createDirectory($path, $param);
}


/**
 * Create directory path (recursive). Use $feature = empty(=default)|htaccess_protected|htaccess_deny|htaccess_no_php.
 */
public static function createDirectory(string $path, string $feature) : void {
	Dir::create($path, 0, true);

	if ($feature == 'htaccess_protected' || $feature == 'htaccess_deny') {
		File::save($path.'/.htaccess', 'Require all denied');
	}
	else if ($feature == 'htaccess_no_php') {
		$no_php = <<<END
<FilesMatch "(?i).+\.ph(ar|p|tml|p[0-9]+)$">
Require all denied
</FilesMatch>
END;
		
		File::save($path.'/.htaccess', $no_php);
	}
	else if (!empty($feature)) {
		throw new Exception('Unkown feature', $feature);
	}
}


/**
 * Copy file from source to target ($p = [ $source, $target ]).
 */
public function tok_file_copy(array $p) : void {
	if (File::exists($p[1])) {
		if (File::md5($p[0]) == File::md5($p[1])) {
			return;
		}
	}

	File::copy($p[0], $p[1]);
}


/**
 * @tok {file:download}url=http://domain.tld/file.zip|#|save_as=data/.tmp/file.zip{:file}
 */
public function tok_file_download(array $p) : void {
	if (empty($p['url']) || empty($p['save_as'])) {
		return;
	}

	if (!($fh = fopen($url, 'rb'))) {
		File::save($p['save_as'], File::fromURL($p['url']));
	}

	file_put_contents($p['save_as'], $fh);
	fclose($fh);
}


/**
 * Return File::size(path). if path does not exists return ''.
 * Set file size format with $param (format|not_empty|byte, empty = default = byte).
 */
public function tok_file_size(string $param, string $path) : string {
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
 * Return 1|'' if file (does not) exist. Throw exception if $param == 'required' and file 
 * does not exist.
 */
public function tok_file_exists(string $param, string $path) : string {
	$required = $param == 'required';
	return File::exists(trim($path), $required) ? 1 : '';
}


}
