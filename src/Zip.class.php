<?php

namespace rkphplib;

require_once(__DIR__.'/lib/execute.php');
require_once(__DIR__.'/File.class.php');
require_once(__DIR__.'/Dir.class.php');


/**
 * Create *.zip files.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2018 Roland Kujundzic
 *
 */
class Zip {

// set [TZip::$is_broken = true] if you want to zip more than 1000 Files
public static $is_broken = false;

/** @var string $zip_dir temporary archive directory */
private $zip_dir = null;

/** @var ZipArchive $zip */
private $zip = null;

/** @var string zip filename */
private $save_as = null;



/**
 * Open zip archive. Create directory $file.mt_rand(10000000, 99999999).
 *
 * @param string $zip_file
 */
public function open($zip_file) {

	if (!is_null($this->zip_dir)) {
		throw new Exception('zip archive already open', "zip_file=$zip_file zip_dir=$zip_dir");
	}

	if (empty($zip_file)) {
		throw new Exception('empty zip filename');
	}

  $this->zip = new \ZipArchive();

  if (self::$is_broken) {
		$zip_dir = DOCROOT.'/data/tmp/'.basename($zip_file).'.'.mt_rand(10000000, 99999999);
		Dir::create($zip_dir, 0, true);
  }
  else {
    if ($this->zip->open($zip_file, \ZIPARCHIVE::CREATE) !== true) {
      throw new Exception('open zip archive '.$zip_file.' failed');
		}
	}

	$this->save_as = $zip_file;
}


/**
 * Add directory to zip archive. If local_dir is empty use same path.
 * 
 * @param string $dir
 * @param string $local_dir
 */
public function addDir($dir, $local_dir = '') {

	$entries = Dir::entries($dir);

	if (empty($local_dir)) {
		$local_dir = $dir;
	}
  
	foreach ($entries as $entry) {
		if (FSEntry::isDir($entry, false)) {
			$this->addDir($entry, $local_dir.'/'.basename($entry));
		}
		else if (FSEntry::isFile($entry, false)) {
			$this->addFile($entry, $local_dir.'/'.basename($entry));
		}
	}
}


/**
 * Add file to zip archive. If local_file is empty use same name.
 * 
 * @param string $file
 * @param string $local_file
 */
public function addFile($file, $local_file = '') {

	if (self::$is_broken) { 
		if (File::exists($file)) {
			$save_as = empty($local_file) ? $file : $local_file;

			if (basename($save_as) != $save_as) {
				Dir::create($this->zip_dir.'/'.dirname($save_as), 0, true);
			}

			File::copy($file, $this->zip_dir.'/'.$save_as);
		}
	}
	else if (File::exists($file, true) && !$this->zip->addFile($file, $local_file)) {
		throw new Exception("failed to add file [$file] rel_path=[$local_file]", "zip=".$this->save_as);
	}
}


/**
 * Close zip archive.
 */
public function close() {

	if (self::$is_broken) {
		$zip_file = basename($this->save_as);

		$curr = getcwd();
		chdir($this->zip_dir);
		\rkphplib\lib\execute("zip -r {:=zip_file} *", array('zip_file' => $zip_file));
		chdir($curr);

		File::move($this->zip_dir.'/'.$zip_file, $this->save_as);
		Dir::remove($this->zip_dir);
	}
	else {
		if (!$this->zip->close()) {
			throw new Exception('failed to close zip file '.$this->save_as);
		}

		if (!File::exists($this->save_as)) {
			throw new Exception('failed to create zip file '.$this->save_as);
    }

		$zip_dir = dirname($this->save_as).'/'.File::basename($this->save_as, true);
		if (Dir::exists($zip_dir)) {
			Dir::remove($zip_dir);
		}

		if (Dir::exists($this->zip_dir)) {
			Dir::remove($this->zip_dir);
		}
  }
}


/**
 * Extract $zip_file
 */
public static function extract($zip_file, $target_dir = '') {

	$zip = new \ZipArchive();

	if (empty($target_dir)) {
		if (substr($zip_file, -4) == '.zip' || substr($zip_file, -4) == '.ZIP') {
			$target_dir = substr($zip_file, 0, -4);
		}
		else {
			throw new Exception("invalid suffix in $zip_file");
		}
	}

	$stat = $zip->open($zip_file);
	if ($stat === TRUE) {
		$zip->extractTo($target_dir);
		$zip->close();
	}
	else {
		if ($stat == 5) {
			// some php5 64bit versions have bug if filenumber in zip archive > 800
			Zip::unzip($zip_file, $target_dir);
		}
    else {
      throw new Exception("failed to extract [$file] - error code $stat");
		}
	}
}


/**
 * Extract via unzip.
 * 
 * @param string $file
 * @param string $target_dir (default = '' = dirname($file).'/'.File::basename($file, true))
 */
public static function unzip($file, $target_dir = '') {

	if (empty($target_dir)) {
		$target_dir = dirname($file).'/'.File::basename($file, true);
	}

	if (Dir::exists($target_dir)) {
		throw new Exception("directory $target_dir already exists");
	}

  $tmp_dir = DOCROOT.'/data/tmp/unzip_'.md5($file.mt_rand(0, 65535));
	Dir::create($tmp_dir, 0, true);

	\rkphplib\lib\execute('unzip -d {:=tmp_dir} {:=zip_file}', array('tmp_dir' => $tmp_dir, 'zip_file' => $file));

	Dir::move($tmp_dir, $target_dir);
}


/**
 * Add file with text content to zip archive.
 *
 * @param string $file
 * @param string $text
 */
public function addText($file, $text) {

	if (empty($file)) {
		throw new Exception("empty filename");
	}

	if (self::$is_broken) {
		if (basename($file) != $file) {
			Dir::create($this->zip_dir.'/'.dirname($file), 0, true);
		}

		File::save($this->zip_dir.'/'.$file, $text);
	}
	else {
		$this->zip->addFromString($file, $text);
	}
}


/**
 * Create zip file $archive. Add files to archive (replace path with '' in file).
 *
 * @param string $archive
 * @param vector $files
 * @string $path (default = '')
 */
public function createArchive($archive, $files, $path = '') {
	$this->open($archive);

	foreach ($files as $file) {
		$local_file = empty($path) ? '' : str_replace($path.'/', '', $file);
		$this->addFile($file, $local_file);
	}

	$this->close();
}


}

