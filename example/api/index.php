<?php

define('PATH_RKPHPLIB', __DIR__.'/../../src/');

require_once(PATH_RKPHPLIB.'ARestAPI.class.php');

use \rkphplib\ARestAPI;
use \rkphplib\File;


/**
 * Simple REST Server example.
 * 
 * @author Roland Kujundzic
 */
class APIExample extends ARestAPI {

  public static function apiMap() {
    return [
			'postSignup' => ['POST', 'signup/user_type/locale', 1], // signup/:user_type/:locale - user_type and locale are parameter
      'getSomeAction' => ['GET', 'some/action', 2], // some/action/:id or some/action&id=7 
      'putSomething' => ['PUT', 'something', 1]]; // something/:id
  }

  public function checkToken() {
    if ($this->_req['api_token'] != 'test:test') { $this->out(['error' => 'invalid api token'], 400); }
    return ['allow' => ['getSomeAction']];
  }

  public function run() {
    $this->parse(); // log or check $r if necessary
		$priv = $this->checkToken(); // check $this->req['api_token'] and return privileges
    $this->route(static::allow(static::apiMap(), $priv['allow'])); // set _req.api_call if authorized
    $method = $this->_req['api_call'];
    $this->$method();
  }

	protected function postSignup() {
    $this->out($this->_req);
	}

	protected function putSomething() {
    $this->out($this->_req);
	}

  protected function getSomeAction() {
    $this->out($this->_req);
  }
}


/**
 * M A I N 
 */

$api = new APIExample();

if (!empty($_SERVER['argv'][0])) {
	require_once(PATH_RKPHPLIB.'File.class.php');

	$index_php = $_SERVER['argv'][0];

	if (!File::exists(__DIR__.'.htaccess')) {
		File::save(__DIR__.'/.htaccess', APIExample::apacheHtaccess());
	}

	if (!File::exists(__DIR__.'/routing.php')) {
		File::save(__DIR__.'/routing.php', APIExample::phpAPIServer());
	}

	print "\nApache2: Use ".dirname(__DIR__)." as document root\n";
	print 'PHP buildin Webserver: php -S localhost:10080 '.dirname($index_php)."/routing.php\n";
	print "Authorization: basic auth (e.g. http://test:test@localhost:10080/some/action\n\n";
}
else {
	$api->run();
}

