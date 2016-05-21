<?php

require_once(dirname(__DIR__).'/src/File.class.php');
require_once(dirname(__DIR__).'/src/JSON.class.php');


/**
 * Example script for FileObject remote synchronization.
 * Install on remote server and adjust require_once.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */

if (!empty($_REQUEST['path']) || !empty($_REQUEST['format'])) {
	return '';
}

// format: misl = md5 + (width + height) + size + last_modified
$format = $_REQUEST['format'];
$path = $_REQUEST['path'];

$res = [];

if (strpos($format, 'm') !== false) {
	$res['md5'] = File::md5($path);
}

if (strpos($format, 'i') !== false) { 
	$ii = File::imageInfo($path, false);
	$res['width'] = $ii['width'];
	$res['height'] = $ii['height'];
}
  
if (strpos($format, 's') !== false) { 
	$res['size'] = File::size($path);
}

if (strpos($format, 'l') !== false) { 
	$res['last_modified'] = File::last_modified($path);
}

print JSON::encode($res);

