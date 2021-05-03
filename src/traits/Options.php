<?php

namespace rkphplib\traits;


/**
 * Trait for __construct($options = []).
 * 
 * @code …
 * require_once 'traits/Options.php';
 *
 * class SomeClass {
 * use \rkphplib\traits\Options;
 * public function __construct($options = []) {
 *    $this->setOptions($options);
 * }
 * @EOL
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
trait Options {

/**
 * If $this->key() exists call $this->key($value).
 * If $this->$key exists set $this->$key = $value.
 * If $key is method.name try $this->setMethod($name, $value).
 */
private function setOptions(array $options) : void {
	foreach ($options as $key => $value) {
		if (method_exists($this, $key)) {
			$this->$key($value);
		}
		else if (strpos($key, '.') > 0) {
			list ($name, $key) = explode('.', $key);
			$method = 'set'.ucfirst($name);
			if (method_exists($this, $method)) {
				$this->$method($key, $value);
			}
		}
		else if (property_exists($this, $key)) {
			$this->$key = $value;
		}
	}
}


}

