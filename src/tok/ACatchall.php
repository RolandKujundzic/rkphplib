<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once __DIR__.'/TokPlugin.iface.php';
require_once __DIR__.'/Tokenizer.php';
require_once __DIR__.'/TBase.php';
require_once $parent_dir.'/File.php';
require_once $parent_dir.'/Dir.php';

use rkphplib\Exception;
use rkphplib\tok\Tokenizer;
use rkphplib\tok\TBase;
use rkphplib\FSEntry;
use rkphplib\File;
use rkphplib\Dir;


/**
 * Basic plugin parser. Subclass and overwrite tok_catchall for custom parsing.
 * Use lid=NULL for system data.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ACatchall implements TokPlugin {

// @var Tokenizer $tok
protected $tok = null;

// @var hash $crawl_dir
protected $crawl_dir = [];

// @var string $layout
protected $layout = '';

// @var string $source_dir
protected $source_dir = '';



/**
 * Set layout and include files.
 * 
 * @example setLayoutInclude('/path/to/www', 'layout.inc.html', [ 'content.inc.html' ]);
 */
public function setLayoutInclude(string $source_dir, string $layout, array $include) : void {
	$this->crawl_dir = [];
	$this->source_dir = $source_dir;
	$this->layout = $layout;

	$inc_html = Dir::scanTree($source_dir, [ 'inc.html' ]);
	$slen = strlen($source_dir);

	foreach ($inc_html as $file) {
		$base = basename($file);

		if (in_array($base, $include)) {
			$dir = substr(dirname($file), $slen + 1).'';

			if (!isset($crawl_dir[$dir])) {
				$this->crawl_dir[$dir] = [];
			}

			array_push($this->crawl_dir[$dir], $base);
		}
	}
}


/**
 *
 */
public function getPlugins(Tokenizer $tok) : array {
	$plugin = [];
	$plugin['catchall'] = 0;
	return $plugin;
}


/**
 * Catch all plugins. Usual result is empty string.
 */
abstract public function tok_catchall(string $param, string $arg) : string;


/**
 * Process parsed file data.
 */
abstract public function processFile(string $file, string $data) : void;


/**
 * Return tokenized via layout. Assume _REQUEST[dir] is exported.
 */
public function parseLayout(string $file) : string {
	$curr = getcwd();
	chdir($this->source_dir);

	$this->tok = new Tokenizer();
	$tbase = new TBase();
	$this->tok->register($tbase);
	$this->tok->register($this);

	$this->tok->load($this->layout);
	$res = $this->tok->toString();

	chdir($curr);
	return $res;
}


/**
 * Return tokenized file. Assume _REQUEST[dir] is exported.
 */
public function parseFile(string $file) : string {
	$this->log("parse $file");

	$this->tok = new Tokenizer();
	$tbase = new TBase();
	$this->tok->register($tbase);
	$this->tok->register($this);

	$this->tok->load($file);
	return $this->tok->toString();
}


/**
 * Call this.processFile($file) for every matching file in directory. 
 */
public function scan(string $directory, array $suffix_list = [ 'inc.html' ]) : void {
	$files = Dir::scanTree('src', $suffix_list);
	$dlen = strlen($directory);

	$this->log("scan $directory/");

	foreach ($files as $file) {
		$relpath = substr($file, $dlen + 1);
		$dir = dirname($relpath);
		$_REQUEST['dir'] = ($dir != '.') ? $dir : '';

		$this->processFile($file, $this->parseFile($file));
	}
}


/**
 * Copy $source_dir content to $target_dir. Process files with suffix in $parse_suffix_list.
 */
public function copy(string $source_dir, string $target_dir, array $parse_suffix_list = [ '.inc.html', '.js', '.conf' ], int $slen = 0) : void {
	$entries = Dir::entries($source_dir);

	if (!$slen) {
		$this->log("copy $source_dir/ to $target_dir/");
		$slen = strlen($source_dir);
	}

	foreach ($entries as $entry) {
		if (FSEntry::isDir($entry, false)) {
			$this->copy($entry, $target_dir, $parse_suffix_list, $slen);
		}
		else if (FSEntry::isFile($entry, false)) {
			$relpath = substr($entry, $slen + 1);
			$target = $target_dir.'/'.$relpath;
			$suffix = File::suffix($entry);

			Dir::create(dirname($target), 0, true);

			$dir = dirname($relpath);
			if ($dir == '.') {
				$dir = '';
			}

			$_REQUEST['dir'] = $dir;
			$base = basename($entry);

			if (isset($this->crawl_dir[$dir]) && in_array($base, $this->crawl_dir[$dir])) {
				$this->processFile(dirname($target).'/index.html', $this->parseLayout($entry));
			}
			if (in_array($suffix, $parse_suffix_list)) {
				$this->processFile($target, $this->parseFile($entry));
			}
			else {
				$this->log("load file $entry");
				$this->processFile($target, File::load($entry));
			}
		}
	}
}


/**
 * Print message to stdout (prepend 2x space, append \n).
 */
public function log(string $message) : void {
	print "  $message\n";
}


}
