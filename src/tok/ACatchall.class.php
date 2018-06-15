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
 * Return tokenized file. Export relative directory path.
 * 
 * @param string $file
 * @return string
 */
public function parseFile($file) {
	$relpath = $file;
  if (($pos = strpos($file, '/')) !== false) {
    $relpath = substr($file, $pos + 1);
  }

	$dir = dirname($relpath);
	$_REQUEST['dir'] = ($dir != '.') ? $dir : '';

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
 * @param array $suffix_list
 */
public function scan($directory, $suffix_list) {
	$files = Dir::scanTree('src', [ '.html' ]);

	foreach ($files as $file) {
		$this->processFile($file, $this->parseFile($file));
	}
}


}
