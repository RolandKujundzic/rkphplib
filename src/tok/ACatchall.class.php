<?php

namespace rkphplib\tok;

$parent_dir = dirname(__DIR__);
require_once(__DIR__.'/TokPlugin.iface.php');
require_once(PATH_RKPHPLIB.'tok/Tokenizer.class.php');
require_once(PATH_RKPHPLIB.'tok/TBase.class.php');
require_once(PATH_RKPHPLIB.'File.class.php');
require_once(PATH_RKPHPLIB.'Dir.class.php');

use \rkphplib\Exception;
use \rkphplib\tok\Tokenizer;
use \rkphplib\tok\TBase;
use \rkphplib\FSEntry;
use \rkphplib\File;
use \rkphplib\Dir;


/**
 * Basic plugin parser. Subclass and overwrite tok_catchall for custom parsing.
 * Use lid=NULL for system data.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
abstract class ACatchall implements TokPlugin {

/** @var Tokenizer $tok */
protected $tok = null;

/** @var hash $crawl_dir */
protected $crawl_dir = [];

/** @var string $layout */
protected $layout = '';

/** @var string $source_dir */
protected $source_dir = '';



/**
 * Set layout and include files.
 * 
 * @param string $source_dir
 * @param string $layout (e.g. 'layout.inc.html')
 * @param array $include (e.g. [ 'content.inc.html' ])
 */
public function setLayoutInclude($source_dir, $layout, $include) {
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
 * Register catchall plugin.
 */
public function getPlugins($tok) {
  $plugin = [];
  $plugin['catchall'] = 0;
  return $plugin;
}


/**
 * Catch all plugins.
 *
 * @param string $param
 * @param string $arg
 * @return string
 */
abstract public function tok_catchall($param, $arg);


/**
 * Process parsed file data.
 *
 * @param string $file
 * @param string $data
 */
abstract public function processFile($file, $data);


/**
 * Return tokenized via layout. Assume _REQUEST[dir] is exported.
 * 
 * @param string $file
 * @return string
 */
public function parseLayout($file) {
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
 * 
 * @param string $file
 * @return string
 */
public function parseFile($file) {
	$this->log("parse $file");

	$this->tok = new Tokenizer();
	$tbase = new TBase();
	$this->tok->register($tbase);
	$this->tok->register($this);

	$this->tok->load($file);
	return $this->tok->toString();
}


/**
 * Call this.processFile($file) for every matching file
 * in directory. 
 *
 * @param string $directory
 * @param array $suffix_list (default [ 'inc.html' ]
 */
public function scan($directory, $suffix_list = [ 'inc.html' ]) {
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
 * Copy $source_dir content to $target_dir. Process files with suffix in
 * $parse_suffix_list.
 * 
 * @param string $source_dir
 * @param string $target_dir 
 * @param array $parse_suffix_list (default = [ 'inc.html', 'js', 'conf' ])
 * @param int $slen (default = 0, internal for recursive call)
 */
public function copy($source_dir, $target_dir, $parse_suffix_list = [ 'inc.html', 'js', 'conf' ], $slen = 0) {
	$entries = Dir::entries($source_dir);

	if (!$slen) {
		$this->log("copy $source_dir/ to $target_dir/");
		Dir::fixSuffixList($parse_suffix_list);
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
 *
 * @param string $message
 */
public function log($message) {
	print "  $message\n";
}


}

