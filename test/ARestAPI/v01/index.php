<?php

require_once('../rkphplib/src/ARestAPI.class.php');


class API extends rkphplib\ARestAPI {

	public static function apiMap($allow = array()) {
		static $map = ['postUser' => ['POST', 'user', 0], 'getUser' => ['GET', 'user', 2], 'getItem' => ['GET', 'item', 1]];
		return $map;
	}

	public function checkToken() {
		if ($this->_req['api_token'] != '123') { $this->out(['error' => 'invalid api token'], 400); }
		return ['allow' => ['getUser', 'postUser']];
	}

	public function run() {
		$r = $this->parse(); // log or check $r if necessary
		$priv = $this->checkToken(); // check $this->req['api_token'] and return privileges
		$r = $this->route($this->allow(self::apiMap(), $priv['allow'])); // get api_call if exists and is authorized
		$method = $this->_req['api_call'];
		$this->$method();
	}

	protected function postUser() {
		$this->out($this->_req);
	}

	protected function getUser() {
		$this->out($this->_req);
	}
}

$api = new API();
// print $api->apacheHtaccess();
// print $api->nginxConf();
$api->run();

