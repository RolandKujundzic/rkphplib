<?php

require_once '../../src/lib/log_debug.php';


class CustomHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface, SessionIdInterface {


public function __construct() {	
	session_set_save_handler($this, true);
	session_start();
}


public function open($save_path, $session_name) {
	\rkphplib\lib\log_debug("open($save_path, $session_name)");
	return true;
}   
	

public function close() {
	\rkphplib\lib\log_debug("close()");
	return true;
}    
		

public function read($id) {
	\rkphplib\lib\log_debug("read($id)");
	if (!file_exists('/tmp/custom_session.ser')) {
		return '';
	}

	$sess = unserialize(file_get_contents('/tmp/custom_session.ser'));
	return isset($sess[$id]) ? $sess[$id] : '';
}
	

public function write($id, $data) { 
	\rkphplib\lib\log_debug("write($id, $data)");
	$sess = unserialize(file_get_contents('/tmp/custom_session.ser'));
	$sess[$id] = $data;
	file_put_contents('/tmp/custom_session.ser', serialize($sess));
	return true;
} 
		

public function destroy($id) { 
	\rkphplib\lib\log_debug("destroy($id)");
	$sess = unserialize(file_get_contents('/tmp/custom_session.ser'));
	unset($sess[$id]);
	file_put_contents('/tmp/custom_session.ser', serialize($sess));
	return true;
}


public function gc($maxlifetime) {
	\rkphplib\lib\log_debug("gc($maxlifetime)");
	return true;
}


public function updateTimestamp($key, $val) {
	\rkphplib\lib\log_debug("updateTimestamp($key, $val)");
	return true;
}


public function validateId($key) {
	\rkphplib\lib\log_debug("validateId($key)");
	$val = $this->read($key);
	return !empty($val);
}


public function create_sid() {
	\rkphplib\lib\log_debug("create_sid()");
	return 'a'.time();
}


}


$handler = new CustomHandler();

if (isset($_SESSION['a'])) {
	print 'a=['.$_SESSION['a'].']';
}
else {
	print 'Use ?a=... to set session value';
}

if (isset($_REQUEST['a'])) {
	$_SESSION['a'] = $_REQUEST['a'];
}

