<?php

namespace rkphplib;

/**
 * Custom exception with two parameter constructor.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 *
 */
class Exception extends \Exception {

/** @var string error message detail you don't want to expose */
public $internal_message = '';


/**
 * Class constructor.
 *
 * @param string $message error message
 * @param string $internal_message error message detail
 */
public function __construct($message, $interal_message = '') {
  $this->internal_message = $interal_message;
  parent::__construct($message);
}

}

