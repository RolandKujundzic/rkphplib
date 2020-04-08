<?php

namespace rkphplib;

require_once __DIR__.'/Exception.class.php';


/**
 * Custom exception with public properties http_error and internal_message.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 * @copyright 2020
 */
class RestException extends \Exception {

const ERR_UNKNOWN = 1;
const ERR_INVALID_API_CALL = 2;
const ERR_INVALID_ROUTE = 3;
const ERR_PHP = 4;
const ERR_CODE = 5;
const ERR_INVALID_INPUT = 6;
const ERR_NOT_IMPLEMENTED = 7;
const ERR_CONFIGURATION = 8;

// @var int $http_error
public $http_code = 400;

// @var string error $internal_mesage error detail you don't want to expose
public $internal_message = '';


/**
 * Class constructor.
 */
public function __construct(string $message, int $error_no, int $http_error = 400, string $internal_message = '') {
  parent::__construct($message, $error_no);
	$this->http_error = $http_error;
  $this->internal_message = $internal_message;
}


}

