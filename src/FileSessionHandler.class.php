<?php

namespace rkphplib;

require_once __DIR__.'/File.class.php';
require_once __DIR__.'/lib/dec2n.php';

use function rkphplib\lib\dec2n;


/**
 * File session handler. Use json files. Default session directory is data/.session/.
 * Session id is 16 digit log random base 62 number.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @phpVersionLt 7.0 class FileSessionHandler implements \SessionHandlerInterface {
 */
class FileSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface, \SessionIdInterface {

/**
 * Create file session handler.
 */
public function __construct($options = []) {	
	// \rkphplib\lib\log_debug('FileSessionHandler.__construct:24> start session');
	if (!isset($_SESSION)) {
		session_set_save_handler($this, true);
		if (!session_start()) {
			throw new Exception('session_start() failed');
		}
	}
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function open($save_path, $session_name) {
	// \rkphplib\lib\log_debug("FileSessionHandler.open:38> open($save_path, $session_name)");
	return true;
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function close() {
	// \rkphplib\lib\log_debug("FileSessionHandler.close:47> FileSessionHandler.close:47>");
	return true;
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function read($id) {
	// \rkphplib\lib\log_debug("FileSessionHandler.read:56> ($id)");
	$sfile = '';
	if (!File::exists($sfile)) {
		return '';
	}

	$sess = File::loadJSON($sfile);
	return isset($sess[$id]) ? $sess[$id] : '';
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function write($id, $data) { 
	// \rkphplib\lib\log_debug("FileSessionHandler.write:71> ($id, $data)");
	return false;
	$sfile = '';
	$sess = File::loadJSON($sfile);
	$sess[$id] = $data;
	File::saveJSON($sfile, $sess);
	return true;
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function destroy($id) { 
	// \rkphplib\lib\log_debug("FileSessionHandler.destroy:85> ($id)");
	return true;

	File::remove($sfile);
	return true;
}


/**
 * Session callback (SessionHandlerInterface)
 */
public function gc($lifetime) {
	// \rkphplib\lib\log_debug("FileSessionHandler.gc:97> ($lifetime)");
	// ToDo ... remove old files: < time() - $lifetime
	return true;
}


/**
 * Session callback (SessionUpdateTimestampHandlerInterface)
 * @phpVersionLt 7.0 skip 
 */
public function updateTimestamp($key, $val) {
	// \rkphplib\lib\log_debug("FileSessionHandler.updateTimestamp:108> ($key, $val)");
	return true;
}


/**
 * Session callback (SessionUpdateTimestampHandlerInterface)
 * @phpVersionLt 7.0 skip
 */
public function validateId($key) {
	// \rkphplib\lib\log_debug("FileSessionHandler.validateId:118> ($key)");
	$val = $this->read($key);
	return !empty($val);
}


/**
 * Session callback (SessionIdInterface)
 */
public function create_sid() {
  $id = '';

  for ($i = 0; $i < 4; $i++) {
    $id .= dec2n(mt_rand(4096, 65535), 16);
  }

	// \rkphplib\lib\log_debug("FileSessionHandler.create_sid:134> create_sid() = $id");
	$this->path .= '/'.$id.'.json';
  return $id;
}

}

