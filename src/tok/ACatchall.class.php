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

			if (in_array($suffix, $parse_suffix_list)) {
				$dir = dirname($relpath);
				$_REQUEST['dir'] = ($dir != '.') ? $dir : '';
				$content = $this->processFile($target, $this->parseFile($entry));
			}
			else {
				$this->log("copy $entry to $target");
				File::copy($entry, $target);
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

