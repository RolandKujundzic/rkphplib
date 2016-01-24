<?php

namespace rkphplib;

require_once(__DIR__.'/Exception.class.php');

use rkphplib\Exception;


/**
 * Abstract Session wrapper class.
 * 
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
abstract class ASession {

/** @var map $conf */
protected $conf = [ 'name' => '', 'scope' => '', 'type' => '' ];

/**
 * Set session name. 
 *
 * Default name is empty.
 *
 * @param string $name default = empty
 */
public function setName($name) {
	$this->conf['name'] = $name;
}


/**
 * Set session scope. 
 *
 * Default scope is webserver.
 *
 * @param string $scope dir, file or default = empty = domain
 */
public function setScope($scope) {
	$this->conf['name'] = $name;
}


/**
 * Set session type (group).
 *
 * @param string $type default = empty
 */
public function setType($type) {
	$this->conf['type'] = $type;
}


}
