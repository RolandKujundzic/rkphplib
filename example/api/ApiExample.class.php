<?php

require_once(PATH_RKPHPLIB.'ARestAPI.class.php');

use \rkphplib\RestServerException;
use \rkphplib\ARestAPI;
use \rkphplib\File;


/**
 * Simple REST Server example. Not implemented api routes call 
 * apiCallNotImplemented() and result this.result.
 * 
 * @author Roland Kujundzic
 */
class ApiExample extends ARestAPI {

/**
 * Check api authentication.
 */
public function checkRequest() {
	if (empty($this->request['token']) || $this->request['token'] != 'test:test') { 
		throw new RestServerException('invalid api token', self::ERR_INVALID_INPUT, 400);
	}

	return [];
}


/**
 * @api version 1.0, route PUT:/user/file/{id}
 * @api_consumes application/octet-stream
 * @api_summary Replace user file
 */
protected function putUserFile($file_id) {
}


/**
 * @api version 1.0, route POST:/user/file
 * @api_consumes multipart/form-data
 * @api_summary Upload user file
 */
protected function postUserFile() {
}


/**
 * @api version 1.0, route DELETE:/user/file/{id}
 * @api_consumes nothing
 * @api_summary Delete user file
 */
protected function deleteUserFile($file_id) {
}


/**
 * @api version 1.0, route POST:/user/{type}/{locale}
 * @api_consumes application/x-www-form-urlencode
 * @api_summary Create User 
 */
protected function postUser($type, $locale) {
}


/**
 * @api version 1.0, route PUT:/user/{id}
 * @api_summary Update user
 */
protected function putUser($id) {
}


/**
 * @api version 1.0, route GET:/user/{id}
 * @api_consumes nothing
 * @api_summary Return user data
 */
protected function getUser($id) {
}


}


