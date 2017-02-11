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

  public function checkToken() {
    if ($this->_req['api_token'] != 'test:test') { $this->out(['error' => 'invalid api token'], 400); }
    return ['allow' => ['getSomeAction']];
  }

  public function run() {
    $this->parse(); // log or check $r if necessary
    $this->route(static::allow(static::apiMap(), $priv['allow'])); // set _req.api_call if authorized

		$priv = $this->checkToken(); // check $this->req['api_token'] and return privileges
    $method = $this->_req['api_call'];
    $this->$method();
  }

	// POST: signup/:user_type/:locale - two parameter
	protected function postSignup($user_type, $locale) {
    $this->out($this->_req);
	}

	// PUT: something/:id - one parameter
	protected function putSomething($id) {
    $this->out($this->_req);
	}

	// GET: some/action - no parameter
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

	print __DIR__."\n";

	$index_php = $_SERVER['argv'][0];

	if (!File::exists(__DIR__.'.htaccess')) {
		File::save(__DIR__.'/.htaccess', APIExample::apacheHtaccess());
	}

	if (!File::exists(__DIR__.'/routing.php')) {
		File::save(__DIR__.'/routing.php', APIExample::phpAPIServer());
	}

	print "\nApache2: Use ".dirname(__DIR__)." as document root\n";
	print 'PHP buildin Webserver: php -S localhost:10080 '.dirname($index_php)."/routing.php\n";
	print "Authorization: basic auth (e.g. http://test:test@localhost:10080/some/action)\n\n";
}
else {
	error_log(print_r($_SERVER, true), 3, '/tmp/php.log');
	$api->run();
}

