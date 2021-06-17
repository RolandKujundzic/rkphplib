<?php

namespace rkphplib;

require_once __DIR__.'/File.php';
require_once __DIR__.'/Dir.php';



/**
 * Image access wrapper. Create *.nfo file (JSON hash with: name, width, height, md5, size, created, lastModified ...).
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class Image {

// @var string url (e.g. https://...)
protected $url = null;

// @var string path (absolute path to image)
protected $path = null;

// @var hash info (width, height, size, created, lastModified, md5, mime)
protected $info = [];



/**
 * Initialize image. Options:
 *
 * @see updateOptions 
 */
public function __construct(array $options = []) {
	$this->updateOptions($options);

	if (!empty($this->url) && !empty($this->path) && !File::exists($this->path)) {
		$this->download();
	}
}


/**
 * Download image. 
 * 
 * @see updateOptions
 */
public function download(array $options = []) : void {
	$this->updateOptions($options);
}


/**
 * Update this.url and this.path with options.url|path. Options:
 *
 * url: https://... 
 * path: /path/to/image
 * 
 * If path does not exist create parent directory.
 */
private function updateOptions(array $options) : void {

	if (!empty($options['url'])) {
		$this->url = $options['url'];
	}

	if (!empty($options['path'])) {
		$this->path = $options['path'];

		if (strlen($this->path) > 0 && strpos($this->path, '/') > 0 && !File::exists($this->path)) {
			$img_dir = dirname($this->path);

			if (strlen($img_dir) > 0 && $img_dir != '.' && $img_dir != '/') {
				Dir::create($img_dir, 0, true);
			}
		}
	}
}


}

